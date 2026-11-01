<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * The internal request list is shared: every authenticated user may manage
 * every request for now. Guests are denied automatically because each method
 * requires a User. To restrict updates to the creator later, change
 * update()/delete() to compare $serviceRequest->user_id with $user->id.
 */
class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ServiceRequest $serviceRequest): bool
    {
        return true;
    }

    public function delete(User $user, ServiceRequest $serviceRequest): bool
    {
        return true;
    }
}
