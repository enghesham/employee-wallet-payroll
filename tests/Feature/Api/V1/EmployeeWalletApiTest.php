<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_employee(): void
    {
        $response = $this->postJson('/api/v1/employees', [
            'name' => 'Jane Payroll',
            'email' => 'jane.payroll@example.test',
            'external_reference' => 'payroll_emp_1001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Jane Payroll')
            ->assertJsonPath('data.email', 'jane.payroll@example.test')
            ->assertJsonPath('data.external_reference', 'payroll_emp_1001')
            ->assertJsonPath('data.status', EmployeeStatus::Active->value);

        $this->assertDatabaseHas('employees', [
            'email' => 'jane.payroll@example.test',
            'external_reference' => 'payroll_emp_1001',
        ]);
    }

    public function test_it_lists_employees_with_filters_and_pagination(): void
    {
        Employee::factory()->create([
            'name' => 'Active Person',
            'email' => 'active@example.test',
            'status' => EmployeeStatus::Active,
        ]);
        Employee::factory()->create([
            'name' => 'Suspended Person',
            'email' => 'suspended@example.test',
            'status' => EmployeeStatus::Suspended,
        ]);

        $response = $this->getJson('/api/v1/employees?status=active&search=active&per_page=10');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'active@example.test')
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_it_shows_an_employee(): void
    {
        $employee = Employee::factory()->create();
        Wallet::factory()->for($employee)->create();

        $response = $this->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.wallets_count', 1);
    }

    public function test_it_creates_a_wallet_for_an_employee(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->postJson("/api/v1/employees/{$employee->id}/wallets", [
            'type' => WalletType::Salary->value,
            'currency' => 'usd',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.type', WalletType::Salary->value)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.available_balance', '0.0000')
            ->assertJsonPath('data.reserved_balance', '0.0000')
            ->assertJsonPath('data.status', WalletStatus::Active->value);

        $this->assertDatabaseHas('wallets', [
            'employee_id' => $employee->id,
            'type' => WalletType::Salary->value,
            'currency' => 'USD',
        ]);
    }

    public function test_it_prevents_duplicate_wallet_type_and_currency_for_same_employee(): void
    {
        $employee = Employee::factory()->create();
        Wallet::factory()->for($employee)->create([
            'type' => WalletType::Salary,
            'currency' => 'USD',
        ]);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/wallets", [
            'type' => WalletType::Salary->value,
            'currency' => 'USD',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_it_lists_wallets_with_filters_and_pagination(): void
    {
        $employee = Employee::factory()->create();
        Wallet::factory()->for($employee)->create([
            'type' => WalletType::Salary,
            'currency' => 'USD',
            'status' => WalletStatus::Active,
        ]);
        Wallet::factory()->for($employee)->create([
            'type' => WalletType::Savings,
            'currency' => 'EUR',
            'status' => WalletStatus::Frozen,
        ]);

        $response = $this->getJson('/api/v1/wallets?currency=usd&type=salary&status=active&per_page=5');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.currency', 'USD')
            ->assertJsonPath('data.0.type', WalletType::Salary->value)
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_it_lists_wallets_for_one_employee(): void
    {
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();

        Wallet::factory()->for($employee)->create(['currency' => 'USD']);
        Wallet::factory()->for($otherEmployee)->create(['currency' => 'USD']);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/wallets");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employee->id);
    }

    public function test_it_shows_a_wallet(): void
    {
        $wallet = Wallet::factory()->create([
            'currency' => 'USD',
            'available_balance' => '125.5000',
            'reserved_balance' => '20.0000',
        ]);

        $response = $this->getJson("/api/v1/wallets/{$wallet->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $wallet->id)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.available_balance', '125.5000')
            ->assertJsonPath('data.reserved_balance', '20.0000');
    }
}
