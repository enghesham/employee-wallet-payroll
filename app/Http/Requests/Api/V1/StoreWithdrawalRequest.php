<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency')),
            'idempotency_key' => $this->header('Idempotency-Key') ?: $this->input('idempotency_key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->filled('amount') && bccomp((string) $this->input('amount'), '0', 4) <= 0) {
                    $validator->errors()->add('amount', 'The amount must be greater than zero.');
                }
            },
        ];
    }
}
