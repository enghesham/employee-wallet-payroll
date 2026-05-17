<?php

namespace App\Domain\Wallets\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';
}
