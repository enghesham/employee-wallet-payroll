<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Employees\Models\Employee;
use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Enums\PayrollEventType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Database\Factories\PayrollEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollEvent extends Model
{
    /** @use HasFactory<PayrollEventFactory> */
    use HasFactory;

    protected $fillable = [
        'payroll_batch_id',
        'employee_id',
        'wallet_id',
        'provider',
        'provider_event_id',
        'event_type',
        'payroll_employee_id',
        'amount',
        'currency',
        'status',
        'payload',
        'occurred_at',
        'processed_at',
        'failure_reason',
    ];

    protected static function newFactory(): PayrollEventFactory
    {
        return PayrollEventFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'event_type' => PayrollEventType::class,
            'status' => PayrollEventStatus::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(WalletLedgerEntry::class, 'source');
    }
}
