<?php

namespace App\Domain\Wallets\Exceptions;

use RuntimeException;

class DuplicateOperationException extends RuntimeException
{
    public static function idempotencyConflict(string $scope, string $key): self
    {
        return new self("Idempotency key [{$key}] was already used for a different {$scope} request.");
    }
}
