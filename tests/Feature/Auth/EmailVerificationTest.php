<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

describe('verification notice', function () {
    test('an unverified user sees the verification screen', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/verify-email'));
    });

    test('a verified user is redirected away from the verification screen', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard', absolute: false));
    });

    test('guests are redirected to login', function () {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    });
});

describe('verifying the email address', function () {
    test('a valid signed link verifies the email address', function () {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(verificationUrl($user))
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
        Event::assertDispatched(Verified::class);
    });

    test('a link with a wrong hash does not verify the email address', function () {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(verificationUrl($user, sha1('someone-else@example.com')))
            ->assertForbidden();

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    test('an unsigned link does not verify the email address', function () {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    test('a link meant for another user does not verify the signed-in user', function () {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(verificationUrl($other))
            ->assertForbidden();

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
            ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
    });
});

describe('resending the verification email', function () {
    test('an unverified user can request a new link', function () {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    });

    test('a verified user does not receive another link', function () {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'));

        Notification::assertNothingSent();
    });

    test('guests cannot request a link', function () {
        Notification::fake();

        $this->post(route('verification.send'))->assertRedirect(route('login'));

        Notification::assertNothingSent();
    });

    test('requests are rate limited', function () {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($user)->post(route('verification.send'));
        }

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertStatus(429);
    });
});

describe('access for unverified users', function () {
    test('they are redirected from verified-only pages to the verification screen', function (Closure $url) {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($url())
            ->assertRedirect(route('verification.notice'));
    })->with([
        'dashboard' => [fn () => route('dashboard')],
        'requests list' => [fn () => route('service-requests.index')],
        'new request form' => [fn () => route('service-requests.create')],
        'request detail' => [fn () => route('service-requests.show', ServiceRequest::factory()->create())],
        'security settings' => [fn () => route('security.edit')],
    ]);

    test('they cannot create a request', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('service-requests.store'), [
                'title' => 'Replace broken monitor',
                'description' => 'The monitor on desk 12 flickers.',
            ])
            ->assertRedirect(route('verification.notice'));

        expect(ServiceRequest::count())->toBe(0);
    });

    test('they cannot edit or delete their own request', function () {
        $user = User::factory()->unverified()->create();
        $serviceRequest = ServiceRequest::factory()->for($user, 'creator')->create(['title' => 'Original']);

        $this->actingAs($user)
            ->put(route('service-requests.update', $serviceRequest), [
                'title' => 'Changed',
                'description' => 'Changed.',
                'status' => 'open',
                'priority' => 'low',
            ])
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->delete(route('service-requests.destroy', $serviceRequest))
            ->assertRedirect(route('verification.notice'));

        expect($serviceRequest->fresh()->title)->toBe('Original');
    });

    test('they can still open the profile page to fix their email address', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('mustVerifyEmail', true));
    });

    test('verified users can reach verified-only pages', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('service-requests.index'))
            ->assertOk();
    });

    test('guests are still sent to login, not to the verification screen', function () {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('service-requests.index'))->assertRedirect(route('login'));
    });
});
