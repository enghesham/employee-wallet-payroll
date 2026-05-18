<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Models\WithdrawalRequest;
use Illuminate\Support\Str;

class MockBankingProvider
{
    /**
     * @return array<string, mixed>
     */
    public function acceptWithdrawal(WithdrawalRequest $withdrawal): array
    {
        return [
            'provider' => 'mock_bank',
            'provider_reference' => 'mock_bank_'.Str::uuid()->toString(),
            'status' => 'accepted',
            'withdrawal_reference' => $withdrawal->reference,
        ];
    }
}
