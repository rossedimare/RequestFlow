<?php

use App\Mail\BrevoTransport;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

const BREVO_KEY = 'test-brevo-key-do-not-leak';
const BREVO_URL = 'https://api.brevo.com/v3/smtp/email';

beforeEach(function () {
    // Nothing in this file may reach the real network.
    Http::preventStrayRequests();

    config([
        'mail.default' => 'brevo',
        'services.brevo.key' => BREVO_KEY,
        'mail.from.address' => 'sender@example.com',
        'mail.from.name' => 'RequestFlow',
    ]);
});

function brevoEmail(): Email
{
    return (new Email)
        ->from(new Address('sender@example.com', 'RequestFlow'))
        ->to(new Address('alice@example.com', 'Alice'))
        ->subject('Welcome aboard')
        ->html('<p>Hello <strong>Alice</strong></p>')
        ->text('Hello Alice');
}

function brevoTransport(?string $key = BREVO_KEY): BrevoTransport
{
    return new BrevoTransport($key);
}

// What Brevo answers for an accepted transactional email: 201 Created with a message id.
function brevoOk(string $messageId = '<202611090001.1@smtp-relay.mailin.fr>'): PromiseInterface
{
    return Http::response(['messageId' => $messageId], 201);
}

describe('request sent to Brevo', function () {
    test('posts the sender, recipient, subject, html and text parts with the api-key header', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        brevoTransport()->send(brevoEmail());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === BREVO_URL
            && $request->hasHeader('api-key', BREVO_KEY)
            && $request->hasHeader('Accept', 'application/json')
            && $request->data() === [
                'sender' => ['email' => 'sender@example.com', 'name' => 'RequestFlow'],
                'to' => [['email' => 'alice@example.com', 'name' => 'Alice']],
                'subject' => 'Welcome aboard',
                'htmlContent' => '<p>Hello <strong>Alice</strong></p>',
                'textContent' => 'Hello Alice',
            ]);
    });

    test('addresses without a display name are sent without a name', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        $email = (new Email)
            ->from('sender@example.com')
            ->to('alice@example.com')
            ->subject('Hi')
            ->text('Plain');

        brevoTransport()->send($email);

        Http::assertSent(fn (Request $request) => $request->data()['sender'] === ['email' => 'sender@example.com']
            && $request->data()['to'] === [['email' => 'alice@example.com']]);
    });

    test('a text-only message sends no htmlContent', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        $email = (new Email)->from('sender@example.com')->to('alice@example.com')->subject('Hi')->text('Plain only');

        brevoTransport()->send($email);

        Http::assertSent(fn (Request $request) => $request->data()['textContent'] === 'Plain only'
            && ! array_key_exists('htmlContent', $request->data()));
    });

    test('an html-only message sends no textContent', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        $email = (new Email)->from('sender@example.com')->to('alice@example.com')->subject('Hi')->html('<p>Only html</p>');

        brevoTransport()->send($email);

        Http::assertSent(fn (Request $request) => $request->data()['htmlContent'] === '<p>Only html</p>'
            && ! array_key_exists('textContent', $request->data()));
    });

    test('cc, bcc and reply-to are forwarded', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        $email = brevoEmail()
            ->cc('carol@example.com')
            ->bcc(new Address('dave@example.com', 'Dave'))
            ->replyTo('help@example.com');

        brevoTransport()->send($email);

        Http::assertSent(fn (Request $request) => $request->data()['cc'] === [['email' => 'carol@example.com']]
            && $request->data()['bcc'] === [['email' => 'dave@example.com', 'name' => 'Dave']]
            && $request->data()['replyTo'] === ['email' => 'help@example.com']);
    });
});

