<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Shared\Enums\IdempotencyRecordStatus;
use App\Domain\Shared\Models\IdempotencyRecord;
use App\Domain\Wallets\Enums\WalletLedgerEntryDirection;
use App\Domain\Wallets\Enums\WalletLedgerEntryStatus;
use App\Domain\Wallets\Enums\WalletLedgerEntryType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Exceptions\CurrencyMismatchException;
use App\Domain\Wallets\Exceptions\DuplicateOperationException;
use App\Domain\Wallets\Exceptions\InsufficientFundsException;
use App\Domain\Wallets\Exceptions\InvalidMoneyAmountException;
use App\Domain\Wallets\Exceptions\WalletNotActiveException;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use Closure;
use Illuminate\Support\Facades\DB;

class WalletLedgerService
{
    private const SCALE = 4;

    public function credit(
        Wallet $wallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        WalletLedgerEntryType $type = WalletLedgerEntryType::ManualCredit,
        ?object $source = null,
        ?string $reason = null,
        ?string $reference = null,
        array $metadata = [],
    ): WalletLedgerEntry {
        $amount = $this->normalizeAmount($amount);
        $currency = strtoupper($currency);

        return $this->runIdempotently(
            'wallet.credit',
            $idempotencyKey,
            compact('wallet', 'amount', 'currency', 'type', 'reason', 'reference', 'metadata'),
            function () use ($wallet, $amount, $currency, $idempotencyKey, $type, $source, $reason, $reference, $metadata): array {
                $lockedWallet = $this->lockWallet($wallet->id);

                $this->assertWalletCanMoveMoney($lockedWallet, $currency);

                $entry = $this->applyBalanceChange(
                    wallet: $lockedWallet,
                    type: $type,
                    direction: WalletLedgerEntryDirection::Credit,
                    amount: $amount,
                    availableAfter: $this->add($lockedWallet->available_balance, $amount),
                    reservedAfter: $lockedWallet->reserved_balance,
                    idempotencyKey: $this->ledgerKey($idempotencyKey, 'credit'),
                    source: $source,
                    reason: $reason,
                    reference: $reference,
                    metadata: $metadata,
                );

                return ['ledger_entry_ids' => [$entry->id]];
            },
            fn (array $body) => WalletLedgerEntry::query()->findOrFail($body['ledger_entry_ids'][0]),
        );
    }

    public function debit(
        Wallet $wallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        WalletLedgerEntryType $type = WalletLedgerEntryType::ManualDebit,
        ?object $source = null,
        ?string $reason = null,
        ?string $reference = null,
        array $metadata = [],
    ): WalletLedgerEntry {
        $amount = $this->normalizeAmount($amount);
        $currency = strtoupper($currency);

        return $this->runIdempotently(
            'wallet.debit',
            $idempotencyKey,
            compact('wallet', 'amount', 'currency', 'type', 'reason', 'reference', 'metadata'),
            function () use ($wallet, $amount, $currency, $idempotencyKey, $type, $source, $reason, $reference, $metadata): array {
                $lockedWallet = $this->lockWallet($wallet->id);

                $this->assertWalletCanMoveMoney($lockedWallet, $currency);
                $this->assertAvailableBalance($lockedWallet, $amount);

                $entry = $this->applyBalanceChange(
                    wallet: $lockedWallet,
                    type: $type,
                    direction: WalletLedgerEntryDirection::Debit,
                    amount: $amount,
                    availableAfter: $this->sub($lockedWallet->available_balance, $amount),
                    reservedAfter: $lockedWallet->reserved_balance,
                    idempotencyKey: $this->ledgerKey($idempotencyKey, 'debit'),
                    source: $source,
                    reason: $reason,
                    reference: $reference,
                    metadata: $metadata,
                );

                return ['ledger_entry_ids' => [$entry->id]];
            },
            fn (array $body) => WalletLedgerEntry::query()->findOrFail($body['ledger_entry_ids'][0]),
        );
    }

