<?php

namespace App\Domain\Wallets\Exceptions;

use App\Domain\Wallets\Models\Wallet;
use RuntimeException;

class WalletNotActiveException extends RuntimeException
{
    public static function forWallet(Wallet $wallet): self
    {
        return new self("Wallet [{$wallet->id}] is not active.");
    }
}
