<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's transactional email HTTP API, using Laravel's HTTP
 * client, so no extra package is needed.
 *
 * Every failure is reported as a Symfony TransportException, which callers such as
 * ProfileController already handle. Exception messages deliberately contain only
 * the HTTP status and Brevo's machine-readable error code: never the API key, the
 * recipients, the subject or the body. Exceptions raised by the HTTP client or the
 * JSON encoder are not attached as "previous" exceptions, because their messages
 * can include response bodies or message fragments.
 */
class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly int $timeout = 10,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new TransportException('The Brevo API key is not configured (BREVO_API_KEY).');
        }

        $original = $message->getOriginalMessage();

        // A raw message has no headers or body structure to read the fields from.
        if (! $original instanceof Message) {
            throw new TransportException('The Brevo transport can only send structured email messages.');
        }

        try {
            $email = MessageConverter::toEmail($original);
        } catch (MimeException) {
            throw new TransportException('The message could not be converted for the Brevo API.');
        }

        $payload = $this->payload($email);

        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey])
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                // Never follow redirects: a redirected POST would turn into a GET, and
                // the api-key header could be forwarded to another host.
                ->withoutRedirecting()
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            // Safe to keep: a connection error message holds the URL, not the headers or body.
            throw new TransportException('Could not connect to the Brevo API.', 0, $e);
        } catch (HttpClientException|\InvalidArgumentException|\JsonException) {
            throw new TransportException('The request to the Brevo API could not be completed.');
        }

        // Brevo answers a sent transactional email with 201 Created and its message id.
        if ($response->status() !== 201) {
            throw new TransportException($this->failureMessage($response->status(), $response->json('code')));
        }

        $messageId = $response->json('messageId');

        if (! is_string($messageId) || trim($messageId) === '') {
            throw new TransportException('The Brevo API response did not contain a message id.');
        }

        $message->setMessageId($messageId);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email): array
    {
        if ($email->getAttachments() !== []) {
            throw new TransportException('The Brevo transport does not support attachments.');
        }

        $from = $email->getFrom()[0] ?? null;

        if ($from === null || $email->getTo() === []) {
            throw new TransportException('The message needs a sender and at least one recipient.');
        }

        $htmlContent = $this->bodyToString($email->getHtmlBody());
        $textContent = $this->bodyToString($email->getTextBody());

        if ($htmlContent === null && $textContent === null) {
            throw new TransportException('The message has no content.');
        }

        $replyTo = $email->getReplyTo()[0] ?? null;

        return array_filter([
            'sender' => $this->address($from),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => $replyTo === null ? null : $this->address($replyTo),
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $htmlContent,
            'textContent' => $textContent,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map($this->address(...), $addresses));
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function address(Address $address): array
    {
        return $address->getName() === ''
            ? ['email' => $address->getAddress()]
            : ['email' => $address->getAddress(), 'name' => $address->getName()];
    }

    /**
     * @param  resource|string|null  $body
     */
    private function bodyToString(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }

    private function failureMessage(int $status, mixed $code): string
    {
        // Success is exactly 201. Other 2xx and 3xx statuses are unexpected: with redirects
        // disabled a 3xx is returned as is and must not be mistaken for a sent email.
        if ($status < 400) {
            return "The Brevo API returned an unexpected response (status {$status}).";
        }

        // Only a short machine-readable code (e.g. "unauthorized") is included. Brevo's
        // free-text message can echo recipient addresses, so it is never used.
        $code = is_string($code) && preg_match('/^[A-Za-z0-9_\-]{1,50}$/', $code) === 1
            ? " ({$code})"
            : '';

        return "The Brevo API request failed with status {$status}{$code}.";
    }
}
