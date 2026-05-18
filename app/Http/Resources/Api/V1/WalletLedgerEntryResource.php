<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletLedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'employee_id' => $this->employee_id,
            'what_happened' => $this->type->value,
            'when' => $this->created_at?->toISOString(),
            'why' => $this->reason,
            'source' => [
                'type' => $this->source_type,
                'id' => $this->source_id,
            ],
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'balances' => [
                'available_before' => $this->available_balance_before,
                'available_after' => $this->available_balance_after,
                'reserved_before' => $this->reserved_balance_before,
                'reserved_after' => $this->reserved_balance_after,
            ],
            'status' => $this->status->value,
            'reference' => $this->reference,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
        ];
    }
}
