<?php

namespace App\Domain\Wallets\Models;

use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Enums\WalletLedgerEntryDirection;
use App\Domain\Wallets\Enums\WalletLedgerEntryStatus;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use Database\Factories\WalletLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletLedgerEntry extends Model
{
    /** @use HasFactory<WalletLedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'wallet_id',
        'type',
        'direction',
        'amount',
        'available_balance_before',
        'available_balance_after',
        'reserved_balance_before',
        'reserved_balance_after',
        'currency',
        'source_type',
        'source_id',
        'status',
        'reason',
        'reference',
        'idempotency_key',
        'metadata',
    ];

    protected static function newFactory(): WalletLedgerEntryFactory
    {
        return WalletLedgerEntryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => WalletLedgerEntryType::class,
            'direction' => WalletLedgerEntryDirection::class,
            'amount' => 'decimal:4',
            'available_balance_before' => 'decimal:4',
            'available_balance_after' => 'decimal:4',
            'reserved_balance_before' => 'decimal:4',
            'reserved_balance_after' => 'decimal:4',
            'status' => WalletLedgerEntryStatus::class,
            'metadata' => 'array',
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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
