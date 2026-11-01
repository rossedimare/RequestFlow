<?php

namespace App\Concerns;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ServiceRequestValidationRules
{
    /**
     * Get the validation rules used to validate a service request title.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function titleRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a service request description.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function descriptionRules(): array
    {
        return ['required', 'string', 'max:5000'];
    }

    /**
     * Get the validation rules used to validate a service request status.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function statusRules(): array
    {
        return ['required', Rule::enum(RequestStatus::class)];
    }

    /**
     * Get the validation rules used to validate a service request priority.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function priorityRules(): array
    {
        return ['required', Rule::enum(RequestPriority::class)];
    }
}
