<?php

namespace App\Services\HR;

use App\Models\HR\HrApplicant;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrJobPosting;
use App\Models\HR\HrOnboardingTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecruitmentService
{
    /** Default onboarding checklist template */
    private static array $onboardingTemplate = [
        ['title' => 'ID Copy Submitted',        'description' => 'Collect and file national ID copy.',            'days' => 1],
        ['title' => 'Contract Signed',           'description' => 'Employment contract reviewed and signed.',      'days' => 1],
        ['title' => 'eSahlan App Account Linked','description' => 'Employee linked to their app account.',         'days' => 3],
        ['title' => 'Equipment Issued',          'description' => 'Laptop / phone / access card issued.',          'days' => 3],
        ['title' => 'System Access Setup',       'description' => 'Email, Slack, internal tools configured.',      'days' => 5],
        ['title' => 'Orientation Completed',     'description' => 'HR orientation session attended.',              'days' => 7],
    ];

    /**
     * Move applicant to next stage (or a specific stage).
     * Audits the transition.
     */
    public static function moveStage(HrApplicant $applicant, string $toStage): void
    {
        $from = $applicant->stage;

        $applicant->update([
            'stage'            => $toStage,
            'stage_changed_at' => now(),
        ]);

        AuditService::log('applicant.stage_changed', $applicant, ['stage' => $from], ['stage' => $toStage]);
    }

    /**
     * Hire applicant → create HrEmployee + generate onboarding checklist.
     * Returns the new employee.
     */
    public static function hire(HrApplicant $applicant): HrEmployee
    {
        return DB::transaction(function () use ($applicant) {
            $posting = $applicant->jobPosting;

            // Create employee skeleton pre-filled from applicant
            $employee = HrEmployee::create([
                'first_name'    => explode(' ', trim($applicant->name))[0],
                'last_name'     => implode(' ', array_slice(explode(' ', trim($applicant->name)), 1)) ?: '-',
                'email'         => $applicant->email,
                'phone'         => $applicant->phone,
                'department_id' => $posting->department_id,
                'position_id'   => $posting->position_id,
                'status'        => 'probation',
                'join_date'     => today(),
                'base_salary'   => 0, // HR to fill in
            ]);

            // Mark applicant hired
            $applicant->update([
                'is_hired'    => true,
                'employee_id' => $employee->id,
                'stage'       => 'hired',
                'stage_changed_at' => now(),
            ]);

            // Increment posting hired_count; close if full
            $posting->increment('hired_count');
            if ($posting->hired_count >= $posting->openings) {
                $posting->update(['status' => 'closed']);
            }

            // Generate onboarding tasks
            foreach (static::$onboardingTemplate as $idx => $task) {
                HrOnboardingTask::create([
                    'employee_id' => $employee->id,
                    'title'       => $task['title'],
                    'description' => $task['description'],
                    'due_date'    => today()->addDays($task['days']),
                    'sort_order'  => $idx,
                ]);
            }

            AuditService::log('applicant.hired', $applicant, [], ['employee_id' => $employee->id]);

            return $employee;
        });
    }
}
