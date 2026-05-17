<?php

namespace App\Domain\Banking\Models;

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use Database\Factories\BankPaymentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPaymentRequest extends Model
{
    /** @use HasFactory<BankPaymentRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'withdrawal_request_id',
        'provider',
        'provider_reference',
        'idempotency_key',
        'status',
        'request_payload',
        'response_payload',
        'sent_at',
        'confirmed_at',
        'failed_at',
        'failure_reason',
    ];

    protected static function newFactory(): BankPaymentRequestFactory
    {
        return BankPaymentRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => BankPaymentRequestStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class);
    }
}
