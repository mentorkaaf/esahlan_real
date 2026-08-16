<?php

namespace App\Services\HR;

use App\Models\HR\HrAttendance;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrEmployeeShift;
use App\Models\HR\HrHoliday;
use App\Models\HR\HrShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Mark or update attendance for one employee on one date.
     *
     * @param  HrEmployee  $employee
     * @param  string      $date        Y-m-d
     * @param  string|null $checkIn     H:i or H:i:s
     * @param  string|null $checkOut    H:i or H:i:s
     * @param  string|null $note
     * @param  bool        $isManual
     * @return HrAttendance
     */
    public static function markAttendance(
        HrEmployee $employee,
        string $date,
        ?string $checkIn,
        ?string $checkOut,
        ?string $note = null,
        bool $isManual = false,
        ?string $forceStatus = null
    ): HrAttendance {
        $d = Carbon::parse($date);

        // resolve shift active on this date
        $shift = static::resolveShift($employee, $d);

        // compute status + late/overtime
        [$status, $lateMin, $otMin] = static::computeStatus(
            $shift, $checkIn, $checkOut, $d, $forceStatus
        );

        $correctedBy = $isManual && Auth::guard('hr')->check()
            ? Auth::guard('hr')->id()
            : null;

        $attendance = HrAttendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            [
                'shift_id'         => $shift?->id,
                'check_in'         => $checkIn,
                'check_out'        => $checkOut,
                'status'           => $status,
                'late_minutes'     => $lateMin,
                'overtime_minutes' => $otMin,
                'note'             => $note,
                'is_manual'        => $isManual,
                'corrected_by'     => $correctedBy,
            ]
        );

        return $attendance;
    }

    /**
     * Bulk-mark all active employees as present for a date.
     */
    public static function bulkMarkPresent(string $date, HrShift $shift): int
    {
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])->get();
        $count = 0;

        foreach ($employees as $emp) {
            static::markAttendance(
                $emp, $date,
                $shift->start_time, $shift->end_time,
                null, true, 'present'
            );
            $count++;
        }

        return $count;
    }

    /**
     * Import from CSV rows: [employee_no, date, check_in, check_out]
     * Returns ['imported' => N, 'errors' => [...]]
     */
    public static function importCsv(array $rows): array
    {
        $imported = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // 1-indexed, row 1 = header
            $emp = HrEmployee::where('employee_no', trim($row['employee_no'] ?? ''))->first();

            if (!$emp) {
                $errors[] = "Row $rowNum: Employee '{$row['employee_no']}' not found.";
                continue;
            }

            if (empty($row['date']) || !strtotime($row['date'])) {
                $errors[] = "Row $rowNum: Invalid date '{$row['date']}'.";
                continue;
            }

            try {
                static::markAttendance(
                    $emp,
                    Carbon::parse($row['date'])->format('Y-m-d'),
                    $row['check_in'] ?? null,
                    $row['check_out'] ?? null,
                    'CSV import',
                    true
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Row $rowNum: {$e->getMessage()}";
            }
        }

        return compact('imported', 'errors');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function resolveShift(HrEmployee $employee, Carbon $date): ?HrShift
    {
        $es = HrEmployeeShift::where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->with('shift')
            ->first();

        return $es?->shift;
    }

    private static function computeStatus(
        ?HrShift $shift,
        ?string $checkIn,
        ?string $checkOut,
        Carbon $date,
        ?string $forceStatus
    ): array {
        // Manual override always wins — HR can mark any status on any day
        if ($forceStatus) {
            return [$forceStatus, 0, 0];
        }

        // weekend
        if ($date->isWeekend()) {
            return ['weekend', 0, 0];
        }

        // holiday
        $holidays = HrHoliday::datesForYear($date->year);
        if (in_array($date->format('Y-m-d'), $holidays)) {
            return ['holiday', 0, 0];
        }

        if (!$checkIn) {
            return ['absent', 0, 0];
        }

        if (!$shift) {
            return ['present', 0, 0];
        }

        // late calculation
        $shiftStart = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->start_time);
        $inTime     = Carbon::parse($date->format('Y-m-d') . ' ' . $checkIn);
        $lateMin    = max(0, $inTime->diffInMinutes($shiftStart, false) * -1);
        $isLate     = $lateMin > $shift->grace_minutes;

        // overtime
        $otMin = 0;
        if ($checkOut) {
            $shiftEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->end_time);
            $outTime  = Carbon::parse($date->format('Y-m-d') . ' ' . $checkOut);
            $otMin    = max(0, $outTime->diffInMinutes($shiftEnd, false));
        }

        $status = $isLate ? 'late' : 'present';

        return [$status, (int) $lateMin, (int) $otMin];
    }
}
