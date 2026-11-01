<?php

namespace App\Http\Requests;

use App\Concerns\ServiceRequestValidationRules;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequestRequest extends FormRequest
{
    use ServiceRequestValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceRequest::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The status is not accepted: new requests always start as open.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => $this->titleRules(),
            'description' => $this->descriptionRules(),
            'priority' => ['sometimes', ...$this->priorityRules()],
        ];
    }
}
