<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWalletLedgerEntryRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::enum(WalletLedgerEntryType::class)],
            'source_type' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'amount_min' => ['sometimes', 'regex:/^\d+(\.\d{1,4})?$/'],
            'amount_max' => ['sometimes', 'regex:/^\d+(\.\d{1,4})?$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    $this->filled('amount_min')
                    && $this->filled('amount_max')
                    && bccomp((string) $this->input('amount_min'), (string) $this->input('amount_max'), 4) === 1
                ) {
                    $validator->errors()->add('amount_min', 'The amount_min must be less than or equal to amount_max.');
                }
            },
        ];
    }
}
