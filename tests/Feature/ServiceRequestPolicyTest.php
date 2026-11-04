<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('any authenticated user can view and create requests, including those of others', function (string $ability) {
    $user = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->create(); // created by someone else

    $arguments = in_array($ability, ['viewAny', 'create'], true)
        ? ServiceRequest::class
        : $serviceRequest;

    expect($user->can($ability, $arguments))->toBeTrue();
})->with(['viewAny', 'view', 'create']);

test('the creator can update and delete their own request', function (string $ability) {
    $creator = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();

    expect($creator->can($ability, $serviceRequest))->toBeTrue();
})->with(['update', 'delete']);

test('a user who is not the creator cannot update or delete a request', function (string $ability) {
    $serviceRequest = ServiceRequest::factory()->create(); // created by someone else

    expect(User::factory()->create()->can($ability, $serviceRequest))->toBeFalse();
})->with(['update', 'delete']);

test('nobody can update or delete a request whose creator was deleted', function (string $ability) {
    $serviceRequest = ServiceRequest::factory()->create(['user_id' => null]);

    expect(User::factory()->create()->can($ability, $serviceRequest))->toBeFalse();
})->with(['update', 'delete']);

test('guests are denied every ability', function (string $ability) {
    $serviceRequest = ServiceRequest::factory()->create();

    $arguments = in_array($ability, ['viewAny', 'create'], true)
        ? ServiceRequest::class
        : $serviceRequest;

    expect(Gate::forUser(null)->allows($ability, $arguments))->toBeFalse();
})->with(['viewAny', 'view', 'create', 'update', 'delete']);

test('the creator check still works when the database returns user_id as a string', function (string $ability) {
    $creator = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();

    // Simulate drivers (MySQL, PostgreSQL) that return numeric strings.
    $serviceRequest->setRawAttributes([
        ...$serviceRequest->getAttributes(),
        'user_id' => (string) $creator->id,
    ]);

    expect($creator->can($ability, $serviceRequest))->toBeTrue()
        ->and(User::factory()->create()->can($ability, $serviceRequest))->toBeFalse();
})->with(['update', 'delete']);
