<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ProfileController extends Controller
{
    /** Email changes allowed per user per hour. */
    private const EMAIL_CHANGES_PER_HOUR = 3;

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');
        $limiterKey = 'profile-email-change:'.$user->id;

        if ($emailChanged) {
            // Reject before saving anything. Name-only updates never reach this
            // limiter, and Fortify's own resend limit is separate.
            if (RateLimiter::tooManyAttempts($limiterKey, self::EMAIL_CHANGES_PER_HOUR)) {
                $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

                throw ValidationException::withMessages([
                    'email' => __('Too many email changes. Please try again in :minutes minutes.', ['minutes' => $minutes]),
                ]);
            }

            // A new address has not been proven yet: clear the verification.
            $user->email_verified_at = null;
        }

        $user->save();

        $mailSent = true;

        if ($emailChanged) {
            RateLimiter::hit($limiterKey, 3600);

            // The profile is already saved, so a mail transport failure must not
            // turn this request into a 500. The user can resend from the profile page.
            try {
                $user->sendEmailVerificationNotification();
            } catch (TransportExceptionInterface $e) {
                report($e);
                $mailSent = false;
            }
        }

        Inertia::flash('toast', $mailSent
            ? ['type' => 'success', 'message' => __('Profile updated.')]
            : [
                'type' => 'warning',
                'message' => __('Profile updated, but the verification email could not be sent. Use the resend link below to try again.'),
            ]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
