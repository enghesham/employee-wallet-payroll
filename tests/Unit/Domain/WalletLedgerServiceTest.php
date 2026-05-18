<?php

namespace Tests\Unit\Domain;

use App\Domain\Shared\Models\IdempotencyRecord;
use App\Domain\Wallets\Enums\WalletLedgerEntryDirection;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\CurrencyMismatchException;
use App\Domain\Wallets\Exceptions\InsufficientFundsException;
use App\Domain\Wallets\Exceptions\WalletTransferNotAllowedException;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WalletLedgerService::class);
    }

    public function test_credit_increases_available_balance_and_records_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '10.0000']);

        $entry = $this->service->credit($wallet, '25.5000', 'USD', 'credit-key-1');

        $wallet->refresh();

        $this->assertSame('35.5000', $wallet->available_balance);
        $this->assertSame('0.0000', $wallet->reserved_balance);
        $this->assertSame(WalletLedgerEntryType::ManualCredit, $entry->type);
        $this->assertSame(WalletLedgerEntryDirection::Credit, $entry->direction);
        $this->assertSame('10.0000', $entry->available_balance_before);
        $this->assertSame('35.5000', $entry->available_balance_after);
    }

    public function test_debit_decreases_available_balance_and_records_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '100.0000']);

        $entry = $this->service->debit($wallet, '40.0000', 'USD', 'debit-key-1');

        $wallet->refresh();

        $this->assertSame('60.0000', $wallet->available_balance);
        $this->assertSame(WalletLedgerEntryType::ManualDebit, $entry->type);
        $this->assertSame(WalletLedgerEntryDirection::Debit, $entry->direction);
        $this->assertSame('100.0000', $entry->available_balance_before);
        $this->assertSame('60.0000', $entry->available_balance_after);
    }

    public function test_debit_fails_when_available_balance_is_insufficient(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '15.0000']);

        $this->expectException(InsufficientFundsException::class);

        try {
            $this->service->debit($wallet, '20.0000', 'USD', 'debit-key-insufficient');
        } finally {
            $wallet->refresh();

            $this->assertSame('15.0000', $wallet->available_balance);
            $this->assertDatabaseCount('wallet_ledger_entries', 0);
            $this->assertDatabaseMissing('idempotency_records', [
                'scope' => 'wallet.debit',
                'key' => 'debit-key-insufficient',
            ]);
        }
    }

    public function test_transfer_creates_debit_and_credit_entries(): void
    {
        $fromWallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'currency' => 'USD',
        ]);
        $toWallet = Wallet::factory()->for($fromWallet->employee)->create([
            'type' => WalletType::Savings,
            'available_balance' => '10.0000',
            'currency' => 'USD',
        ]);

        $entries = $this->service->transfer($fromWallet, $toWallet, '30.0000', 'USD', 'transfer-key-1');

        $fromWallet->refresh();
        $toWallet->refresh();

        $this->assertSame('70.0000', $fromWallet->available_balance);
        $this->assertSame('40.0000', $toWallet->available_balance);
        $this->assertSame(WalletLedgerEntryType::TransferOut, $entries['debit']->type);
        $this->assertSame(WalletLedgerEntryType::TransferIn, $entries['credit']->type);
        $this->assertSame($fromWallet->id, $entries['debit']->wallet_id);
        $this->assertSame($toWallet->id, $entries['credit']->wallet_id);
        $this->assertDatabaseCount('wallet_ledger_entries', 2);
    }

    public function test_transfer_rejects_different_currency_wallets(): void
    {
        $fromWallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'currency' => 'USD',
        ]);
        $toWallet = Wallet::factory()->for($fromWallet->employee)->create([
            'type' => WalletType::Savings,
            'available_balance' => '10.0000',
            'currency' => 'EUR',
        ]);

        $this->expectException(CurrencyMismatchException::class);

        try {
            $this->service->transfer($fromWallet, $toWallet, '30.0000', 'USD', 'transfer-currency-mismatch');
        } finally {
            $fromWallet->refresh();
            $toWallet->refresh();

            $this->assertSame('100.0000', $fromWallet->available_balance);
            $this->assertSame('10.0000', $toWallet->available_balance);
            $this->assertDatabaseCount('wallet_ledger_entries', 0);
        }
    }

    public function test_transfer_rejects_wallets_owned_by_different_employees(): void
    {
        $fromWallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'currency' => 'USD',
        ]);
        $toWallet = Wallet::factory()->create([
            'available_balance' => '10.0000',
            'currency' => 'USD',
        ]);

        $this->expectException(WalletTransferNotAllowedException::class);

        try {
            $this->service->transfer($fromWallet, $toWallet, '30.0000', 'USD', 'transfer-different-employee');
        } finally {
            $fromWallet->refresh();
            $toWallet->refresh();

            $this->assertSame('100.0000', $fromWallet->available_balance);
            $this->assertSame('10.0000', $toWallet->available_balance);
            $this->assertDatabaseCount('wallet_ledger_entries', 0);
        }
    }

    public function test_duplicate_idempotency_key_does_not_apply_operation_twice(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '10.0000']);

        $firstEntry = $this->service->credit($wallet, '25.0000', 'USD', 'duplicate-credit-key');
        $secondEntry = $this->service->credit($wallet->fresh(), '25.0000', 'USD', 'duplicate-credit-key');

        $wallet->refresh();

        $this->assertSame($firstEntry->id, $secondEntry->id);
        $this->assertSame('35.0000', $wallet->available_balance);
        $this->assertDatabaseCount('wallet_ledger_entries', 1);
        $this->assertDatabaseCount('idempotency_records', 1);
    }

    public function test_reserved_funds_reduce_available_balance_and_are_not_spendable(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '100.0000']);

        $entry = $this->service->reserve($wallet, '80.0000', 'USD', 'reserve-key-1');

        $wallet->refresh();

        $this->assertSame('20.0000', $wallet->available_balance);
        $this->assertSame('80.0000', $wallet->reserved_balance);
        $this->assertSame(WalletLedgerEntryType::WithdrawalReserve, $entry->type);

        $this->expectException(InsufficientFundsException::class);

        $this->service->debit($wallet->fresh(), '30.0000', 'USD', 'debit-after-reserve-key');
    }

    public function test_failed_operation_rolls_back_fully(): void
    {
        $wallet = Wallet::factory()->create([
            'available_balance' => '50.0000',
            'reserved_balance' => '0.0000',
        ]);

        try {
            $this->service->release($wallet, '10.0000', 'USD', 'release-without-reserve-key');
            $this->fail('Expected insufficient funds exception.');
        } catch (InsufficientFundsException) {
            $wallet->refresh();

            $this->assertSame('50.0000', $wallet->available_balance);
            $this->assertSame('0.0000', $wallet->reserved_balance);
            $this->assertSame(0, WalletLedgerEntry::query()->count());
            $this->assertSame(0, IdempotencyRecord::query()->count());
        }
    }
}
