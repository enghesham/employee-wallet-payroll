<?php

namespace App\Domain\Banking\Enums;

enum WithdrawalRequestStatus: string
{
    case PendingBankConfirmation = 'pending_bank_confirmation';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
