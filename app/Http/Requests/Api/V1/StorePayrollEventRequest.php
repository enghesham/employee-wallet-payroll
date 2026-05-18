<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Payroll\Enums\PayrollEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload', []);

        if (is_array($payload) && isset($payload['currency'])) {
            $payload['currency'] = strtoupper((string) $payload['currency']);
            $this->merge(['payload' => $payload]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'string', 'max:100'],
            'provider_event_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', Rule::enum(PayrollEventType::class)],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'payload' => ['required', 'array'],

            'payload.employee' => ['required_if:event_type,'.PayrollEventType::EmployeeOnboarded->value, 'array'],
            'payload.employee.external_reference' => ['required_if:event_type,'.PayrollEventType::EmployeeOnboarded->value, 'string', 'max:255'],
            'payload.employee.name' => ['required_if:event_type,'.PayrollEventType::EmployeeOnboarded->value, 'string', 'max:255'],
            'payload.employee.email' => ['required_if:event_type,'.PayrollEventType::EmployeeOnboarded->value, 'email:rfc', 'max:255'],
            'payload.employee.status' => ['sometimes', 'string', Rule::enum(EmployeeStatus::class)],

            'payload.employee_external_reference' => [
                Rule::requiredIf(fn () => in_array($this->input('event_type'), [
                    PayrollEventType::EmployeeStatusChanged->value,
                    PayrollEventType::SalaryRunCompleted->value,
                ], true)),
                'string',
                'max:255',
            ],
            'payload.status' => ['required_if:event_type,'.PayrollEventType::EmployeeStatusChanged->value, 'string', Rule::enum(EmployeeStatus::class)],

            'payload.period' => ['required_if:event_type,'.PayrollEventType::SalaryRunCompleted->value, 'string', 'max:50'],
            'payload.amount' => ['required_if:event_type,'.PayrollEventType::SalaryRunCompleted->value, 'regex:/^\d+(\.\d{1,4})?$/'],
            'payload.currency' => ['required_if:event_type,'.PayrollEventType::SalaryRunCompleted->value, 'string', 'size:3'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (
                    $this->input('event_type') === PayrollEventType::SalaryRunCompleted->value
                    && $this->filled('payload.amount')
                    && bccomp((string) $this->input('payload.amount'), '0', 4) <= 0
                ) {
                    $validator->errors()->add('payload.amount', 'The payload amount must be greater than zero.');
                }
            },
        ];
    }
}
