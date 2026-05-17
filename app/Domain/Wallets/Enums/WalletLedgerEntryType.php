<?php

namespace App\Domain\Wallets\Enums;

enum WalletLedgerEntryType: string
{
    case PayrollCredit = 'payroll_credit';
    case ManualCredit = 'manual_credit';
    case ManualDebit = 'manual_debit';
    case WithdrawalReserve = 'withdrawal_reserve';
    case WithdrawalCapture = 'withdrawal_capture';
    case WithdrawalRelease = 'withdrawal_release';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';
}
