<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payroll\Actions\ProcessPayrollEventAction;
use App\Domain\Payroll\Actions\RetryPayrollEventAction;
use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Models\PayrollEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePayrollEventRequest;
use App\Http\Resources\Api\V1\PayrollEventResource;
use App\Jobs\ProcessPayrollEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PayrollEventController extends Controller
{
    public function store(StorePayrollEventRequest $request, ProcessPayrollEventAction $action): JsonResponse
    {
        $event = $action->execute($request->validated());

        if ($event->status === PayrollEventStatus::Received) {
            ProcessPayrollEventJob::dispatch($event->id);
            $event->refresh();
        }

        return (new PayrollEventResource($event))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function retry(PayrollEvent $payrollEvent, RetryPayrollEventAction $action): PayrollEventResource
    {
        return new PayrollEventResource($action->execute($payrollEvent));
    }
}
