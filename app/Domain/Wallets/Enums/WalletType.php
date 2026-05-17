<?php

namespace App\Domain\Wallets\Enums;

enum WalletType: string
{
    case Salary = 'salary';
    case Savings = 'savings';
    case Bonus = 'bonus';
}
