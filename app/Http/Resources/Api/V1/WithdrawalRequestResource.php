<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'wallet_id' => $this->wallet_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
            'bank_payment_requests' => BankPaymentRequestResource::collection($this->whenLoaded('bankPaymentRequests')),
            'requested_at' => $this->requested_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
