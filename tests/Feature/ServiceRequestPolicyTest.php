<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('any authenticated user can view, create, update and delete any service request', function (string $ability) {
    $user = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->create(); // created by someone else

    $arguments = in_array($ability, ['viewAny', 'create'], true)
        ? ServiceRequest::class
        : $serviceRequest;

    expect($user->can($ability, $arguments))->toBeTrue();
})->with(['viewAny', 'view', 'create', 'update', 'delete']);

test('guests are denied every ability', function (string $ability) {
    $serviceRequest = ServiceRequest::factory()->create();

    $arguments = in_array($ability, ['viewAny', 'create'], true)
        ? ServiceRequest::class
        : $serviceRequest;

    expect(Gate::forUser(null)->allows($ability, $arguments))->toBeFalse();
})->with(['viewAny', 'view', 'create', 'update', 'delete']);
