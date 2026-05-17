<?php

namespace Database\Factories;

use App\Domain\Banking\Enums\WithdrawalRequestStatus;
use App\Domain\Banking\Models\WithdrawalRequest;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalRequest> */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    public function definition(): array
    {
        $amount = number_format(fake()->numberBetween(1000, 100000) / 100, 4, '.', '');

        return [
            'wallet_id' => Wallet::factory(),
            'employee_id' => fn (array $attributes) => Wallet::query()->find($attributes['wallet_id'])->employee_id,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => WithdrawalRequestStatus::PendingBankConfirmation,
            'reference' => 'wd_'.fake()->unique()->uuid(),
            'idempotency_key' => fake()->unique()->uuid(),
            'metadata' => [],
            'requested_at' => now(),
        ];
    }
}
