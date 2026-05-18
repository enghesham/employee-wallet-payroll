<?php

namespace App\Domain\Payroll\Exceptions;

use RuntimeException;

class PayrollEventStateException extends RuntimeException
{
    public static function alreadyProcessing(int $eventId): self
    {
        return new self("Payroll event [{$eventId}] is already processing.");
    }

    public static function cannotRetry(int $eventId, string $status): self
    {
        return new self("Payroll event [{$eventId}] cannot be retried from status [{$status}].");
    }
}
