<?php

namespace App\Domain\Banking\Models;

use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Database\Factories\WithdrawalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WithdrawalRequest extends Model
{
    /** @use HasFactory<WithdrawalRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'wallet_id',
        'amount',
        'currency',
        'status',
        'reference',
        'idempotency_key',
        'metadata',
        'requested_at',
        'completed_at',
        'failure_reason',
    ];

    protected static function newFactory(): WithdrawalRequestFactory
    {
        return WithdrawalRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'status' => WithdrawalRequestStatus::class,
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bankPaymentRequests(): HasMany
    {
        return $this->hasMany(BankPaymentRequest::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(WalletLedgerEntry::class, 'source');
    }
}
