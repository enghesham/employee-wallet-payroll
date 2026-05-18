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
            'status' => ['required', 'string', Rule::in(['success', 'failed'])],
            'provider_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'failure_reason' => ['required_if:status,failed', 'nullable', 'string', 'max:1000'],
            'payload' => ['sometimes', 'array'],
        ];
    }
}
