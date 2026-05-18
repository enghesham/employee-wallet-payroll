<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_event_id' => $this->provider_event_id,
            'event_type' => $this->event_type->value,
            'payroll_employee_id' => $this->payroll_employee_id,
            'employee_id' => $this->employee_id,
            'wallet_id' => $this->wallet_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'payload' => $this->payload,
            'failure_reason' => $this->failure_reason,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
