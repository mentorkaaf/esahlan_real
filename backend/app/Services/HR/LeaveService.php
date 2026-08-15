<?php

namespace App\Services\HR;

use App\Models\HR\HrAttendance;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrHoliday;
use App\Models\HR\HrLeaveBalance;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrLeaveType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    /**
     * Count working days between two dates, excluding weekends and holidays.
     */
    public static function countWorkingDays(string $startDate, string $endDate): float
    {
        $holidays = HrHoliday::datesForYear(Carbon::parse($startDate)->year);
        $period   = CarbonPeriod::create($startDate, $endDate);
        $days     = 0;

        foreach ($period as $day) {
            if ($day->isWeekend()) continue;
            if (in_array($day->format('Y-m-d'), $holidays)) continue;
            $days++;
        }

        return (float) $days;
    }

    /**
     * Submit a new leave request.
     * Validates balance, no overlapping approved leave.
     * Returns ['request' => HrLeaveRequest, 'error' => string|null]
     */
    public static function submitRequest(
        HrEmployee $employee,
        HrLeaveType $leaveType,
        string $startDate,
        string $endDate,
        ?string $reason,
        ?string $documentPath = null
    ): array {
        $workingDays = static::countWorkingDays($startDate, $endDate);
        $year        = Carbon::parse($startDate)->year;

        // Check balance (skip for unlimited types: days_per_year == 0 means unlimited)
        if ($leaveType->days_per_year > 0) {
            $balance = HrLeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                ['allocated' => $leaveType->days_per_year, 'used' => 0, 'pending' => 0]
            );

            if ($workingDays > $balance->available) {
                return [
                    'request' => null,
                    'error'   => "Insufficient balance. Available: {$balance->available} days, requested: {$workingDays} days.",
                ];
            }
        }

        // Check overlap with existing approved/pending leave
        $overlap = HrLeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->where(fn($q) =>
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date',   [$startDate, $endDate])
                  ->orWhere(fn($q2) => $q2->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate))
            )
            ->exists();

        if ($overlap) {
            return [
                'request' => null,
                'error'   => 'Leave request overlaps with an existing approved or pending request.',
            ];
        }

        return DB::transaction(function () use ($employee, $leaveType, $startDate, $endDate, $workingDays, $reason, $documentPath, $year) {
            $request = HrLeaveRequest::create([
                'employee_id'   => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'working_days'  => $workingDays,
                'reason'        => $reason,
                'document_path' => $documentPath,
                'status'        => 'pending',
            ]);

            // Reserve balance as pending
            if ($leaveType->days_per_year > 0) {
                HrLeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->increment('pending', $workingDays);
            }

            return ['request' => $request, 'error' => null];
        });
    }

    /**
     * Approve a leave request.
     * Creates attendance rows with status='leave' for the range.
     */
    public static function approve(HrLeaveRequest $request, ?string $note): void
    {
        DB::transaction(function () use ($request, $note) {
            $year = $request->start_date->year;

            $request->update([
                'status'       => 'approved',
                'decided_by'   => Auth::guard('hr')->id(),
                'decision_note'=> $note,
                'decided_at'   => now(),
            ]);

            // Move from pending → used in balance
            if ($request->leaveType->days_per_year > 0) {
                HrLeaveBalance::where('employee_id', $request->employee_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $year)
                    ->decrement('pending', $request->working_days);

                HrLeaveBalance::where('employee_id', $request->employee_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $year)
                    ->increment('used', $request->working_days);
            }

            // Create attendance rows for each working day in range
            $holidays = HrHoliday::datesForYear($year);
            $period   = CarbonPeriod::create($request->start_date, $request->end_date);

            foreach ($period as $day) {
                if ($day->isWeekend()) continue;
                if (in_array($day->format('Y-m-d'), $holidays)) continue;

                HrAttendance::updateOrCreate(
                    ['employee_id' => $request->employee_id, 'date' => $day->format('Y-m-d')],
                    ['status' => 'leave', 'note' => $request->leaveType->name . ' leave', 'is_manual' => true]
                );
            }

            AuditService::log('leave.approved', $request);
        });
    }

    /**
     * Reject a leave request.
     */
    public static function reject(HrLeaveRequest $request, ?string $note): void
    {
        DB::transaction(function () use ($request, $note) {
            $year = $request->start_date->year;

            // Release pending balance
            if ($request->leaveType->days_per_year > 0 && $request->status === 'pending') {
                HrLeaveBalance::where('employee_id', $request->employee_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $year)
                    ->decrement('pending', $request->working_days);
            }

            $request->update([
                'status'        => 'rejected',
                'decided_by'    => Auth::guard('hr')->id(),
                'decision_note' => $note,
                'decided_at'    => now(),
            ]);

            AuditService::log('leave.rejected', $request);
        });
    }

    /**
     * Initialize leave balances for all active employees for a year.
     * Called by artisan command hr:init-leave-balances {year}
     */
    public static function initBalancesForYear(int $year): int
    {
        $employees  = HrEmployee::whereIn('status', ['active', 'probation'])->get();
        $leaveTypes = HrLeaveType::where('is_active', true)
            ->where('days_per_year', '>', 0)
            ->get();
        $created = 0;

        foreach ($employees as $emp) {
            foreach ($leaveTypes as $lt) {
                $existed = HrLeaveBalance::where([
                    'employee_id'   => $emp->id,
                    'leave_type_id' => $lt->id,
                    'year'          => $year,
                ])->exists();

                if (!$existed) {
                    HrLeaveBalance::create([
                        'employee_id'   => $emp->id,
                        'leave_type_id' => $lt->id,
                        'year'          => $year,
                        'allocated'     => $lt->days_per_year,
                        'used'          => 0,
                        'pending'       => 0,
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }
}
