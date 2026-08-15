<?php

namespace App\Services\HR;

use App\Models\HR\HrAttendance;
use App\Models\HR\HrCommission;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrEmployeeComponent;
use App\Models\HR\HrPayrollRun;
use App\Models\HR\HrPayslip;
use App\Models\HR\HrPayslipItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\FcmService;
use Illuminate\Support\Facades\Storage;

class PayrollService
{
    /**
     * Generate a draft payroll run for a period (YYYY-MM).
     * Guard: no duplicate period, previous run must not be draft.
     */
    public static function generateRun(string $period): array
    {
        // Duplicate guard
        if (HrPayrollRun::where('period', $period)->exists()) {
            return ['run' => null, 'error' => "A payroll run for period {$period} already exists."];
        }

        // Previous run must not be in draft (enforces sequential approval)
        $latestRun = HrPayrollRun::orderByDesc('period')->first();
        if ($latestRun && $latestRun->status === 'draft' && $latestRun->period !== $period) {
            return ['run' => null, 'error' => "The previous run ({$latestRun->period}) is still a draft. Approve or delete it before generating a new run."];
        }

        $periodDate = Carbon::parse($period . '-01');
        $label      = $periodDate->format('F Y') . ' Payroll';

        return DB::transaction(function () use ($period, $label, $periodDate) {
            $run = HrPayrollRun::create([
                'period'       => $period,
                'label'        => $label,
                'status'       => 'draft',
                'generated_by' => Auth::guard('hr')->id(),
            ]);

            $employees = HrEmployee::whereIn('status', ['active', 'probation'])
                ->with(['attendance' => function ($q) use ($periodDate) {
                    $q->whereYear('date', $periodDate->year)
                      ->whereMonth('date', $periodDate->month);
                }])
                ->get();

            $totalGross      = 0;
            $totalDeductions = 0;
            $totalNet        = 0;

            foreach ($employees as $employee) {
                $payslip = static::generatePayslip($run, $employee, $period, $periodDate);
                $totalGross      += (float) $payslip->gross_earnings;
                $totalDeductions += (float) $payslip->total_deductions;
                $totalNet        += (float) $payslip->net_pay;
            }

            $run->update([
                'total_gross'      => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net'        => $totalNet,
                'employee_count'   => $employees->count(),
            ]);

            AuditService::log('payroll.run_generated', $run, null, [
                'period'         => $period,
                'total_net'      => $totalNet,
                'employee_count' => $employees->count(),
            ]);

            return ['run' => $run, 'error' => null];
        });
    }

