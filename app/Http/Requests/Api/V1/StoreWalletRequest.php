<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'type' => ['required', 'string', Rule::enum(WalletType::class)],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::unique('wallets', 'currency')
                    ->where('employee_id', $employee->id)
                    ->where('type', (string) $this->input('type')),
            ],
            'status' => ['sometimes', 'string', Rule::enum(WalletStatus::class)],
        ];
    }
}
