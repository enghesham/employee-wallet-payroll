<?php

namespace App\Domain\Shared\Enums;

enum IdempotencyRecordStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
