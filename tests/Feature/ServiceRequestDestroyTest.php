<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('guests cannot delete a request', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->delete(route('service-requests.destroy', $serviceRequest))
        ->assertRedirect(route('login'));

    expect(ServiceRequest::count())->toBe(1);
});

test('any authenticated user can delete a request created by someone else', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', $serviceRequest))
        ->assertRedirect(route('service-requests.index'));

    expect(ServiceRequest::count())->toBe(0);
});

test('only the targeted request is deleted', function () {
    $target = ServiceRequest::factory()->create();
    $other = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', $target));

    expect(ServiceRequest::find($target->id))->toBeNull()
        ->and(ServiceRequest::find($other->id))->not->toBeNull();
});

test('deleting is forbidden when the policy denies it', function () {
    // Today the policy allows everyone; swap in a denying policy to prove
    // the controller really authorizes through it.
    $denyingPolicy = new class
    {
        public function delete(User $user, ServiceRequest $serviceRequest): bool
        {
            return false;
        }
    };
    Gate::policy(ServiceRequest::class, $denyingPolicy::class);

    $serviceRequest = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', $serviceRequest))
        ->assertForbidden();

    expect(ServiceRequest::count())->toBe(1);
});

test('deleting a request that does not exist returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('service-requests.destroy', 999))
        ->assertNotFound();
});
