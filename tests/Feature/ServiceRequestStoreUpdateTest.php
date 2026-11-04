<?php

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

function validPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Replace broken monitor',
        'description' => 'The monitor on desk 12 flickers.',
        'status' => 'in_progress',
        'priority' => 'high',
    ], $overrides);
}

describe('store', function () {
    test('guests cannot create a request', function () {
        $this->post(route('service-requests.store'), validPayload())
            ->assertRedirect(route('login'));

        expect(ServiceRequest::count())->toBe(0);
    });

    test('an authenticated user can create a request and becomes its creator', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('service-requests.store'), [
            'title' => 'Replace broken monitor',
            'description' => 'The monitor on desk 12 flickers.',
            'priority' => 'high',
        ]);

        $created = ServiceRequest::firstOrFail();
        $response->assertRedirect(route('service-requests.show', $created));

        expect($created->user_id)->toBe($user->id)
            ->and($created->title)->toBe('Replace broken monitor')
            ->and($created->priority)->toBe(RequestPriority::High)
            ->and($created->status)->toBe(RequestStatus::Open);
    });

    test('priority defaults to medium when omitted', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('service-requests.store'), ['title' => 'T', 'description' => 'D'])
            ->assertSessionHasNoErrors();

        expect(ServiceRequest::firstOrFail()->priority)->toBe(RequestPriority::Medium);
    });

    test('status and user_id sent by the client are ignored', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('service-requests.store'), validPayload([
            'status' => 'closed',
            'user_id' => $other->id,
        ]));

        $created = ServiceRequest::firstOrFail();

        expect($created->status)->toBe(RequestStatus::Open)
            ->and($created->user_id)->toBe($user->id);
    });

    test('invalid input is rejected with validation errors', function (array $overrides, string $field) {
        $this->actingAs(User::factory()->create())
            ->post(route('service-requests.store'), validPayload($overrides))
            ->assertSessionHasErrors($field);

        expect(ServiceRequest::count())->toBe(0);
    })->with([
        'missing title' => [['title' => ''], 'title'],
        'title too long' => [['title' => str_repeat('a', 256)], 'title'],
        'missing description' => [['description' => ''], 'description'],
        'description too long' => [['description' => str_repeat('a', 5001)], 'description'],
        'invalid priority' => [['priority' => 'critical'], 'priority'],
    ]);
});

describe('update', function () {
    test('guests cannot update a request', function () {
        $serviceRequest = ServiceRequest::factory()->create(['title' => 'Original']);

        $this->put(route('service-requests.update', $serviceRequest), validPayload())
            ->assertRedirect(route('login'));

        expect($serviceRequest->fresh()->title)->toBe('Original');
    });

    test('the creator can update their own request', function () {
        $creator = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();

        $this->actingAs($creator)
            ->put(route('service-requests.update', $serviceRequest), validPayload())
            ->assertRedirect(route('service-requests.show', $serviceRequest));

        $serviceRequest->refresh();

        expect($serviceRequest->title)->toBe('Replace broken monitor')
            ->and($serviceRequest->status)->toBe(RequestStatus::InProgress)
            ->and($serviceRequest->priority)->toBe(RequestPriority::High)
            ->and($serviceRequest->user_id)->toBe($creator->id);
    });

    test('a user cannot update a request created by someone else', function () {
        $serviceRequest = ServiceRequest::factory()->create([
            'title' => 'Original',
            'status' => RequestStatus::Open,
            'priority' => RequestPriority::Low,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('service-requests.update', $serviceRequest), validPayload())
            ->assertForbidden();

        $serviceRequest->refresh();

        expect($serviceRequest->title)->toBe('Original')
            ->and($serviceRequest->status)->toBe(RequestStatus::Open)
            ->and($serviceRequest->priority)->toBe(RequestPriority::Low);
    });

    test('nobody can update a request whose creator was deleted', function () {
        $serviceRequest = ServiceRequest::factory()->create([
            'user_id' => null,
            'title' => 'Original',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('service-requests.update', $serviceRequest), validPayload())
            ->assertForbidden();

        expect($serviceRequest->fresh()->title)->toBe('Original');
    });

    test('user_id cannot be changed through an update', function () {
        $creator = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create();
        $other = User::factory()->create();

        $this->actingAs($creator)->put(
            route('service-requests.update', $serviceRequest),
            validPayload(['user_id' => $other->id]),
        )->assertRedirect(route('service-requests.show', $serviceRequest));

        expect($serviceRequest->fresh()->user_id)->toBe($creator->id);
    });

    test('invalid input is rejected with validation errors', function (array $overrides, string $field) {
        $creator = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->for($creator, 'creator')->create(['title' => 'Original']);

        $this->actingAs($creator)
            ->put(route('service-requests.update', $serviceRequest), validPayload($overrides))
            ->assertSessionHasErrors($field);

        expect($serviceRequest->fresh()->title)->toBe('Original');
    })->with([
        'missing title' => [['title' => ''], 'title'],
        'title too long' => [['title' => str_repeat('a', 256)], 'title'],
        'missing description' => [['description' => ''], 'description'],
        'invalid status' => [['status' => 'done'], 'status'],
        'missing status' => [['status' => ''], 'status'],
        'invalid priority' => [['priority' => 'critical'], 'priority'],
        'missing priority' => [['priority' => ''], 'priority'],
    ]);

    test('updating a request that does not exist returns 404', function () {
        $this->actingAs(User::factory()->create())
            ->put(route('service-requests.update', 999), validPayload())
            ->assertNotFound();
    });
});
