<?php

namespace App\Domain\Wallets\Models;

use App\Domain\Banking\Models\WithdrawalRequest;
use App\Domain\Employees\Models\Employee;
use App\Domain\Payroll\Models\PayrollEvent;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'currency',
        'available_balance',
        'reserved_balance',
        'status',
    ];

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
            'available_balance' => 'decimal:4',
            'reserved_balance' => 'decimal:4',
            'status' => WalletStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    public function payrollEvents(): HasMany
    {
        return $this->hasMany(PayrollEvent::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
