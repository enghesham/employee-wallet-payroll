<?php

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use App\Domain\Banking\Models\WithdrawalRequest;
use App\Domain\Banking\Services\MockBankingProvider;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Exceptions\DuplicateOperationException;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestBankWithdrawalAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly MockBankingProvider $bankingProvider,
    ) {}

    /**
     * @param  array{amount: string, currency: string, metadata?: array<string, mixed>}  $data
     */
    public function execute(Wallet $wallet, array $data, string $idempotencyKey): WithdrawalRequest
    {
        $currency = strtoupper($data['currency']);

        return DB::transaction(function () use ($wallet, $data, $currency, $idempotencyKey): WithdrawalRequest {
            $existing = WithdrawalRequest::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $this->assertSameWithdrawalRequest($existing, $wallet, $data['amount'], $currency);

                return $existing->load(['wallet', 'bankPaymentRequests']);
            }

            $withdrawal = WithdrawalRequest::query()->create([
                'employee_id' => $wallet->employee_id,
                'wallet_id' => $wallet->id,
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => WithdrawalRequestStatus::PendingBankConfirmation,
                'reference' => 'wd_'.Str::uuid()->toString(),
                'idempotency_key' => $idempotencyKey,
                'metadata' => $data['metadata'] ?? [],
                'requested_at' => now(),
            ]);

            $this->ledger->reserve(
                wallet: $wallet,
                amount: $data['amount'],
                currency: $currency,
                idempotencyKey: "withdrawal:{$idempotencyKey}:reserve",
                type: WalletLedgerEntryType::WithdrawalReserve,
                source: $withdrawal,
                reason: 'Bank withdrawal requested.',
                reference: $withdrawal->reference,
                metadata: ['withdrawal_reference' => $withdrawal->reference],
            );

            $accepted = $this->bankingProvider->acceptWithdrawal($withdrawal);

            $withdrawal->bankPaymentRequests()->create([
                'provider' => $accepted['provider'],
                'provider_reference' => $accepted['provider_reference'],
                'idempotency_key' => "bank-payment:{$idempotencyKey}",
                'status' => BankPaymentRequestStatus::Accepted,
                'request_payload' => [
                    'amount' => $withdrawal->amount,
                    'currency' => $withdrawal->currency,
                    'withdrawal_reference' => $withdrawal->reference,
                ],
                'response_payload' => $accepted,
                'sent_at' => now(),
            ]);

            return $withdrawal->load(['wallet', 'bankPaymentRequests']);
        });
    }

    private function assertSameWithdrawalRequest(WithdrawalRequest $withdrawal, Wallet $wallet, string $amount, string $currency): void
    {
        if (
            $withdrawal->wallet_id !== $wallet->id
            || $withdrawal->amount !== bcadd($amount, '0', 4)
            || $withdrawal->currency !== $currency
        ) {
            throw DuplicateOperationException::idempotencyConflict('bank.withdrawal', $withdrawal->idempotency_key);
        }
    }
}
