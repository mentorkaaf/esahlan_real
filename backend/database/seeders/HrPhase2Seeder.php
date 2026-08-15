<?php

namespace Database\Seeders;

use App\Models\HR\HrAttendance;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrEmployeeShift;
use App\Models\HR\HrHoliday;
use App\Models\HR\HrLeaveBalance;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrLeaveType;
use App\Models\HR\HrShift;
use App\Models\HR\HrStaff;
use App\Services\HR\AttendanceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class HrPhase2Seeder extends Seeder
{
    public function run(): void
    {
        // ── Shifts ────────────────────────────────────────────────────────────
        $officeShift = HrShift::firstOrCreate(['name' => 'Office Hours'], [
            'start_time'    => '08:00:00',
            'end_time'      => '16:00:00',
            'grace_minutes' => 15,
            'break_minutes' => 60,
            'is_active'     => true,
        ]);

        $fieldShift = HrShift::firstOrCreate(['name' => 'Field Hours'], [
            'start_time'    => '09:00:00',
            'end_time'      => '17:00:00',
            'grace_minutes' => 30,
            'break_minutes' => 60,
            'is_active'     => true,
        ]);

        // ── Holidays (Somali/Islamic public holidays) ─────────────────────────
        $holidays = [
            ['name' => 'New Year\'s Day',         'date' => '2026-01-01', 'recurring' => true],
            ['name' => 'Labour Day',               'date' => '2026-05-01', 'recurring' => true],
            ['name' => 'Independence Day',         'date' => '2026-07-01', 'recurring' => true],
            ['name' => 'Foundation Day',           'date' => '2026-10-21', 'recurring' => true],
        ];

        foreach ($holidays as $h) {
            HrHoliday::firstOrCreate(['date' => $h['date']], [
                'name'         => $h['name'],
                'is_recurring' => $h['recurring'],
            ]);
        }

        // ── Leave Types ───────────────────────────────────────────────────────
        $annual = HrLeaveType::firstOrCreate(['code' => 'ANNUAL'], [
            'name'               => 'Annual Leave',
            'days_per_year'      => 21,
            'is_paid'            => true,
            'requires_document'  => false,
            'is_active'          => true,
        ]);

        $sick = HrLeaveType::firstOrCreate(['code' => 'SICK'], [
            'name'               => 'Sick Leave',
            'days_per_year'      => 10,
            'is_paid'            => true,
            'requires_document'  => true,
            'is_active'          => true,
        ]);

        $maternity = HrLeaveType::firstOrCreate(['code' => 'MAT'], [
            'name'               => 'Maternity Leave',
            'days_per_year'      => 90,
            'is_paid'            => true,
            'requires_document'  => false,
            'is_active'          => true,
        ]);

        $unpaid = HrLeaveType::firstOrCreate(['code' => 'UNPAID'], [
            'name'               => 'Unpaid Leave',
            'days_per_year'      => 0,   // unlimited
            'is_paid'            => false,
            'requires_document'  => false,
            'is_active'          => true,
        ]);

        // ── Assign shifts to all employees ────────────────────────────────────
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])->get();

        foreach ($employees as $i => $emp) {
            $shift = ($i % 3 === 0) ? $fieldShift : $officeShift;
            HrEmployeeShift::firstOrCreate(
                ['employee_id' => $emp->id, 'effective_from' => '2026-01-01'],
                ['shift_id' => $shift->id, 'effective_to' => null]
            );
        }

        // ── Current-month attendance for all employees ─────────────────────
        $monthStart = Carbon::now()->startOfMonth();
        $today      = Carbon::today();
        $period     = CarbonPeriod::create($monthStart, $today);
        $manager    = HrStaff::where('role', 'hr_manager')->first();

        $checkInPatterns = [
            '07:58', '08:00', '08:05', '08:15', '08:30', '08:45', '09:10',
        ];

        foreach ($employees as $empIdx => $emp) {
            foreach ($period as $day) {
                $dateStr = $day->format('Y-m-d');

                // Skip weekends silently — AttendanceService handles it but skip to avoid noise
                if ($day->isWeekend()) continue;

                // Existing record? skip
                if (HrAttendance::where('employee_id', $emp->id)->where('date', $dateStr)->exists()) {
                    continue;
                }

                // Simulate realistic check-in
                $pattern = $checkInPatterns[($empIdx + $day->day) % count($checkInPatterns)];
                $checkOut = '16:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT);

                AttendanceService::markAttendance(
                    $emp, $dateStr, $pattern, $checkOut, null, false
                );
            }
        }

        // ── Leave balances for current year ───────────────────────────────────
        $year = now()->year;
        foreach ($employees as $emp) {
            foreach ([$annual, $sick, $maternity] as $lt) {
                HrLeaveBalance::firstOrCreate(
                    ['employee_id' => $emp->id, 'leave_type_id' => $lt->id, 'year' => $year],
                    ['allocated' => $lt->days_per_year, 'used' => 0, 'pending' => 0]
                );
            }
        }

        // ── 4 Sample leave requests (mixed states) ────────────────────────────
        $emp1 = $employees->get(0);  // Ahmed Mohamed
        $emp2 = $employees->get(1);  // Faadumo Hassan
        $emp3 = $employees->get(2);  // Abdi Ali
        $emp4 = $employees->get(3);  // Hodan Ibrahim
        $hrManager = HrStaff::where('role', 'hr_manager')->first();

        $leaveRequests = [
            // Pending
            [
                'employee'      => $emp3,
                'leave_type'    => $annual,
                'start'         => now()->addDays(5)->format('Y-m-d'),
                'end'           => now()->addDays(9)->format('Y-m-d'),
                'reason'        => 'Family vacation',
                'status'        => 'pending',
            ],
            // Pending
            [
                'employee'      => $emp4,
                'leave_type'    => $sick,
                'start'         => now()->subDays(2)->format('Y-m-d'),
                'end'           => now()->format('Y-m-d'),
                'reason'        => 'Medical appointment',
                'status'        => 'pending',
            ],
            // Approved
            [
                'employee'      => $emp1,
                'leave_type'    => $annual,
                'start'         => now()->subDays(14)->format('Y-m-d'),
                'end'           => now()->subDays(11)->format('Y-m-d'),
                'reason'        => 'Personal trip',
                'status'        => 'approved',
                'decided_by'    => $hrManager?->id,
                'decision_note' => 'Approved. Enjoy your break.',
                'decided_at'    => now()->subDays(15),
            ],
            // Rejected
            [
                'employee'      => $emp2,
                'leave_type'    => $unpaid,
                'start'         => now()->addDays(1)->format('Y-m-d'),
                'end'           => now()->addDays(10)->format('Y-m-d'),
                'reason'        => 'Extended personal leave',
                'status'        => 'rejected',
                'decided_by'    => $hrManager?->id,
                'decision_note' => 'Operations cannot support this absence during peak season.',
                'decided_at'    => now()->subDays(1),
            ],
        ];

        foreach ($leaveRequests as $lr) {
            $emp      = $lr['employee'];
            $lt       = $lr['leave_type'];
            $workDays = \App\Services\HR\LeaveService::countWorkingDays($lr['start'], $lr['end']);

            $exists = HrLeaveRequest::where('employee_id', $emp->id)
                ->where('start_date', $lr['start'])
                ->exists();
            if ($exists || !$emp) continue;

            HrLeaveRequest::create([
                'employee_id'   => $emp->id,
                'leave_type_id' => $lt->id,
                'start_date'    => $lr['start'],
                'end_date'      => $lr['end'],
                'working_days'  => $workDays,
                'reason'        => $lr['reason'],
                'status'        => $lr['status'],
                'decided_by'    => $lr['decided_by'] ?? null,
                'decision_note' => $lr['decision_note'] ?? null,
                'decided_at'    => $lr['decided_at'] ?? null,
            ]);

            // Update balances for approved
            if ($lr['status'] === 'approved' && $lt->days_per_year > 0) {
                HrLeaveBalance::where('employee_id', $emp->id)
                    ->where('leave_type_id', $lt->id)
                    ->where('year', now()->year)
                    ->increment('used', $workDays);
            }
        }

        $this->command->info('Phase 2 seeder complete: 2 shifts, ' . count($holidays) . ' holidays, 4 leave types, attendance seeded, 4 leave requests.');
    }
}
