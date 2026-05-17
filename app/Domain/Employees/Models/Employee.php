<?php

namespace App\Domain\Employees\Models;

use App\Domain\Banking\Models\WithdrawalRequest;
use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Payroll\Models\PayrollEvent;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'external_reference',
        'status',
    ];

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
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
