<?php

namespace App\Domain\Wallets\Exceptions;

use RuntimeException;

class CurrencyMismatchException extends RuntimeException
{
    public static function forWallet(string $expected, string $actual): self
    {
        return new self("Wallet currency mismatch. Expected {$expected}, got {$actual}.");
    }
}