    public function reserve(
        Wallet $wallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        WalletLedgerEntryType $type = WalletLedgerEntryType::WithdrawalReserve,
        ?object $source = null,
        ?string $reason = null,
        ?string $reference = null,
        array $metadata = [],
    ): WalletLedgerEntry {
        $amount = $this->normalizeAmount($amount);
        $currency = strtoupper($currency);

        return $this->runIdempotently(
            'wallet.reserve',
            $idempotencyKey,
            compact('wallet', 'amount', 'currency', 'type', 'reason', 'reference', 'metadata'),
            function () use ($wallet, $amount, $currency, $idempotencyKey, $type, $source, $reason, $reference, $metadata): array {
                $lockedWallet = $this->lockWallet($wallet->id);

                $this->assertWalletCanMoveMoney($lockedWallet, $currency);
                $this->assertAvailableBalance($lockedWallet, $amount);

                $entry = $this->applyBalanceChange(
                    wallet: $lockedWallet,
                    type: $type,
                    direction: WalletLedgerEntryDirection::Reserve,
                    amount: $amount,
                    availableAfter: $this->sub($lockedWallet->available_balance, $amount),
                    reservedAfter: $this->add($lockedWallet->reserved_balance, $amount),
                    idempotencyKey: $this->ledgerKey($idempotencyKey, 'reserve'),
                    source: $source,
                    reason: $reason,
                    reference: $reference,
                    metadata: $metadata,
                );

                return ['ledger_entry_ids' => [$entry->id]];
            },
            fn (array $body) => WalletLedgerEntry::query()->findOrFail($body['ledger_entry_ids'][0]),
        );
    }

    public function release(
        Wallet $wallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        WalletLedgerEntryType $type = WalletLedgerEntryType::WithdrawalRelease,
        ?object $source = null,
        ?string $reason = null,
        ?string $reference = null,
        array $metadata = [],
    ): WalletLedgerEntry {
        $amount = $this->normalizeAmount($amount);
        $currency = strtoupper($currency);

        return $this->runReservedBalanceChange(
            scope: 'wallet.release',
            wallet: $wallet,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            type: $type,
            direction: WalletLedgerEntryDirection::Release,
            source: $source,
            reason: $reason,
            reference: $reference,
            metadata: $metadata,
            availableDelta: $amount,
        );
    }

    public function captureReserved(
        Wallet $wallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        WalletLedgerEntryType $type = WalletLedgerEntryType::WithdrawalCapture,
        ?object $source = null,
        ?string $reason = null,
        ?string $reference = null,
        array $metadata = [],
    ): WalletLedgerEntry {
        $amount = $this->normalizeAmount($amount);
        $currency = strtoupper($currency);

        return $this->runReservedBalanceChange(
            scope: 'wallet.capture_reserved',
            wallet: $wallet,
            amount: $amount,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            type: $type,
            direction: WalletLedgerEntryDirection::Debit,
            source: $source,
            reason: $reason,
            reference: $reference,
            metadata: $metadata,
            availableDelta: '0.0000',
        );
    }

