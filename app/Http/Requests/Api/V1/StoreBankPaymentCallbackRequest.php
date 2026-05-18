<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankPaymentCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'string', 'max:100'],
            'provider_reference' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['succeeded', 'failed'])],
            'occurred_at' => ['required', 'date'],
            'failure_reason' => ['required_if:status,failed', 'nullable', 'string', 'max:1000'],
            'payload' => ['sometimes', 'array'],
        ];
    }
}
