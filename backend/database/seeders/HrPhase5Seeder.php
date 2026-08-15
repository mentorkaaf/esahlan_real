<?php

namespace Database\Seeders;

use App\Models\HR\HrAnnouncement;
use App\Models\HR\HrDisciplinaryCase;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrStaff;
use App\Models\HR\HrWarning;
use App\Services\HR\AuditService;
use App\Services\HR\DisciplineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class HrPhase5Seeder extends Seeder
{
    public function run(): void
    {
        $manager  = HrStaff::where('role','hr_manager')->first();
        $officer  = HrStaff::where('role','hr_officer')->first()
                    ?? $manager;

        // Fake auth so AuditService resolves actor correctly
        Auth::guard('hr')->setUser($manager);

        $employees = HrEmployee::whereIn('status',['active','probation'])->get();

        if ($employees->count() < 3) {
            $this->command->warn('Need at least 3 employees. Run HrPhase1Seeder first.');
            return;
        }

        // ── Case 1: Written warning, closed ──────────────────────────────────
        $emp1 = $employees->get(0);
        $case1 = DisciplineService::openCase($emp1, [
            'category'    => 'attendance',
            'severity'    => 'moderate',
            'title'       => 'Repeated Late Arrivals — July 2026',
            'description' => 'Employee has arrived more than 30 minutes late on 6 occasions during July 2026 without prior notice or valid justification. Line manager issued informal warnings on three occasions.',
        ]);
        DisciplineService::updateInvestigation($case1, 'Reviewed attendance records for July. Confirmed 6 late arrivals (07:45–08:15 check-in vs 07:30 start). Employee acknowledged tardiness citing personal transport issues. No medical certificate provided.');
        DisciplineService::closeCase($case1, 'written_warning', 'Employee issued written warning. Required to improve punctuality within 30 days.');

        // ── Case 2: Verbal warning, still open ────────────────────────────────
        $emp2 = $employees->get(1);
        DisciplineService::openCase($emp2, [
            'category'    => 'conduct',
            'severity'    => 'minor',
            'title'       => 'Unprofessional Conduct in Team Meeting',
            'description' => 'Employee used inappropriate language towards a colleague during the weekly team briefing on 10 August 2026. Two witnesses present.',
        ]);

        // ── Case 3: Under investigation (major) ──────────────────────────────
        $emp3 = $employees->get(2);
        $case3 = DisciplineService::openCase($emp3, [
            'category'    => 'performance',
            'severity'    => 'major',
            'title'       => 'Persistent Failure to Meet Sales Targets',
            'description' => 'Employee has failed to meet monthly sales targets for 3 consecutive months (May–July 2026) despite performance improvement plan issued in April.',
        ]);
        DisciplineService::updateInvestigation($case3, 'Performance data reviewed. Employee met 42%, 38%, and 51% of targets respectively. PIP objectives not achieved. Manager interview scheduled for 18 August 2026.');

        // ── Announcements ─────────────────────────────────────────────────────
        HrAnnouncement::create([
            'title'        => 'eSahlan Office Closed — Eid Al-Adha',
            'body'         => "Dear Team,\n\nPlease note that the eSahlan office will be closed from Sunday 16 June to Tuesday 18 June 2026 in observance of Eid Al-Adha. Work resumes Wednesday 19 June.\n\nEid Mubarak to all who celebrate! 🌙",
            'audience'     => 'all',
            'created_by'   => $manager->id,
            'published_at' => now()->subDays(10),
        ]);

        HrAnnouncement::create([
            'title'        => 'Q3 Performance Review — Dates Announced',
            'body'         => "The Q3 2026 performance review cycle opens on 1 September 2026. All employees should complete their self-assessments by 10 September. Managers will conduct one-on-one reviews between 15–25 September.\n\nPlease log into the HR portal to view your goals and submit your self-assessment.",
            'audience'     => 'all',
            'created_by'   => $manager->id,
            'published_at' => now()->subDays(3),
        ]);

        HrAnnouncement::create([
            'title'        => 'Health & Safety Drill — 20 August 2026',
            'body'         => "A mandatory health and safety drill will be conducted on Wednesday 20 August at 10:00 AM. All staff must participate. Please be present in the office and follow the instructions of the safety warden.\n\nDuration: approximately 30 minutes.",
            'audience'     => 'all',
            'created_by'   => $officer->id,
            'published_at' => null, // draft — not yet published
        ]);

        $this->command->info('Phase 5 seeded: 3 disciplinary cases (1 closed/written warning, 1 open/verbal, 1 investigating) + 3 announcements (2 published, 1 draft).');
    }
}
