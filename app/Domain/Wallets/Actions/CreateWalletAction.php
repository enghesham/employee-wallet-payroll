<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Models\Wallet;

class CreateWalletAction
{
    /**
     * @param  array{type: string, currency: string, status?: string}  $data
     */
    public function execute(Employee $employee, array $data): Wallet
    {
        return $employee->wallets()->create([
            'status' => WalletStatus::Active->value,
            ...$data,
            'available_balance' => '0.0000',
            'reserved_balance' => '0.0000',
        ]);
    }
}
