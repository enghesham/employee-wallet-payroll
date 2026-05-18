<?php

namespace App\Domain\Banking\Enums;

enum BankPaymentRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Succeeded = 'succeeded';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
