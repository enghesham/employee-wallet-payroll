<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Banking\Models\WithdrawalRequest;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_history_returns_paginated_transactions_newest_first(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '0.0000']);
        $ledger = app(WalletLedgerService::class);

        $oldest = $ledger->credit($wallet, '10.0000', 'USD', 'history-credit-1', reference: 'oldest');
        $middle = $ledger->credit($wallet->fresh(), '20.0000', 'USD', 'history-credit-2', reference: 'middle');
        $newest = $ledger->debit($wallet->fresh(), '5.0000', 'USD', 'history-debit-1', reference: 'newest');

        $oldest->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
        $middle->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        $newest->forceFill(['created_at' => now(), 'updated_at' => now()])->save();

        $response = $this->getJson("/api/v1/wallets/{$wallet->id}/ledger-entries?per_page=2");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reference', 'newest')
            ->assertJsonPath('data.0.what_happened', WalletLedgerEntryType::ManualDebit->value)
            ->assertJsonPath('data.0.balances.available_before', '30.0000')
            ->assertJsonPath('data.0.balances.available_after', '25.0000')
            ->assertJsonPath('data.1.reference', 'middle')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_wallet_history_filters_work(): void
    {
        $wallet = Wallet::factory()->create(['available_balance' => '100.0000']);
        $ledger = app(WalletLedgerService::class);
        $withdrawal = WithdrawalRequest::factory()->for($wallet)->create([
            'employee_id' => $wallet->employee_id,
            'amount' => '25.0000',
            'currency' => 'USD',
        ]);

        $credit = $ledger->credit($wallet, '40.0000', 'USD', 'filter-credit-key', reference: 'filter-credit');
        $reserve = $ledger->reserve(
            wallet: $wallet->fresh(),
            amount: '25.0000',
            currency: 'USD',
            idempotencyKey: 'filter-reserve-key',
            source: $withdrawal,
            reference: 'filter-reserve',
        );

        $credit->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();
        $reserve->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $query = http_build_query([
            'type' => WalletLedgerEntryType::WithdrawalReserve->value,
            'source_type' => WithdrawalRequest::class,
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
            'amount_min' => '20.0000',
            'amount_max' => '30.0000',
        ]);

        $response = $this->getJson("/api/v1/wallets/{$wallet->id}/ledger-entries?{$query}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'filter-reserve')
            ->assertJsonPath('data.0.source.type', WithdrawalRequest::class)
            ->assertJsonPath('data.0.amount', '25.0000');
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'employee-wallet-payroll',
            ]);
    }
}
