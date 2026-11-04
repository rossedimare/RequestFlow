<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('changing the email address sends a new verification notification', function () {
    Notification::fake();
    $user = User::factory()->create();
    $oldEmail = $user->email;
    $newEmail = 'new-address@example.com';

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $newEmail,
    ]);

    $user->refresh();

    expect($user->email)->toBe($newEmail)
        ->and($user->email_verified_at)->toBeNull();

    // Exactly one link is sent, and it is built for the new address only: the
    // notifiable's address and the signed URL's hash both match the new email.
    Notification::assertSentTimes(VerifyEmail::class, 1);
    Notification::assertSentTo(
        $user,
        VerifyEmail::class,
        function (VerifyEmail $notification, array $channels, User $notifiable) use ($oldEmail, $newEmail) {
            $url = $notification->toMail($notifiable)->actionUrl;

            return $notifiable->getEmailForVerification() === $newEmail
                && str_contains($url, sha1($newEmail))
                && ! str_contains($url, sha1($oldEmail));
        },
    );
});

test('no verification notification is sent when only the name changes', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Another Name',
        'email' => $user->email,
    ]);

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('an unverified user can update their profile', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Renamed User',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->name)->toBe('Renamed User');
});

test('an unverified user can delete their account', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

describe('email change rate limit', function () {
    function changeEmailTo(User $user, string $email): TestResponse
    {
        return test()->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $email,
        ]);
    }

    test('the fourth email change within an hour is rejected before saving anything', function () {
        Notification::fake();
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            changeEmailTo($user, "address-{$i}@example.com")->assertSessionHasNoErrors();
        }

        changeEmailTo($user->refresh(), 'address-4@example.com')
            ->assertSessionHasErrors('email');

        expect($user->fresh()->email)->toBe('address-3@example.com');
        Notification::assertSentTimes(VerifyEmail::class, 3);
    });

    test('name-only updates do not consume the limit', function () {
        Notification::fake();
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)
                ->patch(route('profile.update'), ['name' => "Name {$i}", 'email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        // All three email changes are still available afterwards.
        foreach (range(1, 3) as $i) {
            changeEmailTo($user->refresh(), "address-{$i}@example.com")->assertSessionHasNoErrors();
        }

        expect($user->fresh()->name)->toBe('Name 5');
    });

    test('the limit is tracked per user', function () {
        Notification::fake();
        $first = User::factory()->create();
        $second = User::factory()->create();

        foreach (range(1, 3) as $i) {
            changeEmailTo($first, "first-{$i}@example.com")->assertSessionHasNoErrors();
        }

        changeEmailTo($second, 'second@example.com')->assertSessionHasNoErrors();

        expect($second->fresh()->email)->toBe('second@example.com');
    });

    test('email changes are allowed again after an hour', function () {
        Notification::fake();
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            changeEmailTo($user, "address-{$i}@example.com");
        }

        changeEmailTo($user->refresh(), 'blocked@example.com')->assertSessionHasErrors('email');

        $this->travelTo(Carbon::now()->addMinutes(61));

        changeEmailTo($user->refresh(), 'allowed@example.com')->assertSessionHasNoErrors();

        expect($user->fresh()->email)->toBe('allowed@example.com');
    });

    test('submitting the same email address is not counted', function () {
        Notification::fake();
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            changeEmailTo($user, $user->email)->assertSessionHasNoErrors();
        }

        changeEmailTo($user->refresh(), 'new@example.com')->assertSessionHasNoErrors();
        Notification::assertSentTimes(VerifyEmail::class, 1);
    });
});

describe('mail transport failure', function () {
    beforeEach(function () {
        // A transport that always fails, so the real mail pipeline throws.
        Mail::extend('failing', fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('Connection refused.');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });

        config([
            'mail.mailers.failing' => ['transport' => 'failing'],
            'mail.default' => 'failing',
        ]);
    });

    test('the profile stays saved and the user gets a warning instead of a 500', function () {
        Exceptions::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new-address@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast', [
                'type' => 'warning',
                'message' => 'Profile updated, but the verification email could not be sent. Use the resend link below to try again.',
            ]);

        $user->refresh();

        expect($user->email)->toBe('new-address@example.com')
            ->and($user->email_verified_at)->toBeNull();
        Exceptions::assertReported(TransportException::class);
    });

    test('failed deliveries still count toward the email change limit', function () {
        Exceptions::fake();
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            changeEmailTo($user->refresh(), "address-{$i}@example.com")->assertSessionHasNoErrors();
        }

        Exceptions::assertReportedCount(3);

        changeEmailTo($user->refresh(), 'address-4@example.com')->assertSessionHasErrors('email');

        expect($user->fresh()->email)->toBe('address-3@example.com');
    });

    test('the verification email can be resent once mail works again', function () {
        Exceptions::fake();
        $user = User::factory()->create();

        changeEmailTo($user, 'new-address@example.com')->assertSessionHasNoErrors();

        // Mail recovers: use the real resend endpoint from the profile page.
        config(['mail.default' => 'array']);
        Notification::fake();

        $this->actingAs($user->refresh())
            ->from(route('profile.edit'))
            ->post(route('verification.send'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn ($notification, $channels, $notifiable) => $notifiable->getEmailForVerification() === 'new-address@example.com',
        );
    });
});
