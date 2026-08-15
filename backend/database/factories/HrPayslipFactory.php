<?php

namespace Database\Factories;

use App\Models\HR\HrPayslip;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrPayslipFactory extends Factory
{
    protected $model = HrPayslip::class;

    public function definition(): array
    {
        return [
            'run_id'               => \App\Models\HR\HrPayrollRun::factory(),
            'employee_id'          => \App\Models\HR\HrEmployee::factory(),
            'base_salary'          => 1500.00,
            'working_days'         => 26,
            'present_days'         => 26,
            'absent_days'          => 0,
            'leave_days'           => 0,
            'gross_earnings'       => 1500.00,
            'total_deductions'     => 200.00,
            'attendance_deduction' => 0,
            'commission_total'     => 0,
            'net_pay'              => 1300.00,
            'payment_status'       => 'unpaid',
        ];
    }
}
