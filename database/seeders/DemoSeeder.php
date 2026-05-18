<?php

namespace Database\Seeders;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(WalletLedgerService $ledger): void
    {
        $employee = Employee::query()->updateOrCreate(
            ['external_reference' => 'EMP-DEMO-001'],
            [
                'name' => 'Demo Employee',
                'email' => 'demo.employee@example.com',
                'status' => EmployeeStatus::Active,
            ],
        );

        $salaryWallet = Wallet::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'type' => WalletType::Salary->value,
                'currency' => 'USD',
            ],
            [
                'available_balance' => '0.0000',
                'reserved_balance' => '0.0000',
                'status' => WalletStatus::Active,
            ],
        );

        $savingsWallet = Wallet::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'type' => WalletType::Savings->value,
                'currency' => 'USD',
            ],
            [
                'available_balance' => '0.0000',
                'reserved_balance' => '0.0000',
                'status' => WalletStatus::Active,
            ],
        );

        $ledger->credit(
            wallet: $salaryWallet,
            amount: '1000.00',
            currency: 'USD',
            idempotencyKey: 'seed:demo:salary-wallet:opening-credit',
            type: WalletLedgerEntryType::ManualCredit,
            reason: 'Demo opening balance',
            reference: 'seed-demo-salary-wallet-opening-credit',
            metadata: ['seed' => 'demo'],
        );

        $ledger->credit(
            wallet: $savingsWallet,
            amount: '200.00',
            currency: 'USD',
            idempotencyKey: 'seed:demo:savings-wallet:opening-credit',
            type: WalletLedgerEntryType::ManualCredit,
            reason: 'Demo opening balance',
            reference: 'seed-demo-savings-wallet-opening-credit',
            metadata: ['seed' => 'demo'],
        );
    }
}
