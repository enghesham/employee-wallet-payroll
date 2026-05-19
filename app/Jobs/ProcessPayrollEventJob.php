<?php

namespace App\Jobs;

use App\Domain\Payroll\Actions\ProcessPayrollEventAction;
use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Models\PayrollEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessPayrollEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $payrollEventId) {}

    public function handle(ProcessPayrollEventAction $processor): void
    {
        $event = DB::transaction(function (): ?PayrollEvent {
            $event = PayrollEvent::query()
                ->whereKey($this->payrollEventId)
                ->lockForUpdate()
                ->first();

            if ($event === null || $event->status !== PayrollEventStatus::Received) {
                return null;
            }

            $event->forceFill([
                'status' => PayrollEventStatus::Processing,
                'failure_reason' => null,
            ])->save();

            return $event;
        });

        if ($event === null) {
            return;
        }

        $processor->processStoredEvent($event);
    }
}
