<?php

namespace App\Http\Requests;

use App\Concerns\ServiceRequestValidationRules;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequestRequest extends FormRequest
{
    use ServiceRequestValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $serviceRequest = $this->route('service_request');

        return $serviceRequest instanceof ServiceRequest
            && ($this->user()?->can('update', $serviceRequest) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => $this->titleRules(),
            'description' => $this->descriptionRules(),
            'status' => $this->statusRules(),
            'priority' => $this->priorityRules(),
        ];
    }
}
