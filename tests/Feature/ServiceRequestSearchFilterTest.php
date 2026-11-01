<?php

use App\Models\ServiceRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('search matches the title', function () {
    $match = ServiceRequest::factory()->create(['title' => 'Broken monitor', 'description' => 'x']);
    ServiceRequest::factory()->create(['title' => 'New keyboard', 'description' => 'y']);

    $this->get(route('service-requests.index', ['search' => 'monitor']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.id', $match->id));
});

test('search matches the description case-insensitively', function () {
    $match = ServiceRequest::factory()->create(['title' => 'Hardware', 'description' => 'The Docking Station is dead']);
    ServiceRequest::factory()->create(['title' => 'Software', 'description' => 'Licence expired']);

    $this->get(route('service-requests.index', ['search' => 'docking station']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.id', $match->id));
});

test('search treats LIKE wildcards literally', function () {
    $match = ServiceRequest::factory()->create(['title' => 'Budget 100% approved', 'description' => 'x']);
    ServiceRequest::factory()->create(['title' => 'Budget 1000 approved', 'description' => 'x']);

    $this->get(route('service-requests.index', ['search' => '100%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.id', $match->id));
});

test('it filters by status', function () {
    $match = ServiceRequest::factory()->create(['status' => 'resolved']);
    ServiceRequest::factory()->create(['status' => 'open']);

    $this->get(route('service-requests.index', ['status' => 'resolved']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.id', $match->id));
});

test('it filters by priority', function () {
    $match = ServiceRequest::factory()->create(['priority' => 'urgent']);
    ServiceRequest::factory()->create(['priority' => 'low']);

    $this->get(route('service-requests.index', ['priority' => 'urgent']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.id', $match->id));
});

test('search and filters can be combined', function () {
    $match = ServiceRequest::factory()->create(['title' => 'Printer jam', 'status' => 'open', 'priority' => 'high']);
    ServiceRequest::factory()->create(['title' => 'Printer jam', 'status' => 'closed', 'priority' => 'high']);
    ServiceRequest::factory()->create(['title' => 'Printer jam', 'status' => 'open', 'priority' => 'low']);
    ServiceRequest::factory()->create(['title' => 'Laptop', 'status' => 'open', 'priority' => 'high']);

    $this->get(route('service-requests.index', [
        'search' => 'printer',
        'status' => 'open',
        'priority' => 'high',
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('serviceRequests.data', 1)
        ->where('serviceRequests.data.0.id', $match->id)
        ->where('filters', ['search' => 'printer', 'status' => 'open', 'priority' => 'high']));
});

test('empty parameters apply no filtering', function () {
    ServiceRequest::factory()->count(3)->create();

    $this->get(route('service-requests.index', ['search' => '', 'status' => '', 'priority' => '']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 3)
            ->where('filters', ['search' => null, 'status' => null, 'priority' => null]));
});

test('pagination links keep the search and filter parameters', function () {
    ServiceRequest::factory()->count(16)->create(['title' => 'Printer jam', 'status' => 'open', 'priority' => 'high']);
    ServiceRequest::factory()->create(['title' => 'Other', 'status' => 'closed', 'priority' => 'low']);

    $query = ['search' => 'printer', 'status' => 'open', 'priority' => 'high'];

    $this->get(route('service-requests.index', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests.data', 15)
            ->where('serviceRequests.total', 16)
            ->where('serviceRequests.next_page_url', fn (string $url) => str_contains($url, 'search=printer')
                && str_contains($url, 'status=open')
                && str_contains($url, 'priority=high')
                && str_contains($url, 'page=2')));

    $this->get(route('service-requests.index', [...$query, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('serviceRequests.data', 1));
});

test('invalid parameters are rejected with validation errors', function (array $query, string $field) {
    $this->from(route('service-requests.index'))
        ->get(route('service-requests.index', $query))
        ->assertRedirect(route('service-requests.index'))
        ->assertSessionHasErrors($field);
})->with([
    'unknown status' => [['status' => 'done'], 'status'],
    'unknown priority' => [['priority' => 'critical'], 'priority'],
    'search too long' => [['search' => str_repeat('a', 101)], 'search'],
    'search as array' => [['search' => ['a']], 'search'],
]);

test('guests are still redirected to login', function () {
    auth()->logout();

    $this->get(route('service-requests.index', ['status' => 'open']))
        ->assertRedirect(route('login'));
});
