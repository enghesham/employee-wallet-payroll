<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Employees\Models\Employee;
use App\Domain\Payroll\Enums\PayrollEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTokenMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_configured_provider_token_fails_closed(): void
    {
        config(['integrations.provider_tokens.payroll' => null]);

        $this->withToken('anything')->postJson('/api/v1/payroll/events', $this->payrollPayload())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Provider token for [payroll] is not configured.');
    }

    public function test_missing_request_token_returns_unauthorized(): void
    {
        $this->postJson('/api/v1/payroll/events', $this->payrollPayload())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid provider token.');
    }

    public function test_invalid_request_token_returns_unauthorized(): void
    {
        $this->withToken('wrong-token')->postJson('/api/v1/payroll/events', $this->payrollPayload())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid provider token.');
    }

    public function test_valid_token_allows_request(): void
    {
        Employee::factory()->create(['external_reference' => 'provider_token_emp']);

        $this->withToken('local-payroll-token')->postJson('/api/v1/payroll/events', $this->payrollPayload())
            ->assertAccepted()
            ->assertJsonPath('data.status', 'processed');
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollPayload(): array
    {
        return [
            'provider_event_id' => 'provider-token-event-'.uniqid(),
            'event_type' => PayrollEventType::EmployeeOnboarded->value,
            'payload' => [
                'employee' => [
                    'external_reference' => 'provider_token_emp',
                    'name' => 'Provider Token Employee',
                    'email' => 'provider.token@example.test',
                    'status' => EmployeeStatus::Active->value,
                ],
            ],
        ];
    }
}
