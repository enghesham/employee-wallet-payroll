<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Payroll\Enums\PayrollEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSignatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_configured_webhook_secret_fails_closed(): void
    {
        config(['integrations.webhook_secrets.payroll' => null]);

        $this->postJsonWithProviderSignature('/api/v1/payroll/events', $this->payrollPayload(), 'payroll', 'anything')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Webhook secret for [payroll] is not configured.');
    }

    public function test_missing_signature_headers_return_unauthorized(): void
    {
        $this->postJson('/api/v1/payroll/events', $this->payrollPayload())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid webhook signature.');
    }

    public function test_invalid_signature_returns_unauthorized(): void
    {
        $this->postJsonWithProviderSignature('/api/v1/payroll/events', $this->payrollPayload(), 'payroll', 'wrong-secret')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid webhook signature.');
    }

    public function test_stale_signature_timestamp_returns_unauthorized(): void
    {
        $this->postJsonWithProviderSignature('/api/v1/payroll/events', $this->payrollPayload(), 'payroll', timestamp: now()->subMinutes(10)->timestamp)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Webhook signature timestamp is outside the allowed tolerance.');
    }

    public function test_valid_signature_allows_request(): void
    {
        $this->postJsonWithProviderSignature('/api/v1/payroll/events', $this->payrollPayload(), 'payroll')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'processed');
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollPayload(): array
    {
        return [
            'provider_event_id' => 'provider-signature-event-'.uniqid(),
            'event_type' => PayrollEventType::EmployeeOnboarded->value,
            'payload' => [
                'employee' => [
                    'external_reference' => 'provider_signature_emp',
                    'name' => 'Provider Signature Employee',
                    'email' => 'provider.signature@example.test',
                    'status' => EmployeeStatus::Active->value,
                ],
            ],
        ];
    }
}
