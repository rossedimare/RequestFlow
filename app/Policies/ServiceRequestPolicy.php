<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * The request list is shared: every authenticated user may view every request
 * and create new ones. Only the creator may update or delete a request.
 * Requests whose creator deleted their account (user_id is null) stay visible
 * but can no longer be changed by anyone. Guests are denied automatically
 * because each method requires a User.
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
        return $this->isCreator($user, $serviceRequest);
    }

    public function delete(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->isCreator($user, $serviceRequest);
    }

    /**
     * A null user_id never matches, so orphaned requests are read-only.
     */
    private function isCreator(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->user_id !== null
            && $serviceRequest->user_id === $user->id;
    }
}
