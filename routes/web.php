<?php

use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Everything here requires a signed-in user with a verified email address.
// Profile routes (routes/settings.php) and Fortify's verification routes stay
// reachable without verification so users can fix their email or resend the link.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('service-requests', ServiceRequestController::class)
        ->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