describe('response handling', function () {
    test('send() returns the SentMessage carrying the message id from Brevo', function () {
        Http::fake(['api.brevo.com/*' => brevoOk('<abc123@smtp-relay.mailin.fr>')]);

        $sent = brevoTransport()->send(brevoEmail());

        expect($sent)->toBeInstanceOf(SentMessage::class)
            ->and($sent->getMessageId())->toBe('<abc123@smtp-relay.mailin.fr>');
    });

    test('only 201 counts as sent: other success and redirect statuses are rejected', function (int $status) {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<id@brevo>'], $status)]);

        expect(fn () => brevoTransport()->send(brevoEmail()))
            ->toThrow(TransportException::class, "The Brevo API returned an unexpected response (status {$status}).");
    })->with([200, 202, 204, 301, 302, 304]);

    test('a 201 without a usable message id is rejected', function (mixed $body) {
        Http::fake(['api.brevo.com/*' => Http::response($body, 201)]);

        expect(fn () => brevoTransport()->send(brevoEmail()))
            ->toThrow(TransportException::class, 'The Brevo API response did not contain a message id.');
    })->with([
        'empty object' => [[]],
        'empty id' => [['messageId' => '']],
        'blank id' => [['messageId' => '   ']],
        'numeric id' => [['messageId' => 12345]],
        'array id' => [['messageId' => ['<a@b>']]],
        'null id' => [['messageId' => null]],
        'other field only' => [['messageIds' => ['<a@b>']]],
        'not JSON at all' => ['OK'],
    ]);

    test('redirects are never followed', function () {
        Http::fake(['api.brevo.com/*' => Http::response('', 302, ['Location' => 'https://evil.example/collect'])]);

        expect(fn () => brevoTransport()->send(brevoEmail()))
            ->toThrow(TransportException::class, 'status 302');

        // Only the original request was made; the api-key header never went to the other host.
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.example'));
    });

    test('a payload that cannot be JSON encoded fails with a generic TransportException', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        // Malformed UTF-8 in the subject makes json_encode fail.
        $email = brevoEmail()->subject("Broken \xB1\x31 subject");

        try {
            brevoTransport()->send($email);
            $this->fail('A TransportException was expected.');
        } catch (TransportException $e) {
            expect($e->getMessage())->toBe('The request to the Brevo API could not be completed.')
                ->and($e->getPrevious())->toBeNull();
        }

        Http::assertNothingSent();
    });
});

describe('message validation', function () {
    test('a raw message is rejected', function () {
        Http::fake();

        $envelope = new Envelope(new Address('sender@example.com'), [new Address('alice@example.com')]);

        expect(fn () => brevoTransport()->send(new RawMessage("Subject: Hi\r\n\r\nBody"), $envelope))
            ->toThrow(TransportException::class, 'can only send structured email messages');

        Http::assertNothingSent();
    });

    test('a message with only bcc recipients is rejected because Brevo needs a "to"', function () {
        Http::fake();

        $email = (new Email)->from('sender@example.com')->bcc('alice@example.com')->subject('Hi')->text('Body');

        expect(fn () => brevoTransport()->send($email))
            ->toThrow(TransportException::class, 'needs a sender and at least one recipient');

        Http::assertNothingSent();
    });

    test('a message with empty bodies is rejected', function () {
        Http::fake();

        $email = (new Email)->from('sender@example.com')->to('alice@example.com')->subject('Hi')->text('')->html('');

        expect(fn () => brevoTransport()->send($email))
            ->toThrow(TransportException::class, 'The message has no content.');

        Http::assertNothingSent();
    });

    test('a streamed body is read and sent', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'Streamed body');
        rewind($stream);

        $email = (new Email)->from('sender@example.com')->to('alice@example.com')->subject('Hi')->text($stream);

        brevoTransport()->send($email);

        Http::assertSent(fn (Request $request) => $request->data()['textContent'] === 'Streamed body');
    });
});

describe('failures', function () {
    test('HTTP errors become a TransportException without leaking secrets or content', function (int $status, array $body) {
        Http::fake(['api.brevo.com/*' => Http::response($body, $status)]);

        try {
            brevoTransport()->send(brevoEmail());
            $this->fail('A TransportException was expected.');
        } catch (TransportException $e) {
            expect($e->getMessage())
                ->toContain((string) $status)
                ->not->toContain(BREVO_KEY)
                ->not->toContain('alice@example.com')
                ->not->toContain('Welcome aboard')
                ->not->toContain('Hello');
        }
    })->with([
        'unauthorized' => [401, ['code' => 'unauthorized', 'message' => 'Key not found']],
        'bad request echoing the recipient' => [400, ['code' => 'invalid_parameter', 'message' => 'alice@example.com is not valid']],
        'server error without a body' => [500, []],
    ]);

    test('the machine-readable Brevo error code is included, the free-text message is not', function () {
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'invalid_parameter', 'message' => 'alice@example.com is not valid'], 400)]);

        expect(fn () => brevoTransport()->send(brevoEmail()))
            ->toThrow(TransportException::class, 'The Brevo API request failed with status 400 (invalid_parameter).');
    });

    test('an unsafe error code is ignored', function () {
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'alice@example.com'], 400)]);

        expect(fn () => brevoTransport()->send(brevoEmail()))
            ->toThrow(TransportException::class, 'The Brevo API request failed with status 400.');
    });

    test('a connection failure becomes a generic TransportException', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out reaching api.brevo.com with key '.BREVO_KEY));

        try {
            brevoTransport()->send(brevoEmail());
            $this->fail('A TransportException was expected.');
        } catch (TransportException $e) {
            expect($e->getMessage())
                ->toBe('Could not connect to the Brevo API.')
                ->not->toContain(BREVO_KEY);
        }
    });

    test('a missing API key fails without sending any request', function (?string $key) {
        Http::fake();

        expect(fn () => brevoTransport($key)->send(brevoEmail()))
            ->toThrow(TransportException::class, 'BREVO_API_KEY');

        Http::assertNothingSent();
    })->with([
        'null' => [null],
        'empty' => [''],
    ]);

    test('attachments are rejected instead of being silently dropped', function () {
        Http::fake();

        $email = brevoEmail()->attach('file contents', 'report.txt', 'text/plain');

        expect(fn () => brevoTransport()->send($email))
            ->toThrow(TransportException::class, 'attachments');

        Http::assertNothingSent();
    });
});

