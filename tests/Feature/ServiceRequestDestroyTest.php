<?php

use App\Models\ServiceRequest;
use App\Models\User;

test('guests cannot delete a request', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->delete(route('service-requests.destroy', $serviceRequest))
        ->assertRedirect(route('login'));

    expect(ServiceRequest::count())->toBe(1);
});

test('the creator can delete their own request', function () {
    $creator = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();

    $this->actingAs($creator)
        ->delete(route('service-requests.destroy', $serviceRequest))
        ->assertRedirect(route('service-requests.index'));

    expect(ServiceRequest::count())->toBe(0);
});

test('a user cannot delete a request created by someone else', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', $serviceRequest))
        ->assertForbidden();

    expect(ServiceRequest::find($serviceRequest->id))->not->toBeNull();
});

test('nobody can delete a request whose creator was deleted', function () {
    $serviceRequest = ServiceRequest::factory()->create(['user_id' => null]);

    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', $serviceRequest))
        ->assertForbidden();

    expect(ServiceRequest::find($serviceRequest->id))->not->toBeNull();
});

test('only the targeted request is deleted', function () {
    $creator = User::factory()->create();
    $target = ServiceRequest::factory()->for($creator, 'creator')->create();
    $other = ServiceRequest::factory()->for($creator, 'creator')->create();

    $this->actingAs($creator)
        ->delete(route('service-requests.destroy', $target));

    expect(ServiceRequest::find($target->id))->toBeNull()
        ->and(ServiceRequest::find($other->id))->not->toBeNull();
});

test('deleting a request that does not exist returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', 999))
        ->assertNotFound();
});
