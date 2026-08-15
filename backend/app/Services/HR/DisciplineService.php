<?php

namespace App\Services\HR;

use App\Models\HR\HrDisciplinaryCase;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrWarning;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DisciplineService
{
    /** Open a new disciplinary case */
    public static function openCase(HrEmployee $employee, array $data): HrDisciplinaryCase
    {
        $case = HrDisciplinaryCase::create([
            'employee_id' => $employee->id,
            'opened_by'   => Auth::guard('hr')->id(),
            'category'    => $data['category'],
            'severity'    => $data['severity'],
            'title'       => $data['title'],
            'description' => $data['description'],
            'status'      => 'open',
        ]);

        AuditService::log('discipline.opened', $case, [], [
            'severity' => $data['severity'],
            'category' => $data['category'],
        ]);

        return $case;
    }

    /** Update investigation notes (moves to 'investigating' if open) */
    public static function updateInvestigation(HrDisciplinaryCase $case, string $notes): void
    {
        $wasOpen = $case->status === 'open';

        $case->update([
            'investigation_notes' => $notes,
            'status'              => 'investigating',
        ]);

        AuditService::log('discipline.investigating', $case, ['status' => $wasOpen ? 'open' : 'investigating'], ['status' => 'investigating']);
    }

    /**
     * Close case with outcome.
     * verbal_warning / written_warning → creates HrWarning + PDF.
     * termination → sets employee status=terminated, end-dates contract.
     */
    public static function closeCase(HrDisciplinaryCase $case, string $outcome, ?string $note = null): void
    {
        DB::transaction(function () use ($case, $outcome, $note) {
            $before = ['status' => $case->status, 'outcome' => null];

            $case->update([
                'status'    => 'closed',
                'outcome'   => $outcome,
                'closed_by' => Auth::guard('hr')->id(),
                'closed_at' => now(),
                'investigation_notes' => $note ?? $case->investigation_notes,
            ]);

            AuditService::log('discipline.closed', $case, $before, [
                'status'  => 'closed',
                'outcome' => $outcome,
                'note'    => $note,
            ]);

            // Create warning letter
            if (in_array($outcome, ['verbal_warning', 'written_warning'])) {
                $type    = $outcome === 'verbal_warning' ? 'verbal' : 'written';
                $warning = HrWarning::create([
                    'case_id'     => $case->id,
                    'employee_id' => $case->employee_id,
                    'issued_by'   => Auth::guard('hr')->id(),
                    'type'        => $type,
                    'title'       => ucfirst($type) . ' Warning — ' . $case->title,
                    'body'        => static::buildWarningBody($case, $type),
                ]);

                // Generate PDF
                if ($type === 'written') {
                    static::generateWarningPdf($warning);
                }
            }

            // Termination — set employee status + end-date active contracts
            if ($outcome === 'termination') {
                $emp = $case->employee;
                $emp->update(['status' => 'terminated']);

                // End-date active contracts
                $emp->contracts()->where('status', 'active')->update([
                    'status'   => 'terminated',
                    'end_date' => today()->toDateString(),
                ]);

                AuditService::log('employee.terminated', $emp, ['status' => 'active'], [
                    'status'      => 'terminated',
                    'case_id'     => $case->id,
                    'terminated_by' => Auth::guard('hr')->id(),
                ]);
            }
        });
    }

    /** Generate warning letter PDF */
    public static function generateWarningPdf(HrWarning $warning): string
    {
        $warning->load(['employee.department', 'issuedBy', 'case_']);

        $pdf  = Pdf::loadView('hr.discipline.warning_pdf', compact('warning'));
        $path = "hr/warnings/warning-{$warning->id}.pdf";

        Storage::disk('public')->put($path, $pdf->output());
        $warning->update(['pdf_path' => $path]);

        return $path;
    }

    /** Build warning letter body text */
    private static function buildWarningBody(HrDisciplinaryCase $case, string $type): string
    {
        $emp = $case->employee;
        return "Dear {$emp->full_name},\n\n"
            . "This letter serves as a formal " . ($type === 'verbal' ? 'verbal' : 'written') . " warning regarding the following matter:\n\n"
            . "**Subject:** {$case->title}\n\n"
            . "**Description:**\n{$case->description}\n\n"
            . ($case->investigation_notes ? "**Investigation Summary:**\n{$case->investigation_notes}\n\n" : '')
            . "You are expected to immediately correct the above behaviour. "
            . "Failure to do so may result in further disciplinary action, up to and including termination of employment.\n\n"
            . "Please acknowledge receipt of this " . ($type === 'verbal' ? 'verbal warning notice' : 'written warning') . " by signing below.\n\n"
            . "Yours sincerely,\neSahlan Human Resources";
    }
}
