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
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_onboarded_event_creates_or_updates_employee(): void
    {
        $response = $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
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

        $updateResponse = $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
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

        $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', $payload)->assertAccepted();
        $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', $payload)->assertAccepted();

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

        $response = $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
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

        $response = $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
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
        $response = $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
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

    public function test_payroll_event_requires_provider_token(): void
    {
        $this->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'payroll-token-required',
            'event_type' => PayrollEventType::EmployeeOnboarded->value,
            'payload' => [
                'employee' => [
                    'external_reference' => 'payroll_token_employee',
                    'name' => 'Token Required',
                    'email' => 'token.required@example.test',
                    'status' => EmployeeStatus::Active->value,
                ],
            ],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid provider token.');
    }

    public function test_failed_payroll_event_can_be_retried_successfully(): void
    {
        $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'retry-salary-after-employee-created',
            'event_type' => PayrollEventType::SalaryRunCompleted->value,
            'payload' => [
                'employee_external_reference' => 'retry_emp_1',
                'period' => '2026-05',
                'amount' => '1000.0000',
                'currency' => 'USD',
            ],
        ])
            ->assertAccepted()
            ->assertJsonPath('data.status', PayrollEventStatus::Failed->value);

        $event = PayrollEvent::query()->firstOrFail();
        Employee::factory()->create(['external_reference' => 'retry_emp_1']);

        $response = $this
            ->withToken('local-payroll-token')
            ->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', PayrollEventStatus::Processed->value)
            ->assertJsonPath('data.failure_reason', null);

        $wallet = Wallet::query()->firstOrFail();

        $this->assertSame('1000.0000', $wallet->available_balance);
        $this->assertSame(1, WalletLedgerEntry::query()->count());
    }

    public function test_processed_payroll_event_cannot_be_retried(): void
    {
        Employee::factory()->create(['external_reference' => 'processed_retry_emp']);

        $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', [
            'provider_event_id' => 'processed-retry-event',
            'event_type' => PayrollEventType::SalaryRunCompleted->value,
            'payload' => [
                'employee_external_reference' => 'processed_retry_emp',
                'period' => '2026-05',
                'amount' => '500.0000',
                'currency' => 'USD',
            ],
        ])->assertAccepted();

        $event = PayrollEvent::query()->firstOrFail();

        $this
            ->withToken('local-payroll-token')
            ->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertConflict()
            ->assertJsonPath('message', "Payroll event [{$event->id}] cannot be retried from status [processed].");
    }

    public function test_processing_payroll_event_cannot_be_retried(): void
    {
        $event = PayrollEvent::query()->create([
            'provider' => 'mock_payroll',
            'provider_event_id' => 'processing-retry-event',
            'event_type' => PayrollEventType::SalaryRunCompleted,
            'payroll_employee_id' => 'processing_retry_emp',
            'amount' => '500.0000',
            'currency' => 'USD',
            'status' => PayrollEventStatus::Processing,
            'payload' => [
                'employee_external_reference' => 'processing_retry_emp',
                'period' => '2026-05',
                'amount' => '500.0000',
                'currency' => 'USD',
            ],
        ]);

        $this
            ->withToken('local-payroll-token')
            ->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertConflict()
            ->assertJsonPath('message', "Payroll event [{$event->id}] cannot be retried from status [processing].");
    }

    public function test_retry_failure_keeps_event_failed_with_updated_failure_reason(): void
    {
        $event = PayrollEvent::query()->create([
            'provider' => 'mock_payroll',
            'provider_event_id' => 'retry-still-fails-event',
            'event_type' => PayrollEventType::SalaryRunCompleted,
            'payroll_employee_id' => 'still_missing_emp',
            'amount' => '500.0000',
            'currency' => 'USD',
            'status' => PayrollEventStatus::Failed,
            'failure_reason' => 'Old failure reason.',
            'payload' => [
                'employee_external_reference' => 'still_missing_emp',
                'period' => '2026-05',
                'amount' => '500.0000',
                'currency' => 'USD',
            ],
        ]);

        $response = $this
            ->withToken('local-payroll-token')
            ->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', PayrollEventStatus::Failed->value);

        $event->refresh();

        $this->assertSame(PayrollEventStatus::Failed, $event->status);
        $this->assertStringContainsString('still_missing_emp', $event->failure_reason);
        $this->assertNotSame('Old failure reason.', $event->failure_reason);
    }

    public function test_retried_salary_event_does_not_double_credit_if_partial_work_already_happened(): void
    {
        $employee = Employee::factory()->create(['external_reference' => 'partial_retry_emp']);
        $wallet = Wallet::factory()->for($employee)->create([
            'type' => WalletType::Salary,
            'currency' => 'USD',
            'available_balance' => '0.0000',
        ]);

        $event = PayrollEvent::query()->create([
            'provider' => 'mock_payroll',
            'provider_event_id' => 'partial-retry-salary-event',
            'event_type' => PayrollEventType::SalaryRunCompleted,
            'payroll_employee_id' => 'partial_retry_emp',
            'employee_id' => $employee->id,
            'wallet_id' => $wallet->id,
            'amount' => '750.0000',
            'currency' => 'USD',
            'status' => PayrollEventStatus::Failed,
            'failure_reason' => 'Simulated failure after ledger credit.',
            'payload' => [
                'employee_external_reference' => 'partial_retry_emp',
                'period' => '2026-05',
                'amount' => '750.0000',
                'currency' => 'USD',
            ],
        ]);

        app(WalletLedgerService::class)->credit(
            wallet: $wallet,
            amount: '750.0000',
            currency: 'USD',
            idempotencyKey: 'payroll:partial-retry-salary-event:partial_retry_emp:2026-05',
            type: WalletLedgerEntryType::PayrollCredit,
            source: $event,
            reason: 'Payroll salary run completed for 2026-05.',
            reference: $event->provider_event_id,
            metadata: [
                'provider' => 'mock_payroll',
                'period' => '2026-05',
            ],
        );

        $this
            ->withToken('local-payroll-token')
            ->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollEventStatus::Processed->value);

        $wallet->refresh();

        $this->assertSame('750.0000', $wallet->available_balance);
        $this->assertSame(1, WalletLedgerEntry::query()->count());
    }
}
