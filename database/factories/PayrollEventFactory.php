<?php

namespace Database\Factories;

use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Models\PayrollBatch;
use App\Domain\Payroll\Models\PayrollEvent;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollEvent> */
class PayrollEventFactory extends Factory
{
    protected $model = PayrollEvent::class;

    public function definition(): array
    {
        $amount = number_format(fake()->numberBetween(10000, 500000) / 100, 4, '.', '');

        return [
            'payroll_batch_id' => PayrollBatch::factory(),
            'wallet_id' => Wallet::factory(),
            'employee_id' => fn (array $attributes) => Wallet::query()->find($attributes['wallet_id'])->employee_id,
            'provider' => 'mock_payroll',
            'provider_event_id' => 'evt_'.fake()->unique()->uuid(),
            'event_type' => 'salary_paid',
            'payroll_employee_id' => fn (array $attributes) => 'employee_'.$attributes['employee_id'],
            'amount' => $amount,
            'currency' => 'USD',
            'status' => PayrollEventStatus::Received,
            'payload' => [
                'amount' => $amount,
                'currency' => 'USD',
            ],
            'occurred_at' => now(),
        ];
    }
}
