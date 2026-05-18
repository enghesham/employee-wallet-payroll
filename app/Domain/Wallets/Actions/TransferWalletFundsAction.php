<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Models\WalletLedgerEntry;
use App\Domain\Wallets\Services\WalletLedgerService;

class TransferWalletFundsAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * @param  array{to_wallet_id: int, amount: string, currency: string, reason?: string|null, metadata?: array<string, mixed>}  $data
     * @return array{debit: WalletLedgerEntry, credit: WalletLedgerEntry}
     */
    public function execute(Wallet $fromWallet, array $data, string $idempotencyKey): array
    {
        $toWallet = Wallet::query()->findOrFail($data['to_wallet_id']);

        return $this->ledger->transfer(
            fromWallet: $fromWallet,
            toWallet: $toWallet,
            amount: $data['amount'],
            currency: strtoupper($data['currency']),
            idempotencyKey: $idempotencyKey,
            reason: $data['reason'] ?? 'Wallet-to-wallet transfer.',
            reference: $idempotencyKey,
            metadata: $data['metadata'] ?? [],
        );
    }
}
