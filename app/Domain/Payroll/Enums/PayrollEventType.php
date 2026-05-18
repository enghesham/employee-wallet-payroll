<?php

namespace App\Domain\Payroll\Enums;

enum PayrollEventType: string
{
    case EmployeeOnboarded = 'employee.onboarded';
    case EmployeeStatusChanged = 'employee.status_changed';
    case SalaryRunCompleted = 'salary_run.completed';
}