    /**
     * Generate one payslip for an employee within a run.
     */
    private static function generatePayslip(
        HrPayrollRun $run,
        HrEmployee $employee,
        string $period,
        Carbon $periodDate
    ): HrPayslip {
        $baseSalary   = (float) $employee->base_salary;
        $workingDays  = static::standardWorkingDays($periodDate);
        $dailyRate    = $workingDays > 0 ? $baseSalary / $workingDays : 0;

        // Count attendance statuses for the period
        $attendance = $employee->attendance;
        $presentDays = $attendance->whereIn('status', ['present', 'late'])->count();
        $leaveDays   = $attendance->where('status', 'leave')->count();
        $absentDays  = max(0, $workingDays - $presentDays - $leaveDays);

        $attendanceDeduction = round($absentDays * $dailyRate, 2);

        // Collect items
        $items      = [];
        $sortOrder  = 0;
        $grossEarnings    = $baseSalary;
        $totalDeductions  = $attendanceDeduction;

        // Base salary item
        $items[] = [
            'label'      => 'Base Salary',
            'type'       => 'earning',
            'source'     => 'base',
            'amount'     => $baseSalary,
            'note'       => null,
            'sort_order' => $sortOrder++,
        ];

        // Attendance deduction item
        if ($attendanceDeduction > 0) {
            $items[] = [
                'label'      => "Absence Deduction ({$absentDays} day" . ($absentDays > 1 ? 's' : '') . ')',
                'type'       => 'deduction',
                'source'     => 'attendance',
                'amount'     => $attendanceDeduction,
                'note'       => "Daily rate: \${$dailyRate}",
                'sort_order' => $sortOrder++,
            ];
        }

        // Salary components active on last day of period
        $periodEnd = $periodDate->copy()->endOfMonth()->format('Y-m-d');
        $empComponents = HrEmployeeComponent::where('employee_id', $employee->id)
            ->where('effective_from', '<=', $periodEnd)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $periodEnd))
            ->with('component')
            ->get();

        foreach ($empComponents as $ec) {
            $comp = $ec->component;
            if (!$comp || !$comp->is_active) continue;

            // Use override_value if set, else component value
            $rawValue = $ec->override_value ?? $comp->value;
            $amount   = $comp->calculation === 'fixed'
                ? (float) $rawValue
                : round($baseSalary * ((float) $rawValue / 100), 2);

            if ($comp->type === 'earning') {
                $grossEarnings   += $amount;
                $items[] = [
                    'label'      => $comp->name,
                    'type'       => 'earning',
                    'source'     => 'component',
                    'amount'     => $amount,
                    'note'       => $comp->calculation === 'percentage' ? "{$rawValue}% of base" : null,
                    'sort_order' => $sortOrder++,
                ];
            } else {
                $totalDeductions += $amount;
                $items[] = [
                    'label'      => $comp->name,
                    'type'       => 'deduction',
                    'source'     => 'component',
                    'amount'     => $amount,
                    'note'       => $comp->calculation === 'percentage' ? "{$rawValue}% of base" : null,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        // Approved commissions for this period
        $commissions = HrCommission::where('employee_id', $employee->id)
            ->where('period', $period)
            ->where('status', 'approved')
            ->get();

        $commissionTotal = 0;
        foreach ($commissions as $commission) {
            $commissionTotal += (float) $commission->amount;
            $grossEarnings   += (float) $commission->amount;
            $items[] = [
                'label'      => "Commission: {$commission->type_label}",
                'type'       => 'earning',
                'source'     => 'commission',
                'amount'     => (float) $commission->amount,
                'note'       => "Achieved: {$commission->achieved}/{$commission->target}",
                'sort_order' => $sortOrder++,
            ];
        }

        $netPay = $grossEarnings - $totalDeductions;

        $payslip = HrPayslip::create([
            'run_id'               => $run->id,
            'employee_id'          => $employee->id,
            'base_salary'          => $baseSalary,
            'working_days'         => $workingDays,
            'present_days'         => $presentDays,
            'absent_days'          => $absentDays,
            'leave_days'           => $leaveDays,
            'gross_earnings'       => $grossEarnings,
            'total_deductions'     => $totalDeductions,
            'attendance_deduction' => $attendanceDeduction,
            'commission_total'     => $commissionTotal,
            'net_pay'              => max(0, $netPay),
            'payment_status'       => 'unpaid',
        ]);

        // Insert items
        foreach ($items as $item) {
            $item['payslip_id'] = $payslip->id;
            HrPayslipItem::create($item);
        }

        // Mark commissions as included
        if ($commissions->isNotEmpty()) {
            HrCommission::whereIn('id', $commissions->pluck('id'))
                ->update(['status' => 'included', 'payslip_id' => $payslip->id]);
        }

        return $payslip;
    }

    /**
     * Submit run for approval (payroll_officer or hr_manager).
     */
    public static function submitForApproval(HrPayrollRun $run): array
    {
        if (!$run->canSubmit()) {
            return ['error' => "Run cannot be submitted from status: {$run->status}."];
        }

        $before = $run->only('status');

        $run->update([
            'status'       => 'pending_approval',
            'submitted_by' => Auth::guard('hr')->id(),
            'submitted_at' => now(),
        ]);

        AuditService::log('payroll.submitted', $run, $before, ['status' => 'pending_approval']);

        return ['error' => null];
    }

    /**
     * Approve run — ONLY hr_manager.
     */
    public static function approve(HrPayrollRun $run): array
    {
        if (!Auth::guard('hr')->user()->isManager()) {
            return ['error' => 'Only HR Manager can approve payroll runs.'];
        }

        if (!$run->canApprove()) {
            return ['error' => "Run is not in pending_approval state."];
        }

        $before = $run->only('status', 'total_net');

        DB::transaction(function () use ($run) {
            $run->update([
                'status'      => 'approved',
                'approved_by' => Auth::guard('hr')->id(),
                'approved_at' => now(),
            ]);

            AuditService::log('payroll.approved', $run,
                ['status' => 'pending_approval'],
                ['status' => 'approved', 'total_net' => $run->total_net, 'approved_by' => Auth::guard('hr')->user()->name]
            );
        });

        return ['error' => null];
    }

    /**
     * Reject run back to draft with a note.
     */
    public static function reject(HrPayrollRun $run, string $note): array
    {
        if ($run->status !== 'pending_approval') {
            return ['error' => 'Only pending_approval runs can be rejected.'];
        }

        $before = $run->only('status');

        $run->update([
            'status'         => 'draft',
            'rejection_note' => $note,
            'submitted_by'   => null,
            'submitted_at'   => null,
        ]);

        AuditService::log('payroll.rejected', $run, $before, ['status' => 'draft', 'reason' => $note]);

        return ['error' => null];
    }

    /**
     * Mark a single payslip as paid.
     */
    public static function markPayslipPaid(HrPayslip $payslip, string $method, ?string $ref): void
    {
        $before = $payslip->only('payment_status');

        $payslip->update([
            'payment_status' => 'paid',
            'payment_method' => $method,
            'payment_ref'    => $ref,
            'paid_at'        => now(),
        ]);

        AuditService::log('payroll.payslip_paid', $payslip, $before, [
            'payment_status' => 'paid',
            'payment_method' => $method,
            'payment_ref'    => $ref,
        ]);

        // FCM — notify employee their payslip is paid
        try {
            $payslip->loadMissing('employee.user', 'run');
            $token = $payslip->employee?->user?->fcm_token;
            if ($token) {
                $period = $payslip->run?->period ?? 'this period';
                FcmService::sendToToken(
                    fcmToken: $token,
                    title: '💰 Payslip Paid',
                    body: "Your salary for {$period} has been transferred.",
                    data: [
                        'type' => 'payslip_paid',
                        'id'   => (string) $payslip->id,
                    ],
                );
            }
        } catch (\Throwable) {}

        // If all payslips paid, mark run as paid
        $run = $payslip->run;
        if ($run->payslips()->where('payment_status', 'unpaid')->doesntExist()) {
            $run->update(['status' => 'paid', 'paid_at' => now()]);
            AuditService::log('payroll.run_fully_paid', $run);
        }
    }

    /**
     * Bulk mark all payslips in a run as paid (EVC Plus or other).
     */
    public static function bulkMarkPaid(HrPayrollRun $run, string $method, ?string $ref): array
    {
        if (!$run->canMarkPaid()) {
            return ['error' => 'Run must be in approved state to mark as paid.'];
        }

        DB::transaction(function () use ($run, $method, $ref) {
            $before = ['status' => $run->status];

            $run->payslips()->where('payment_status', 'unpaid')->each(function ($payslip) use ($method, $ref) {
                static::markPayslipPaid($payslip, $method, $ref);
            });

            // Ensure run status updated
            $run->refresh();
            if ($run->status !== 'paid') {
                $run->update(['status' => 'paid', 'paid_at' => now()]);
            }

            AuditService::log('payroll.bulk_paid', $run, $before, [
                'status'         => 'paid',
                'payment_method' => $method,
                'payment_ref'    => $ref,
            ]);
        });

        return ['error' => null];
    }

    /**
     * Generate and store PDF for a payslip. Returns the storage path.
     */
    public static function generatePdf(HrPayslip $payslip): string
    {
        $payslip->load(['employee.department', 'employee.position', 'items', 'run']);

        $html = view('hr.payroll.payslip_pdf', compact('payslip'))->render();

        $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait');

        $path = 'hr/payslips/payslip-' . $payslip->run->period . '-' . $payslip->employee->employee_no . '.pdf';

        // Ensure directory exists before writing
        $dir = dirname(Storage::disk('public')->path($path));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        Storage::disk('public')->put($path, $pdf->output());

        $payslip->update(['pdf_path' => $path]);

        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** Standard working days in a month (Mon–Fri, 4–5 weeks) */
    private static function standardWorkingDays(Carbon $periodDate): int
    {
        $start = $periodDate->copy()->startOfMonth();
        $end   = $periodDate->copy()->endOfMonth();
        $days  = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (!$d->isWeekend()) $days++;
        }

        return $days;
    }
}
