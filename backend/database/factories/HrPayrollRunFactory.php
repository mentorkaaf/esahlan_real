<?php

namespace Database\Factories;

use App\Models\HR\HrPayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrPayrollRunFactory extends Factory
{
    protected $model = HrPayrollRun::class;

    private static int $offset = 0;

    public function definition(): array
    {
        $period = now()->subMonths(self::$offset++)->format('Y-m');

        return [
            'period'          => $period,
            'label'           => now()->format('F Y') . ' Payroll',
            'status'          => 'approved',
            'total_gross'     => 0,
            'total_deductions'=> 0,
            'total_net'       => 0,
            'employee_count'  => 0,
        ];
    }
}
