<?php

namespace App\Domain\Payroll\Actions;

use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Exceptions\PayrollEventStateException;
use App\Domain\Payroll\Models\PayrollEvent;
use Illuminate\Support\Facades\DB;

class RetryPayrollEventAction
{
    public function __construct(private readonly ProcessPayrollEventAction $processor) {}

    public function execute(PayrollEvent $payrollEvent): PayrollEvent
    {
        $event = DB::transaction(function () use ($payrollEvent): PayrollEvent {
            /** @var PayrollEvent $event */
            $event = PayrollEvent::query()
                ->whereKey($payrollEvent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($event->status !== PayrollEventStatus::Failed) {
                throw PayrollEventStateException::cannotRetry($event->id, $event->status->value);
            }

            $event->forceFill([
                'status' => PayrollEventStatus::Processing,
                'failure_reason' => null,
            ])->save();

            return $event;
        });

        return $this->processor->processStoredEvent($event);
    }
}
