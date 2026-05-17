<?php

namespace Database\Factories;

use App\Domain\Payroll\Enums\PayrollBatchStatus;
use App\Domain\Payroll\Models\PayrollBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollBatch> */
class PayrollBatchFactory extends Factory
{
    protected $model = PayrollBatch::class;

    public function definition(): array
    {
        return [
            'provider' => 'mock_payroll',
            'provider_batch_id' => 'batch_'.fake()->unique()->uuid(),
            'status' => PayrollBatchStatus::Pending,
            'currency' => 'USD',
            'total_amount' => '0.0000',
            'total_events' => 0,
            'processed_events' => 0,
            'failed_events' => 0,
            'metadata' => [],
        ];
    }
}
