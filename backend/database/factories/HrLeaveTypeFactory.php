<?php

namespace Database\Factories;

use App\Models\HR\HrLeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrLeaveTypeFactory extends Factory
{
    protected $model = HrLeaveType::class;

    private static array $names = ['Annual', 'Sick', 'Maternity', 'Paternity', 'Emergency', 'Study', 'Compassionate'];
    private static int $idx = 0;

    public function definition(): array
    {
        $name = self::$names[self::$idx++ % count(self::$names)] . ' Leave';

        return [
            'name'              => $name,
            'code'              => strtoupper(substr(str_replace(' ', '', $name), 0, 4)) . self::$idx,
            'days_per_year'     => 15,
            'is_paid'           => true,
            'requires_document' => false,
            'is_active'         => true,
        ];
    }
}
