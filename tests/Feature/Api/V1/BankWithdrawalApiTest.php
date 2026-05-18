<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use App\Domain\Banking\Models\BankPaymentRequest;
use App\Domain\Banking\Models\WithdrawalRequest;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankWithdrawalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_reserves_funds_and_creates_bank_payment_request(): void
    {
        $wallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'reserved_balance' => '0.0000',
            'currency' => 'USD',
        ]);

        $response = $this
            ->withHeader('Idempotency-Key', 'withdrawal-key-1')
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", [
                'amount' => '35.0000',
                'currency' => 'usd',
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', WithdrawalRequestStatus::PendingBankConfirmation->value)
            ->assertJsonPath('data.amount', '35.0000')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.bank_payment_requests.0.status', BankPaymentRequestStatus::Accepted->value);

        $wallet->refresh();

        $this->assertSame('65.0000', $wallet->available_balance);
        $this->assertSame('35.0000', $wallet->reserved_balance);
        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(1, BankPaymentRequest::query()->count());
        $this->assertDatabaseHas('wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => WalletLedgerEntryType::WithdrawalReserve->value,
            'available_balance_before' => '100.0000',
            'available_balance_after' => '65.0000',
            'reserved_balance_before' => '0.0000',
            'reserved_balance_after' => '35.0000',
        ]);
    }

    public function test_withdrawal_fails_if_available_balance_is_insufficient(): void
    {
        $wallet = Wallet::factory()->create([
            'available_balance' => '20.0000',
            'reserved_balance' => '0.0000',
            'currency' => 'USD',
        ]);

        $response = $this
            ->withHeader('Idempotency-Key', 'withdrawal-insufficient-key')
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", [
                'amount' => '25.0000',
                'currency' => 'USD',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient available balance. Requested 25.0000, available 20.0000.');

        $wallet->refresh();

        $this->assertSame('20.0000', $wallet->available_balance);
        $this->assertSame('0.0000', $wallet->reserved_balance);
        $this->assertSame(0, WithdrawalRequest::query()->count());
        $this->assertSame(0, BankPaymentRequest::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_reserved_withdrawal_amount_is_not_spendable_by_another_withdrawal(): void
    {
        $wallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'reserved_balance' => '0.0000',
            'currency' => 'USD',
        ]);

        $this
            ->withHeader('Idempotency-Key', 'reserve-not-spendable-first')
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", [
                'amount' => '80.0000',
                'currency' => 'USD',
            ])
            ->assertAccepted();

        $response = $this
            ->withHeader('Idempotency-Key', 'reserve-not-spendable-second')
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", [
                'amount' => '30.0000',
                'currency' => 'USD',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient available balance. Requested 30.0000, available 20.0000.');

        $wallet->refresh();

        $this->assertSame('20.0000', $wallet->available_balance);
        $this->assertSame('80.0000', $wallet->reserved_balance);
        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(1, BankPaymentRequest::query()->count());
    }

    public function test_success_callback_captures_reserved_funds(): void
    {
        [$wallet, $payment] = $this->createPendingWithdrawal('withdrawal-success-key', '40.0000');

        $response = $this->postJson('/api/v1/integrations/bank/callbacks', [
            'provider_reference' => $payment->provider_reference,
            'status' => 'succeeded',
            'occurred_at' => '2026-05-02T12:00:00Z',
            'payload' => ['settlement_id' => 'settle_1001'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', BankPaymentRequestStatus::Succeeded->value);

        $wallet->refresh();
        $payment->refresh();
        $withdrawal = $payment->withdrawalRequest()->firstOrFail();

        $this->assertSame('60.0000', $wallet->available_balance);
        $this->assertSame('0.0000', $wallet->reserved_balance);
        $this->assertSame(WithdrawalRequestStatus::Succeeded, $withdrawal->status);
        $this->assertSame(BankPaymentRequestStatus::Succeeded, $payment->status);
        $this->assertDatabaseHas('wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => WalletLedgerEntryType::WithdrawalCapture->value,
            'reserved_balance_before' => '40.0000',
            'reserved_balance_after' => '0.0000',
        ]);
    }

    public function test_failure_callback_releases_reserved_funds(): void
    {
        [$wallet, $payment] = $this->createPendingWithdrawal('withdrawal-failure-key', '40.0000');

        $response = $this->postJson('/api/v1/integrations/bank/callbacks', [
            'provider_reference' => $payment->provider_reference,
            'status' => 'failed',
            'occurred_at' => '2026-05-02T12:00:00Z',
            'failure_reason' => 'Rejected by simulated bank.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', BankPaymentRequestStatus::Failed->value)
            ->assertJsonPath('data.failure_reason', 'Rejected by simulated bank.');

        $wallet->refresh();
        $payment->refresh();
        $withdrawal = $payment->withdrawalRequest()->firstOrFail();

        $this->assertSame('100.0000', $wallet->available_balance);
        $this->assertSame('0.0000', $wallet->reserved_balance);
        $this->assertSame(WithdrawalRequestStatus::Failed, $withdrawal->status);
        $this->assertSame(BankPaymentRequestStatus::Failed, $payment->status);
        $this->assertSame('Rejected by simulated bank.', $withdrawal->failure_reason);
        $this->assertDatabaseHas('wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => WalletLedgerEntryType::WithdrawalRelease->value,
            'available_balance_after' => '100.0000',
            'reserved_balance_after' => '0.0000',
        ]);
    }

    public function test_duplicate_callback_does_not_change_balance_twice(): void
    {
        [$wallet, $payment] = $this->createPendingWithdrawal('withdrawal-duplicate-callback-key', '40.0000');

        $callbackPayload = [
            'provider_reference' => $payment->provider_reference,
            'status' => 'succeeded',
            'occurred_at' => '2026-05-02T12:00:00Z',
        ];

        $this->postJson('/api/v1/integrations/bank/callbacks', $callbackPayload)->assertOk();

        $this->postJson('/api/v1/integrations/bank/callbacks', $callbackPayload)->assertOk();

        $wallet->refresh();

        $this->assertSame('60.0000', $wallet->available_balance);
        $this->assertSame('0.0000', $wallet->reserved_balance);
        $this->assertSame(2, WalletLedgerEntry::query()->count());
    }

    public function test_duplicate_withdrawal_request_returns_original_state_without_double_reserve(): void
    {
        $wallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'reserved_balance' => '0.0000',
            'currency' => 'USD',
        ]);

        $payload = [
            'amount' => '30.0000',
            'currency' => 'USD',
        ];

        $firstResponse = $this
            ->withHeader('Idempotency-Key', 'duplicate-withdrawal-key')
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", $payload);

        $secondResponse = $this
            ->withHeader('Idempotency-Key', 'duplicate-withdrawal-key')
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", $payload);

        $firstResponse->assertAccepted();
        $secondResponse
            ->assertAccepted()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));

        $wallet->refresh();

        $this->assertSame('70.0000', $wallet->available_balance);
        $this->assertSame('30.0000', $wallet->reserved_balance);
        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(1, BankPaymentRequest::query()->count());
        $this->assertSame(1, WalletLedgerEntry::query()->count());
    }

    /**
     * @return array{Wallet, BankPaymentRequest}
     */
    private function createPendingWithdrawal(string $idempotencyKey, string $amount): array
    {
        $wallet = Wallet::factory()->create([
            'available_balance' => '100.0000',
            'reserved_balance' => '0.0000',
            'currency' => 'USD',
        ]);

        $response = $this
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/wallets/{$wallet->id}/withdrawals", [
                'amount' => $amount,
                'currency' => 'USD',
            ]);

        $response->assertAccepted();

        return [$wallet, BankPaymentRequest::query()->firstOrFail()];
    }
}
