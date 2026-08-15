<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrDisciplinaryCase;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrWarning;
use App\Services\HR\DisciplineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HrDisciplinaryController extends Controller
{
    public function index()
    {
        $cases = HrDisciplinaryCase::with(['employee.department','openedBy'])
            ->orderByRaw("FIELD(status,'open','investigating','closed')")
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('hr.discipline.index', compact('cases'));
    }

    public function create()
    {
        $employees = HrEmployee::whereIn('status',['active','probation'])->orderBy('first_name')->get();
        return view('hr.discipline.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'category'    => 'required|in:attendance,conduct,performance,other',
            'severity'    => 'required|in:minor,moderate,major',
            'title'       => 'required|string|max:150',
            'description' => 'required|string|max:3000',
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);
        $case     = DisciplineService::openCase($employee, $data);

        return redirect()->route('hr.discipline.show', $case)
            ->with('success', 'Disciplinary case opened.');
    }

    public function show(HrDisciplinaryCase $case)
    {
        $case->load(['employee.department','openedBy','closedBy','warnings.issuedBy']);
        $auditLogs = \App\Models\HR\HrAuditLog::where('subject_type', HrDisciplinaryCase::class)
            ->where('subject_id', $case->id)
            ->orderBy('created_at')
            ->get();

        return view('hr.discipline.show', compact('case', 'auditLogs'));
    }

    /** Update investigation notes */
    public function investigate(Request $request, HrDisciplinaryCase $case)
    {
        if ($case->status === 'closed') return back()->with('error', 'Case is already closed.');

        $data = $request->validate(['investigation_notes' => 'required|string|max:5000']);
        DisciplineService::updateInvestigation($case, $data['investigation_notes']);

        return back()->with('success', 'Investigation notes updated.');
    }

    /** Close case with outcome */
    public function close(Request $request, HrDisciplinaryCase $case)
    {
        if ($case->status === 'closed') return back()->with('error', 'Already closed.');

        $data = $request->validate([
            'outcome' => 'required|in:verbal_warning,written_warning,suspension,termination,dismissed',
            'close_note' => 'nullable|string|max:2000',
        ]);

        // Termination requires manager
        if ($data['outcome'] === 'termination' && !Auth::guard('hr')->user()->isManager()) {
            return back()->with('error', 'Only HR Manager can close a case with termination outcome.');
        }

        DisciplineService::closeCase($case, $data['outcome'], $data['close_note'] ?? null);

        return back()->with('success', "Case closed with outcome: {$case->fresh()->getOutcomeLabel()}.");
    }

    /** Acknowledge a warning (HR marks after employee signs) */
    public function acknowledge(HrWarning $warning)
    {
        $warning->update(['acknowledged_at' => now()]);
        \App\Services\HR\AuditService::log('warning.acknowledged', $warning);

        return back()->with('success', 'Warning acknowledged.');
    }

    /** Download warning PDF */
    public function warningPdf(HrWarning $warning)
    {
        if (!$warning->pdf_path) {
            DisciplineService::generateWarningPdf($warning);
            $warning->refresh();
        }

        return Storage::disk('public')->download(
            $warning->pdf_path,
            'warning-' . $warning->id . '.pdf'
        );
    }
}
