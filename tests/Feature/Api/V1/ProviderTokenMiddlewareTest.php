<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Payroll\Enums\PayrollEventStatus;
use App\Domain\Payroll\Enums\PayrollEventType;
use App\Domain\Payroll\Models\PayrollEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTokenMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_configured_provider_token_fails_closed(): void
    {
        config(['integrations.provider_tokens.payroll' => null]);

        $event = $this->failedPayrollEvent();

        $this->withToken('anything')->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Provider token for [payroll] is not configured.');
    }

    public function test_missing_request_token_returns_unauthorized(): void
    {
        $event = $this->failedPayrollEvent();

        $this->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid provider token.');
    }

    public function test_invalid_request_token_returns_unauthorized(): void
    {
        $event = $this->failedPayrollEvent();

        $this->withToken('wrong-token')->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid provider token.');
    }

    public function test_valid_token_allows_request(): void
    {
        $event = $this->failedPayrollEvent();

        $this->withToken('local-payroll-token')->postJson("/api/v1/integrations/payroll/events/{$event->id}/retry")
            ->assertOk();
    }

    private function failedPayrollEvent(): PayrollEvent
    {
        return PayrollEvent::query()->create([
            'provider' => 'mock_payroll',
            'provider_event_id' => 'provider-token-event-'.uniqid(),
            'event_type' => PayrollEventType::EmployeeOnboarded,
            'payroll_employee_id' => 'provider_token_emp',
            'status' => PayrollEventStatus::Failed,
            'failure_reason' => 'Retry token middleware test.',
            'payload' => [
                'employee' => [
                    'external_reference' => 'provider_token_emp',
                    'name' => 'Provider Token Employee',
                    'email' => 'provider.token@example.test',
                    'status' => EmployeeStatus::Active->value,
                ],
            ],
        ]);
    }
}
