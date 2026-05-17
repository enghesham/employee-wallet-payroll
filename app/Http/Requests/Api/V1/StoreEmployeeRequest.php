<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('employees', 'email')],
            'external_reference' => ['required', 'string', 'max:255', Rule::unique('employees', 'external_reference')],
            'status' => ['sometimes', 'string', Rule::enum(EmployeeStatus::class)],
        ];
    }
}