    /**
     * @return array{debit: WalletLedgerEntry, credit: WalletLedgerEntry}
     */
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?string $reason = null,
        ?string $reference = null,
        array $metadata = [],
    ): array {
        $amount = $this->normalizeAmount($amount);
        $currency = strtoupper($currency);

        return $this->runIdempotently(
            'wallet.transfer',
            $idempotencyKey,
            compact('fromWallet', 'toWallet', 'amount', 'currency', 'reason', 'reference', 'metadata'),
            function () use ($fromWallet, $toWallet, $amount, $currency, $idempotencyKey, $reason, $reference, $metadata): array {
                if ($fromWallet->is($toWallet)) {
                    throw CurrencyMismatchException::forWallet('different wallet', 'same wallet');
                }

                $lockedWallets = Wallet::query()
                    ->whereIn('id', [$fromWallet->id, $toWallet->id])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                /** @var Wallet $lockedFrom */
                $lockedFrom = $lockedWallets->get($fromWallet->id);
                /** @var Wallet $lockedTo */
                $lockedTo = $lockedWallets->get($toWallet->id);

                $this->assertWalletCanMoveMoney($lockedFrom, $currency);
                $this->assertWalletCanMoveMoney($lockedTo, $currency);
                $this->assertAvailableBalance($lockedFrom, $amount);

                $debitEntry = $this->applyBalanceChange(
                    wallet: $lockedFrom,
                    type: WalletLedgerEntryType::TransferOut,
                    direction: WalletLedgerEntryDirection::Debit,
                    amount: $amount,
                    availableAfter: $this->sub($lockedFrom->available_balance, $amount),
                    reservedAfter: $lockedFrom->reserved_balance,
                    idempotencyKey: $this->ledgerKey($idempotencyKey, 'transfer-out'),
                    source: null,
                    reason: $reason,
                    reference: $reference,
                    metadata: $metadata,
                );

                $creditEntry = $this->applyBalanceChange(
                    wallet: $lockedTo,
                    type: WalletLedgerEntryType::TransferIn,
                    direction: WalletLedgerEntryDirection::Credit,
                    amount: $amount,
                    availableAfter: $this->add($lockedTo->available_balance, $amount),
                    reservedAfter: $lockedTo->reserved_balance,
                    idempotencyKey: $this->ledgerKey($idempotencyKey, 'transfer-in'),
                    source: null,
                    reason: $reason,
                    reference: $reference,
                    metadata: $metadata,
                );

                return ['ledger_entry_ids' => [$debitEntry->id, $creditEntry->id]];
            },
            function (array $body): array {
                $entries = WalletLedgerEntry::query()
                    ->whereIn('id', $body['ledger_entry_ids'])
                    ->get()
                    ->keyBy('id');

                return [
                    'debit' => $entries->get($body['ledger_entry_ids'][0]),
                    'credit' => $entries->get($body['ledger_entry_ids'][1]),
                ];
            },
        );
    }

    private function runReservedBalanceChange(
        string $scope,
        Wallet $wallet,
        string $amount,
        string $currency,
        string $idempotencyKey,
        WalletLedgerEntryType $type,
        WalletLedgerEntryDirection $direction,
        ?object $source,
        ?string $reason,
        ?string $reference,
        array $metadata,
        string $availableDelta,
    ): WalletLedgerEntry {
        return $this->runIdempotently(
            $scope,
            $idempotencyKey,
            compact('wallet', 'amount', 'currency', 'type', 'direction', 'reason', 'reference', 'metadata', 'availableDelta'),
            function () use ($scope, $wallet, $amount, $currency, $idempotencyKey, $type, $direction, $source, $reason, $reference, $metadata, $availableDelta): array {
                $lockedWallet = $this->lockWallet($wallet->id);

                $this->assertWalletCanMoveMoney($lockedWallet, $currency);
                $this->assertReservedBalance($lockedWallet, $amount);

                $entry = $this->applyBalanceChange(
                    wallet: $lockedWallet,
                    type: $type,
                    direction: $direction,
                    amount: $amount,
                    availableAfter: $this->add($lockedWallet->available_balance, $availableDelta),
                    reservedAfter: $this->sub($lockedWallet->reserved_balance, $amount),
                    idempotencyKey: $this->ledgerKey($idempotencyKey, str_replace('wallet.', '', $scope)),
                    source: $source,
                    reason: $reason,
                    reference: $reference,
                    metadata: $metadata,
                );

                return ['ledger_entry_ids' => [$entry->id]];
            },
            fn (array $body) => WalletLedgerEntry::query()->findOrFail($body['ledger_entry_ids'][0]),
        );
    }

    private function runIdempotently(string $scope, string $key, array $payload, Closure $callback, Closure $resolver): mixed
    {
        $requestHash = hash('sha256', json_encode($this->normalizePayload($payload), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($scope, $key, $requestHash, $callback, $resolver): mixed {
            $record = IdempotencyRecord::query()
                ->where('scope', $scope)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($record !== null) {
                if ($record->request_hash !== $requestHash) {
                    throw DuplicateOperationException::idempotencyConflict($scope, $key);
                }

                if ($record->status === IdempotencyRecordStatus::Completed) {
                    return $resolver($record->response_body ?? []);
                }
            } else {
                $record = IdempotencyRecord::query()->create([
                    'scope' => $scope,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'status' => IdempotencyRecordStatus::Processing,
                    'locked_until' => now()->addMinutes(5),
                    'expires_at' => now()->addDay(),
                ]);
            }

            $responseBody = $callback();

            $record->update([
                'status' => IdempotencyRecordStatus::Completed,
                'response_code' => 200,
                'response_body' => $responseBody,
                'completed_at' => now(),
                'locked_until' => null,
            ]);

            return $resolver($responseBody);
        });
    }

    private function applyBalanceChange(
        Wallet $wallet,
        WalletLedgerEntryType $type,
        WalletLedgerEntryDirection $direction,
        string $amount,
        string $availableAfter,
        string $reservedAfter,
        string $idempotencyKey,
        ?object $source,
        ?string $reason,
        ?string $reference,
        array $metadata,
    ): WalletLedgerEntry {
        $availableBefore = $wallet->available_balance;
        $reservedBefore = $wallet->reserved_balance;

        $wallet->forceFill([
            'available_balance' => $availableAfter,
            'reserved_balance' => $reservedAfter,
        ])->save();

        return WalletLedgerEntry::query()->create([
            'employee_id' => $wallet->employee_id,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'available_balance_before' => $availableBefore,
            'available_balance_after' => $availableAfter,
            'reserved_balance_before' => $reservedBefore,
            'reserved_balance_after' => $reservedAfter,
            'currency' => $wallet->currency,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->id,
            'status' => WalletLedgerEntryStatus::Posted,
            'reason' => $reason,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata,
        ]);
    }

    private function lockWallet(int $walletId): Wallet
    {
        return Wallet::query()
            ->whereKey($walletId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertWalletCanMoveMoney(Wallet $wallet, string $currency): void
    {
        if ($wallet->status !== WalletStatus::Active) {
            throw WalletNotActiveException::forWallet($wallet);
        }

        if ($wallet->currency !== $currency) {
            throw CurrencyMismatchException::forWallet($wallet->currency, $currency);
        }
    }

    private function assertAvailableBalance(Wallet $wallet, string $amount): void
    {
        if ($this->cmp($wallet->available_balance, $amount) < 0) {
            throw InsufficientFundsException::available($amount, $wallet->available_balance);
        }
    }

    private function assertReservedBalance(Wallet $wallet, string $amount): void
    {
        if ($this->cmp($wallet->reserved_balance, $amount) < 0) {
            throw InsufficientFundsException::reserved($amount, $wallet->reserved_balance);
        }
    }

    private function normalizeAmount(string $amount): string
    {
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $amount)) {
            throw InvalidMoneyAmountException::notPositive($amount);
        }

        $normalized = bcadd($amount, '0', self::SCALE);

        if ($this->cmp($normalized, '0.0000') <= 0) {
            throw InvalidMoneyAmountException::notPositive($amount);
        }

        return $normalized;
    }

    private function normalizePayload(array $payload): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value instanceof Wallet) {
                return ['wallet_id' => $value->id];
            }

            if ($value instanceof WalletLedgerEntryType || $value instanceof WalletLedgerEntryDirection) {
                return $value->value;
            }

            if (is_array($value)) {
                return $this->normalizePayload($value);
            }

            return $value;
        }, $payload);
    }

    private function ledgerKey(string $idempotencyKey, string $entry): string
    {
        return "{$idempotencyKey}:{$entry}";
    }

    private function add(string $left, string $right): string
    {
        return bcadd($left, $right, self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($left, $right, self::SCALE);
    }

    private function cmp(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }
}
