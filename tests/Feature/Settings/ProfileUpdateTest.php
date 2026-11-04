<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

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
