<?php

namespace Database\Factories;

use App\Domain\Banking\Enums\BankPaymentRequestStatus;
use App\Domain\Banking\Models\BankPaymentRequest;
use App\Domain\Banking\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankPaymentRequest> */
class BankPaymentRequestFactory extends Factory
{
    protected $model = BankPaymentRequest::class;

    public function definition(): array
    {
        return [
            'withdrawal_request_id' => WithdrawalRequest::factory(),
            'provider' => 'mock_bank',
            'provider_reference' => 'bank_'.fake()->unique()->uuid(),
            'idempotency_key' => fake()->unique()->uuid(),
            'status' => BankPaymentRequestStatus::Pending,
            'request_payload' => [],
            'response_payload' => null,
        ];
    }
}
