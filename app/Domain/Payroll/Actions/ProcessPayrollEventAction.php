<?php

namespace App\Domain\Payroll\Actions;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Employees\Models\Employee;
use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Enums\PayrollEventType;
use App\Domain\Payroll\Exceptions\PayrollEventProcessingException;
use App\Domain\Payroll\Exceptions\PayrollEventStateException;
use App\Domain\Payroll\Models\PayrollEvent;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPayrollEventAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * @param  array{provider?: string, provider_event_id: string, event_type: string, payload: array<string, mixed>, occurred_at?: string|null}  $data
     */
    public function execute(array $data): PayrollEvent
    {
        $provider = $data['provider'] ?? 'mock_payroll';
        $payload = $data['payload'];
        $eventType = PayrollEventType::from($data['event_type']);

        $existingEvent = PayrollEvent::query()
            ->where('provider', $provider)
            ->where('provider_event_id', $data['provider_event_id'])
            ->first();

        if ($existingEvent !== null) {
            return $this->handleExistingEvent($existingEvent);
        }

        $event = $this->storeIncomingEvent($provider, $data['provider_event_id'], $eventType, $payload, $data['occurred_at'] ?? null);

        return $event;
    }

    public function processStoredEvent(PayrollEvent $event): PayrollEvent
    {
        $event = $event->refresh();

        try {
            return match ($event->event_type) {
                PayrollEventType::EmployeeOnboarded => $this->processEmployeeOnboarded($event),
                PayrollEventType::EmployeeStatusChanged => $this->processEmployeeStatusChanged($event),
                PayrollEventType::SalaryRunCompleted => $this->processSalaryRunCompleted($event),
            };
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => PayrollEventStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ])->save();

            return $event->refresh();
        }
    }

    private function handleExistingEvent(PayrollEvent $event): PayrollEvent
    {
        return match ($event->status) {
            PayrollEventStatus::Processed, PayrollEventStatus::Failed, PayrollEventStatus::Ignored => $event->refresh(),
            PayrollEventStatus::Processing => throw PayrollEventStateException::alreadyProcessing($event->id),
            PayrollEventStatus::Received => $event->refresh(),
        };
    }

    private function storeIncomingEvent(
        string $provider,
        string $providerEventId,
        PayrollEventType $eventType,
        array $payload,
        ?string $occurredAt,
    ): PayrollEvent {
        $eventData = [
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
            'event_type' => $eventType,
            'payroll_employee_id' => $this->employeeExternalReference($payload),
            'amount' => $payload['amount'] ?? null,
            'currency' => isset($payload['currency']) ? strtoupper((string) $payload['currency']) : null,
            'status' => PayrollEventStatus::Received,
            'payload' => $payload,
            'occurred_at' => $occurredAt,
        ];

        try {
            return PayrollEvent::query()->create($eventData);
        } catch (QueryException) {
            return PayrollEvent::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $providerEventId)
                ->firstOrFail();
        }
    }

    private function processEmployeeOnboarded(PayrollEvent $event): PayrollEvent
    {
        return DB::transaction(function () use ($event): PayrollEvent {
            $event = $this->markProcessing($event);
            $employeePayload = $event->payload['employee'];

            $employee = Employee::query()->updateOrCreate(
                ['external_reference' => $employeePayload['external_reference']],
                [
                    'name' => $employeePayload['name'],
                    'email' => $employeePayload['email'],
                    'status' => $employeePayload['status'] ?? EmployeeStatus::Active->value,
                ],
            );

            return $this->markProcessed($event, $employee);
        });
    }

    private function processEmployeeStatusChanged(PayrollEvent $event): PayrollEvent
    {
        return DB::transaction(function () use ($event): PayrollEvent {
            $event = $this->markProcessing($event);
            $employee = $this->findEmployee((string) $event->payload['employee_external_reference']);

            $employee->forceFill([
                'status' => $event->payload['status'],
            ])->save();

            return $this->markProcessed($event, $employee);
        });
    }

    private function processSalaryRunCompleted(PayrollEvent $event): PayrollEvent
    {
        $event->forceFill(['status' => PayrollEventStatus::Processing])->save();

        $payload = $event->payload;
        $employee = $this->findEmployee((string) $payload['employee_external_reference']);
        $currency = strtoupper((string) $payload['currency']);
        $wallet = $this->findOrCreateSalaryWallet($employee, $currency);
        $period = (string) $payload['period'];
        $idempotencyKey = "payroll:{$event->provider_event_id}:{$employee->external_reference}:{$period}";

        $this->ledger->credit(
            wallet: $wallet,
            amount: (string) $payload['amount'],
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            type: WalletLedgerEntryType::PayrollCredit,
            source: $event,
            reason: "Payroll salary run completed for {$period}.",
            reference: $event->provider_event_id,
            metadata: [
                'provider' => $event->provider,
                'period' => $period,
            ],
        );

        $event->forceFill([
            'employee_id' => $employee->id,
            'wallet_id' => $wallet->id,
            'status' => PayrollEventStatus::Processed,
            'processed_at' => now(),
            'failure_reason' => null,
        ])->save();

        return $event->refresh();
    }

    private function markProcessing(PayrollEvent $event): PayrollEvent
    {
        $event->forceFill(['status' => PayrollEventStatus::Processing])->save();

        return $event;
    }

    private function markProcessed(PayrollEvent $event, Employee $employee): PayrollEvent
    {
        $event->forceFill([
            'employee_id' => $employee->id,
            'status' => PayrollEventStatus::Processed,
            'processed_at' => now(),
            'failure_reason' => null,
        ])->save();

        return $event->refresh();
    }

    private function findEmployee(string $externalReference): Employee
    {
        $employee = Employee::query()
            ->where('external_reference', $externalReference)
            ->first();

        if ($employee === null) {
            throw new PayrollEventProcessingException("Employee [{$externalReference}] was not found.");
        }

        return $employee;
    }

    private function findOrCreateSalaryWallet(Employee $employee, string $currency): Wallet
    {
        return $employee->wallets()->firstOrCreate(
            [
                'type' => WalletType::Salary->value,
                'currency' => $currency,
            ],
            [
                'available_balance' => '0.0000',
                'reserved_balance' => '0.0000',
                'status' => WalletStatus::Active->value,
            ],
        );
    }

    private function employeeExternalReference(array $payload): ?string
    {
        if (isset($payload['employee']['external_reference'])) {
            return (string) $payload['employee']['external_reference'];
        }

        if (isset($payload['employee_external_reference'])) {
            return (string) $payload['employee_external_reference'];
        }

        return null;
    }
}
