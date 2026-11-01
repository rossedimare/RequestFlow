<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexServiceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ServiceRequest::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the query string.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
        ];
    }
}
