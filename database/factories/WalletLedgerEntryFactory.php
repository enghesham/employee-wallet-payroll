<?php

namespace Database\Factories;

use App\Domain\Wallets\Enums\WalletLedgerEntryDirection;
use App\Domain\Wallets\Enums\WalletLedgerEntryStatus;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WalletLedgerEntry> */
class WalletLedgerEntryFactory extends Factory
{
    protected $model = WalletLedgerEntry::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'employee_id' => fn (array $attributes) => Wallet::query()->find($attributes['wallet_id'])->employee_id,
            'type' => WalletLedgerEntryType::ManualCredit,
            'direction' => WalletLedgerEntryDirection::Credit,
            'amount' => '100.0000',
            'available_balance_before' => '0.0000',
            'available_balance_after' => '100.0000',
            'reserved_balance_before' => '0.0000',
            'reserved_balance_after' => '0.0000',
            'currency' => 'USD',
            'status' => WalletLedgerEntryStatus::Posted,
            'reason' => 'Factory generated ledger entry.',
            'reference' => 'ledger_'.fake()->unique()->uuid(),
            'idempotency_key' => fake()->unique()->uuid(),
            'metadata' => [],
        ];
    }
}
