<?php

namespace App\Domain\Wallets\Enums;

enum WalletLedgerEntryStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
