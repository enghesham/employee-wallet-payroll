<?php

namespace Database\Factories;

use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Wallet> */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => WalletType::Salary,
            'currency' => 'USD',
            'available_balance' => '0.0000',
            'reserved_balance' => '0.0000',
            'status' => WalletStatus::Active,
        ];
    }
}
