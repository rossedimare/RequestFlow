<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login from the list and the detail', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->get(route('service-requests.index'))->assertRedirect(route('login'));
    $this->get(route('service-requests.show', $serviceRequest))->assertRedirect(route('login'));
});

test('an authenticated user sees requests created by other users, newest first', function () {
    $older = ServiceRequest::factory()->create(['created_at' => now()->subDay()]);
    $newer = ServiceRequest::factory()->create(['created_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/index')
            ->has('serviceRequests.data', 2)
            ->where('serviceRequests.data.0.id', $newer->id)
            ->where('serviceRequests.data.1.id', $older->id)
            ->has('serviceRequests.data.0.creator', fn (Assert $creator) => $creator
                ->where('id', $newer->creator->id)
                ->where('name', $newer->creator->name)));
});

test('the list is paginated at 15 per page', function () {
    ServiceRequest::factory()->count(16)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 15)
            ->where('serviceRequests.total', 16));
});

test('an authenticated user can view any single request', function () {
    $serviceRequest = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.show', $serviceRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/show')
            ->where('serviceRequest.id', $serviceRequest->id)
            ->where('serviceRequest.title', $serviceRequest->title)
            ->where('serviceRequest.status', $serviceRequest->status->value)
            ->where('serviceRequest.creator.name', $serviceRequest->creator->name));
});

test('viewing a request that does not exist returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.show', 999))
        ->assertNotFound();
});
