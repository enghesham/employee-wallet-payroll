<?php

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use App\Domain\Banking\Exceptions\BankPaymentStateException;
use App\Domain\Banking\Models\BankPaymentRequest;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Services\WalletLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HandleBankPaymentCallbackAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * @param  array{provider?: string, provider_reference: string, status: string, occurred_at: string, failure_reason?: string|null, payload?: array<string, mixed>}  $data
     */
    public function execute(array $data): BankPaymentRequest
    {
        return DB::transaction(function () use ($data): BankPaymentRequest {
            /** @var BankPaymentRequest $payment */
            $payment = BankPaymentRequest::query()
                ->with(['withdrawalRequest.wallet'])
                ->where('provider', $data['provider'] ?? 'mock_bank')
                ->where('provider_reference', $data['provider_reference'])
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
                'succeeded' => $this->handleSuccess($payment, $data),
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
        $occurredAt = Carbon::parse($data['occurred_at']);

        $this->ledger->captureReserved(
            wallet: $withdrawal->wallet,
            amount: $withdrawal->amount,
            currency: $withdrawal->currency,
            idempotencyKey: "bank-callback:{$payment->provider}:{$payment->provider_reference}:succeeded",
            type: WalletLedgerEntryType::WithdrawalCapture,
            source: $withdrawal,
            reason: 'Bank withdrawal succeeded.',
            reference: $withdrawal->reference,
            metadata: ['bank_payment_request_id' => $payment->id],
        );

        $withdrawal->forceFill([
            'status' => WithdrawalRequestStatus::Succeeded,
            'completed_at' => $occurredAt,
            'failure_reason' => null,
        ])->save();

        $payment->forceFill([
            'status' => BankPaymentRequestStatus::Succeeded,
            'response_payload' => $this->callbackPayload($data),
            'confirmed_at' => $occurredAt,
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
        $occurredAt = Carbon::parse($data['occurred_at']);

        $this->ledger->release(
            wallet: $withdrawal->wallet,
            amount: $withdrawal->amount,
            currency: $withdrawal->currency,
            idempotencyKey: "bank-callback:{$payment->provider}:{$payment->provider_reference}:failed",
            type: WalletLedgerEntryType::WithdrawalRelease,
            source: $withdrawal,
            reason: 'Bank withdrawal failed.',
            reference: $withdrawal->reference,
            metadata: ['bank_payment_request_id' => $payment->id],
        );

        $withdrawal->forceFill([
            'status' => WithdrawalRequestStatus::Failed,
            'completed_at' => $occurredAt,
            'failure_reason' => $failureReason,
        ])->save();

        $payment->forceFill([
            'status' => BankPaymentRequestStatus::Failed,
            'response_payload' => $this->callbackPayload($data),
            'failed_at' => $occurredAt,
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
        return ($currentStatus === BankPaymentRequestStatus::Succeeded && $requestedStatus === 'succeeded')
            || ($currentStatus === BankPaymentRequestStatus::Failed && $requestedStatus === 'failed');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function callbackPayload(array $data): array
    {
        return [
            'provider' => $data['provider'] ?? 'mock_bank',
            'provider_reference' => $data['provider_reference'],
            'status' => $data['status'],
            'occurred_at' => $data['occurred_at'],
            'payload' => $data['payload'] ?? [],
        ];
    }
}