describe('Laravel notifications through the brevo mailer', function () {
    test('the verification email reaches Brevo with working html and text links', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);
        $user = User::factory()->unverified()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $user->sendEmailVerificationNotification();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($user) {
            $data = $request->data();

            return $request->hasHeader('api-key', BREVO_KEY)
                && $data['sender'] === ['email' => 'sender@example.com', 'name' => 'RequestFlow']
                && $data['to'] === [['email' => 'alice@example.com']]
                && is_string($data['subject']) && $data['subject'] !== ''
                && str_contains($data['htmlContent'], '/email/verify/'.$user->id.'/')
                && str_contains($data['textContent'], '/email/verify/'.$user->id.'/');
        });
    });

    test('the password reset email reaches Brevo with working html and text links', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $user->sendPasswordResetNotification('reset-token-123');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $data['to'] === [['email' => 'alice@example.com']]
                && is_string($data['subject']) && $data['subject'] !== ''
                && str_contains($data['htmlContent'], '/reset-password/reset-token-123')
                && str_contains($data['textContent'], '/reset-password/reset-token-123');
        });
    });

    test('the resend endpoint sends the verification email through Brevo', function () {
        Http::fake(['api.brevo.com/*' => brevoOk()]);
        $user = User::factory()->unverified()->create(['email' => 'alice@example.com']);

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-sent');

        Http::assertSent(fn (Request $request) => $request->data()['to'] === [['email' => 'alice@example.com']]);
    });

    test('changing the profile email still saves it and warns when Brevo fails', function () {
        Exceptions::fake();
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'unauthorized'], 401)]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new-address@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast', [
                'type' => 'warning',
                'message' => 'Profile updated, but the verification email could not be sent. Use the resend link below to try again.',
            ]);

        expect($user->fresh()->email)->toBe('new-address@example.com');
        Exceptions::assertReported(TransportException::class);
    });

    test('exceptions reported for failed deliveries never contain the key or email content', function (Closure $fakeBrevo) {
        Exceptions::fake();
        $fakeBrevo();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new-address@example.com'])
            ->assertRedirect(route('profile.edit'));

        Exceptions::assertReportedCount(1);

        // Check the whole exception chain, since report() logs the previous exceptions too.
        Exceptions::assertReported(function (TransportException $e) use ($user): bool {
            $forbidden = [BREVO_KEY, 'new-address@example.com', $user->email, '/email/verify/', 'signature='];

            for ($exception = $e; $exception !== null; $exception = $exception->getPrevious()) {
                foreach ($forbidden as $secret) {
                    if (str_contains($exception->getMessage(), $secret)) {
                        return false;
                    }
                }
            }

            return true;
        });
    })->with([
        'rejected key, Brevo message echoes it' => [fn () => Http::fake(['api.brevo.com/*' => Http::response(['code' => 'unauthorized', 'message' => 'Key not found: '.BREVO_KEY], 401)])],
        'invalid recipient, Brevo message echoes it' => [fn () => Http::fake(['api.brevo.com/*' => Http::response(['code' => 'invalid_parameter', 'message' => 'new-address@example.com is not valid'], 400)])],
        'redirect' => [fn () => Http::fake(['api.brevo.com/*' => Http::response('', 302, ['Location' => 'https://evil.example/collect'])])],
        'success status without a message id' => [fn () => Http::fake(['api.brevo.com/*' => Http::response([], 201)])],
        'connection error' => [fn () => Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds for https://api.brevo.com/v3/smtp/email'))],
    ]);
});
