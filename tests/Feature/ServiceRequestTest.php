<?php

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

test('a new service request defaults to open status and medium priority', function () {
    $user = User::factory()->create();

    $request = $user->serviceRequests()->create([
        'title' => 'Replace broken monitor',
        'description' => 'The monitor on desk 12 flickers.',
    ])->refresh();

    expect($request->status)->toBe(RequestStatus::Open)
        ->and($request->priority)->toBe(RequestPriority::Medium)
        ->and($request->creator->is($user))->toBeTrue();
});

test('user_id cannot be mass assigned', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $request = $owner->serviceRequests()->create([
        'user_id' => $other->id,
        'title' => 'Spoofed creator',
        'description' => 'Should belong to the owner.',
    ]);

    expect($request->user_id)->toBe($owner->id);
});

test('a service request is kept with a null creator when its user is deleted', function () {
    $request = ServiceRequest::factory()->create();

    $request->creator->delete();

    expect($request->fresh())->not->toBeNull()
        ->and($request->fresh()->user_id)->toBeNull();
});

test('user_id is cast to an integer and null stays null', function () {
    $request = new ServiceRequest;

    $request->setRawAttributes(['user_id' => '7']);
    expect($request->user_id)->toBe(7);

    $request->setRawAttributes(['user_id' => null]);
    expect($request->user_id)->toBeNull();
});
