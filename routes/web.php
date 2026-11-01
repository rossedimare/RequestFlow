<?php

use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::resource('service-requests', ServiceRequestController::class)
        ->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy']);
});
require __DIR__.'/settings.php';
