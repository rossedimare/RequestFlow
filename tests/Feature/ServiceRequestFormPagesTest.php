<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login from the create and edit pages', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->get(route('service-requests.create'))->assertRedirect(route('login'));
    $this->get(route('service-requests.edit', $serviceRequest))->assertRedirect(route('login'));
});

test('an authenticated user can open the create page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('service-requests/create'));
});

test('the edit page receives the existing request values', function () {
    $creator = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();

    $this->actingAs($creator)
        ->get(route('service-requests.edit', $serviceRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/edit')
            ->where('serviceRequest.id', $serviceRequest->id)
            ->where('serviceRequest.title', $serviceRequest->title)
            ->where('serviceRequest.description', $serviceRequest->description)
            ->where('serviceRequest.status', $serviceRequest->status->value)
            ->where('serviceRequest.priority', $serviceRequest->priority->value));
});

test('a user cannot open the edit page of a request created by someone else', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.edit', $serviceRequest))
        ->assertForbidden();
});

test('nobody can open the edit page of a request whose creator was deleted', function () {
    $serviceRequest = ServiceRequest::factory()->create(['user_id' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.edit', $serviceRequest))
        ->assertForbidden();
});

test('the edit page returns 404 for a request that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.edit', 999999))
        ->assertNotFound();
});

test('creating a request flashes a success toast', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('service-requests.store'), [
            'title' => 'Replace broken monitor',
            'description' => 'The monitor on desk 12 flickers.',
        ])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Request created.']);
});

test('updating a request flashes a success toast', function () {
    $creator = User::factory()->create();
    $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();

    $this->actingAs($creator)
        ->put(route('service-requests.update', $serviceRequest), [
            'title' => 'Updated title',
            'description' => 'Updated description.',
            'status' => 'resolved',
            'priority' => 'low',
        ])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Request updated.']);
});
