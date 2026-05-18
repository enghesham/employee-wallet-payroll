<?php

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use App\Domain\Banking\Exceptions\BankPaymentStateException;
use App\Domain\Banking\Models\BankPaymentRequest;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

class HandleBankPaymentCallbackAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * @param  array{status: string, provider_reference?: string|null, failure_reason?: string|null, payload?: array<string, mixed>}  $data
     */
    public function execute(BankPaymentRequest $bankPaymentRequest, array $data): BankPaymentRequest
    {
        return DB::transaction(function () use ($bankPaymentRequest, $data): BankPaymentRequest {
            /** @var BankPaymentRequest $payment */
            $payment = BankPaymentRequest::query()
                ->with(['withdrawalRequest.wallet'])
                ->whereKey($bankPaymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $requestedStatus = $data['status'];

            if ($this->isFinal($payment->status)) {
                if ($this->matchesFinalStatus($payment->status, $requestedStatus)) {
                    return $payment->load(['withdrawalRequest.wallet']);
                }

                throw BankPaymentStateException::conflictingFinalState($payment->status->value, $requestedStatus);
            }

            return match ($requestedStatus) {
                'success' => $this->handleSuccess($payment, $data),
                'failed' => $this->handleFailure($payment, $data),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleSuccess(BankPaymentRequest $payment, array $data): BankPaymentRequest
    {
        $withdrawal = $payment->withdrawalRequest;

        $this->ledger->captureReserved(
            wallet: $withdrawal->wallet,
            amount: $withdrawal->amount,
            currency: $withdrawal->currency,
            idempotencyKey: "bank-callback:{$payment->id}:success",
            type: WalletLedgerEntryType::WithdrawalCapture,
            source: $withdrawal,
            reason: 'Bank withdrawal succeeded.',
            reference: $withdrawal->reference,
            metadata: ['bank_payment_request_id' => $payment->id],
        );

        $withdrawal->forceFill([
            'status' => WithdrawalRequestStatus::Succeeded,
            'completed_at' => now(),
            'failure_reason' => null,
        ])->save();

        $payment->forceFill([
            'provider_reference' => $data['provider_reference'] ?? $payment->provider_reference,
            'status' => BankPaymentRequestStatus::Succeeded,
            'response_payload' => $data['payload'] ?? [],
            'confirmed_at' => now(),
            'failure_reason' => null,
        ])->save();

        return $payment->refresh()->load(['withdrawalRequest.wallet']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleFailure(BankPaymentRequest $payment, array $data): BankPaymentRequest
    {
        $withdrawal = $payment->withdrawalRequest;
        $failureReason = $data['failure_reason'] ?? 'Bank payment failed.';

        $this->ledger->release(
            wallet: $withdrawal->wallet,
            amount: $withdrawal->amount,
            currency: $withdrawal->currency,
            idempotencyKey: "bank-callback:{$payment->id}:failed",
            type: WalletLedgerEntryType::WithdrawalRelease,
            source: $withdrawal,
            reason: 'Bank withdrawal failed.',
            reference: $withdrawal->reference,
            metadata: ['bank_payment_request_id' => $payment->id],
        );

        $withdrawal->forceFill([
            'status' => WithdrawalRequestStatus::Failed,
            'completed_at' => now(),
            'failure_reason' => $failureReason,
        ])->save();

        $payment->forceFill([
            'provider_reference' => $data['provider_reference'] ?? $payment->provider_reference,
            'status' => BankPaymentRequestStatus::Failed,
            'response_payload' => $data['payload'] ?? [],
            'failed_at' => now(),
            'failure_reason' => $failureReason,
        ])->save();

        return $payment->refresh()->load(['withdrawalRequest.wallet']);
    }

    private function isFinal(BankPaymentRequestStatus $status): bool
    {
        return in_array($status, [BankPaymentRequestStatus::Succeeded, BankPaymentRequestStatus::Failed], true);
    }

    private function matchesFinalStatus(BankPaymentRequestStatus $currentStatus, string $requestedStatus): bool
    {
        return ($currentStatus === BankPaymentRequestStatus::Succeeded && $requestedStatus === 'success')
            || ($currentStatus === BankPaymentRequestStatus::Failed && $requestedStatus === 'failed');
    }
}
