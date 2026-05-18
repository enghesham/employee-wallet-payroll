<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankPaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'withdrawal_request_id' => $this->withdrawal_request_id,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'status' => $this->status->value,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'sent_at' => $this->sent_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
