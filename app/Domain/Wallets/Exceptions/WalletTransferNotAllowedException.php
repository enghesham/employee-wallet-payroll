<?php

namespace App\Domain\Wallets\Exceptions;

use RuntimeException;

class WalletTransferNotAllowedException extends RuntimeException
{
    public static function sameWallet(): self
    {
        return new self('Transfer requires two different wallets.');
    }

    public static function differentEmployees(): self
    {
        return new self('Transfers are only allowed between wallets owned by the same employee.');
    }
}
