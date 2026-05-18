<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTransferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_transfer_moves_funds_between_same_employee_wallets(): void
    {
        $fromWallet = Wallet::factory()->create([
            'type' => WalletType::Salary,
            'available_balance' => '500.0000',
            'currency' => 'USD',
        ]);
        $toWallet = Wallet::factory()->for($fromWallet->employee)->create([
            'type' => WalletType::Savings,
            'available_balance' => '25.0000',
            'currency' => 'USD',
        ]);

        $response = $this
            ->withHeader('Idempotency-Key', 'transfer-api-key-1')
            ->postJson("/api/v1/wallets/{$fromWallet->id}/transfers", [
                'to_wallet_id' => $toWallet->id,
                'amount' => '125.0000',
                'currency' => 'USD',
                'reason' => 'Move part of salary to savings.',
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.debit.what_happened', WalletLedgerEntryType::TransferOut->value)
            ->assertJsonPath('data.credit.what_happened', WalletLedgerEntryType::TransferIn->value)
            ->assertJsonPath('data.debit.amount', '125.0000')
            ->assertJsonPath('data.credit.amount', '125.0000');

        $fromWallet->refresh();
        $toWallet->refresh();

        $this->assertSame('375.0000', $fromWallet->available_balance);
        $this->assertSame('150.0000', $toWallet->available_balance);
        $this->assertSame(2, WalletLedgerEntry::query()->count());
    }

    public function test_wallet_transfer_rejects_wallets_owned_by_different_employees(): void
    {
        $fromWallet = Wallet::factory()->create([
            'available_balance' => '500.0000',
            'currency' => 'USD',
        ]);
        $toWallet = Wallet::factory()->create([
            'available_balance' => '25.0000',
            'currency' => 'USD',
        ]);

        $response = $this
            ->withHeader('Idempotency-Key', 'transfer-api-different-employee')
            ->postJson("/api/v1/wallets/{$fromWallet->id}/transfers", [
                'to_wallet_id' => $toWallet->id,
                'amount' => '125.0000',
                'currency' => 'USD',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Transfers are only allowed between wallets owned by the same employee.');

        $fromWallet->refresh();
        $toWallet->refresh();

        $this->assertSame('500.0000', $fromWallet->available_balance);
        $this->assertSame('25.0000', $toWallet->available_balance);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_duplicate_wallet_transfer_idempotency_key_does_not_transfer_twice(): void
    {
        $fromWallet = Wallet::factory()->create([
            'type' => WalletType::Salary,
            'available_balance' => '500.0000',
            'currency' => 'USD',
        ]);
        $toWallet = Wallet::factory()->for($fromWallet->employee)->create([
            'type' => WalletType::Savings,
            'available_balance' => '25.0000',
            'currency' => 'USD',
        ]);

        $payload = [
            'to_wallet_id' => $toWallet->id,
            'amount' => '125.0000',
            'currency' => 'USD',
        ];

        $this
            ->withHeader('Idempotency-Key', 'transfer-api-duplicate')
            ->postJson("/api/v1/wallets/{$fromWallet->id}/transfers", $payload)
            ->assertAccepted();

        $this
            ->withHeader('Idempotency-Key', 'transfer-api-duplicate')
            ->postJson("/api/v1/wallets/{$fromWallet->id}/transfers", $payload)
            ->assertAccepted();

        $fromWallet->refresh();
        $toWallet->refresh();

        $this->assertSame('375.0000', $fromWallet->available_balance);
        $this->assertSame('150.0000', $toWallet->available_balance);
        $this->assertSame(2, WalletLedgerEntry::query()->count());
    }
}
