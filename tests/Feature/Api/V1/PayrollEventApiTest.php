<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Employees\Models\Employee;
use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Enums\PayrollEventType;
use App\Domain\Payroll\Models\PayrollEvent;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_onboarded_event_creates_or_updates_employee(): void
    {
        $response = $this->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'payroll-event-onboard-1',
            'event_type' => PayrollEventType::EmployeeOnboarded->value,
            'payload' => [
                'employee' => [
                    'external_reference' => 'payroll_emp_2001',
                    'name' => 'Maya Payroll',
                    'email' => 'maya.payroll@example.test',
                    'status' => EmployeeStatus::Active->value,
                ],
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', PayrollEventStatus::Processed->value)
            ->assertJsonPath('data.payroll_employee_id', 'payroll_emp_2001');

        $this->assertDatabaseHas('employees', [
            'external_reference' => 'payroll_emp_2001',
            'name' => 'Maya Payroll',
            'email' => 'maya.payroll@example.test',
            'status' => EmployeeStatus::Active->value,
        ]);

        $updateResponse = $this->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'payroll-event-onboard-2',
            'event_type' => PayrollEventType::EmployeeOnboarded->value,
            'payload' => [
                'employee' => [
                    'external_reference' => 'payroll_emp_2001',
                    'name' => 'Maya Updated',
                    'email' => 'maya.updated@example.test',
                    'status' => EmployeeStatus::Suspended->value,
                ],
            ],
        ]);

        $updateResponse
            ->assertAccepted()
            ->assertJsonPath('data.status', PayrollEventStatus::Processed->value);

        $this->assertDatabaseHas('employees', [
            'external_reference' => 'payroll_emp_2001',
            'name' => 'Maya Updated',
            'email' => 'maya.updated@example.test',
            'status' => EmployeeStatus::Suspended->value,
        ]);
    }

    public function test_duplicate_event_does_not_process_twice(): void
    {
        $employee = Employee::factory()->create(['external_reference' => 'payroll_emp_3001']);
        $wallet = Wallet::factory()->for($employee)->create([
            'type' => WalletType::Salary,
            'currency' => 'USD',
            'available_balance' => '0.0000',
        ]);

        $payload = [
            'provider_event_id' => 'salary-run-duplicate-1',
            'event_type' => PayrollEventType::SalaryRunCompleted->value,
            'payload' => [
                'employee_external_reference' => 'payroll_emp_3001',
                'period' => '2026-05',
                'amount' => '1200.0000',
                'currency' => 'USD',
            ],
        ];

        $this->postJson('/api/v1/payroll/events', $payload)->assertAccepted();
        $this->postJson('/api/v1/payroll/events', $payload)->assertAccepted();

        $wallet->refresh();

        $this->assertSame('1200.0000', $wallet->available_balance);
        $this->assertSame(1, PayrollEvent::query()->count());
        $this->assertSame(1, WalletLedgerEntry::query()->count());
    }

    public function test_employee_status_changed_event_updates_employee(): void
    {
        $employee = Employee::factory()->create([
            'external_reference' => 'payroll_emp_status_1',
            'status' => EmployeeStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'payroll-status-change-1',
            'event_type' => PayrollEventType::EmployeeStatusChanged->value,
            'payload' => [
                'employee_external_reference' => 'payroll_emp_status_1',
                'status' => EmployeeStatus::Suspended->value,
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', PayrollEventStatus::Processed->value)
            ->assertJsonPath('data.employee_id', $employee->id);

        $employee->refresh();

        $this->assertSame(EmployeeStatus::Suspended, $employee->status);
    }

    public function test_salary_run_completed_credits_employee_salary_wallet(): void
    {
        Employee::factory()->create(['external_reference' => 'payroll_emp_4001']);

        $response = $this->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'salary-run-4001-2026-05',
            'event_type' => PayrollEventType::SalaryRunCompleted->value,
            'payload' => [
                'employee_external_reference' => 'payroll_emp_4001',
                'period' => '2026-05',
                'amount' => '2750.5000',
                'currency' => 'USD',
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', PayrollEventStatus::Processed->value)
            ->assertJsonPath('data.amount', '2750.5000')
            ->assertJsonPath('data.currency', 'USD');

        $wallet = Wallet::query()->firstOrFail();
        $entry = WalletLedgerEntry::query()->firstOrFail();

        $this->assertSame(WalletType::Salary, $wallet->type);
        $this->assertSame('2750.5000', $wallet->available_balance);
        $this->assertSame(WalletLedgerEntryType::PayrollCredit, $entry->type);
        $this->assertSame('0.0000', $entry->available_balance_before);
        $this->assertSame('2750.5000', $entry->available_balance_after);
        $this->assertSame('payroll:salary-run-4001-2026-05:payroll_emp_4001:2026-05:credit', $entry->idempotency_key);
    }

    public function test_failed_payroll_event_is_stored_with_failure_reason(): void
    {
        $response = $this->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'salary-run-missing-employee',
            'event_type' => PayrollEventType::SalaryRunCompleted->value,
            'payload' => [
                'employee_external_reference' => 'missing_employee',
                'period' => '2026-05',
                'amount' => '900.0000',
                'currency' => 'USD',
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', PayrollEventStatus::Failed->value);

        $event = PayrollEvent::query()->firstOrFail();

        $this->assertSame(PayrollEventStatus::Failed, $event->status);
        $this->assertStringContainsString('missing_employee', $event->failure_reason);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }
}
