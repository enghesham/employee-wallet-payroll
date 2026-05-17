<?php

namespace App\Domain\Wallets\Exceptions;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public static function available(string $requested, string $available): self
    {
        return new self("Insufficient available balance. Requested {$requested}, available {$available}.");
    }

    public static function reserved(string $requested, string $reserved): self
    {
        return new self("Insufficient reserved balance. Requested {$requested}, reserved {$reserved}.");
    }
}
