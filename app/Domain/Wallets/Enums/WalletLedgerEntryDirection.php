<?php

namespace App\Domain\Wallets\Enums;

enum WalletLedgerEntryDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Reserve = 'reserve';
    case Release = 'release';
}
