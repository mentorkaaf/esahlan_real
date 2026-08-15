<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAuditLog;
use App\Models\HR\HrPayrollRun;
use App\Models\HR\HrPayslip;
use App\Services\HR\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HrPayrollController extends Controller
{
    /** Runs list */
    public function index()
    {
        $runs = HrPayrollRun::with(['generator', 'approver'])
            ->orderByDesc('period')
            ->paginate(20);

        return view('hr.payroll.index', compact('runs'));
    }

    /** Generate new draft run */
    public function generate(Request $request)
    {
        $this->requirePayrollAccess();

        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $result = PayrollService::generateRun($data['period']);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('hr.payroll.show', $result['run'])
            ->with('success', "Payroll run for {$result['run']->label} generated successfully.");
    }

    /** Run detail — payslip table */
    public function show(HrPayrollRun $payroll)
    {
        $payroll->load(['payslips.employee.department', 'payslips.employee.position',
                        'generator', 'submitter', 'approver']);

        $auditLogs = HrAuditLog::where('subject_type', HrPayrollRun::class)
            ->where('subject_id', $payroll->id)
            ->orderBy('created_at')
            ->get();

        return view('hr.payroll.show', compact('payroll', 'auditLogs'));
    }

    /** Submit for approval */
    public function submit(HrPayrollRun $payroll)
    {
        $this->requirePayrollAccess();

        $result = PayrollService::submitForApproval($payroll);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Run submitted for approval.');
    }

    /** Approve — hr_manager only */
    public function approve(HrPayrollRun $payroll)
    {
        $result = PayrollService::approve($payroll);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Payroll run approved.');
    }

    /** Reject back to draft */
    public function reject(Request $request, HrPayrollRun $payroll)
    {
        $data = $request->validate(['note' => 'required|string|max:1000']);

        $result = PayrollService::reject($payroll, $data['note']);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Payroll run returned to draft.');
    }

    /** Delete draft run */
    public function destroy(HrPayrollRun $payroll)
    {
        $this->requireManager();

        if (!$payroll->isEditable()) {
            return back()->with('error', 'Only draft runs can be deleted.');
        }

        $payroll->delete();

        return redirect()->route('hr.payroll.index')
            ->with('success', 'Draft run deleted.');
    }

    /** Payslip drill-in */
    public function payslip(HrPayslip $payslip)
    {
        $payslip->load(['employee.department', 'employee.position', 'items', 'run', 'commissions']);

        return view('hr.payroll.payslip', compact('payslip'));
    }

    /** Download/generate payslip PDF */
    public function payslipPdf(HrPayslip $payslip)
    {
        if (!$payslip->pdf_path || !Storage::disk('public')->exists($payslip->pdf_path)) {
            PayrollService::generatePdf($payslip);
            $payslip->refresh();
        }

        return Storage::disk('public')->download($payslip->pdf_path,
            'payslip-' . $payslip->run->period . '-' . $payslip->employee->employee_no . '.pdf'
        );
    }

    /** Mark single payslip paid */
    public function markPaid(Request $request, HrPayslip $payslip)
    {
        $this->requireManager();

        if ($payslip->run->status !== 'approved') {
            return back()->with('error', 'Run must be approved before marking payment.');
        }

        $data = $request->validate([
            'payment_method' => 'required|string|max:50',
            'payment_ref'    => 'nullable|string|max:100',
        ]);

        PayrollService::markPayslipPaid($payslip, $data['payment_method'], $data['payment_ref'] ?? null);

        return back()->with('success', 'Payslip marked as paid.');
    }

    /** Bulk mark all payslips paid */
    public function bulkMarkPaid(Request $request, HrPayrollRun $payroll)
    {
        $this->requireManager();

        $data = $request->validate([
            'payment_method' => 'required|string|max:50',
            'payment_ref'    => 'nullable|string|max:100',
        ]);

        $result = PayrollService::bulkMarkPaid($payroll, $data['payment_method'], $data['payment_ref'] ?? null);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'All payslips marked as paid.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function requirePayrollAccess(): void
    {
        $user = Auth::guard('hr')->user();
        if (!$user->canDo('generate_payroll')) abort(403);
    }

    private function requireManager(): void
    {
        if (!Auth::guard('hr')->user()->isManager()) abort(403);
    }
}
