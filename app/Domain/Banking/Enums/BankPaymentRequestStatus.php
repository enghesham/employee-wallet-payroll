<?php

namespace App\Domain\Banking\Enums;

enum BankPaymentRequestStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
