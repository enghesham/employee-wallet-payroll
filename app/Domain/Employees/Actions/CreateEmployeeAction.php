<?php

namespace App\Domain\Employees\Actions;

use App\Domain\Employees\Enums\EmployeeStatus;
use App\Domain\Employees\Models\Employee;

class CreateEmployeeAction
{
    /**
     * @param  array{name: string, email: string, external_reference: string, status?: string}  $data
     */
    public function execute(array $data): Employee
    {
        return Employee::query()->create([
            'status' => EmployeeStatus::Active->value,
            ...$data,
        ]);
    }
}
