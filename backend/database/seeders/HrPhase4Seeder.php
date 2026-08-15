<?php

namespace Database\Seeders;

use App\Models\HR\HrApplicant;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrGoal;
use App\Models\HR\HrJobPosting;
use App\Models\HR\HrPerformanceCycle;
use App\Models\HR\HrPosition;
use App\Models\HR\HrStaff;
use Illuminate\Database\Seeder;

class HrPhase4Seeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Job Posting — Marketing Officer ───────────────────────────────
        $dept = HrDepartment::where('name', 'like', '%Marketing%')->first()
             ?? HrDepartment::first();
        $pos  = HrPosition::where('title', 'like', '%Marketing%')->first()
             ?? HrPosition::first();
        $staff = HrStaff::first();

        $posting = HrJobPosting::firstOrCreate(
            ['title' => 'Marketing Officer'],
            [
                'department_id' => $dept?->id,
                'position_id'   => $pos?->id,
                'description'   => "We are looking for a driven Marketing Officer to join eSahlan's growing team. You will manage digital campaigns, grow our user base, and coordinate with vendors across Mogadishu.\n\nYou'll work closely with the product and operations teams to drive customer and vendor acquisition.",
                'requirements'  => "• 2+ years marketing experience\n• Strong communication skills in Somali & English\n• Experience with social media campaigns\n• Ability to work independently",
                'location'      => 'Mogadishu, Somalia',
                'type'          => 'full_time',
                'openings'      => 3,
                'hired_count'   => 0,
                'status'        => 'open',
                'created_by'    => $staff?->id,
                'posted_at'     => now()->subDays(7),
                'closes_at'     => now()->addDays(23)->toDateString(),
            ]
        );

        $this->command->info("✓ Job posting: {$posting->title} (ID: {$posting->id})");

        // ── 2. 6 Applicants across stages ────────────────────────────────────
        $applicantData = [
            ['name'=>'Fadumo Abdi Hassan',   'email'=>'fadumo.abdi@gmail.com',    'stage'=>'applied',   'rating'=>null, 'source'=>'careers_page', 'days'=>5],
            ['name'=>'Omar Mohamud Yusuf',   'email'=>'omar.mohamud@gmail.com',   'stage'=>'applied',   'rating'=>null, 'source'=>'linkedin',     'days'=>3],
            ['name'=>'Khadija Ahmed Warsame','email'=>'khadija.ahmed@gmail.com',  'stage'=>'screening', 'rating'=>3,    'source'=>'referral',     'days'=>4],
            ['name'=>'Abdirahman Ali Nur',   'email'=>'abdirahman.ali@gmail.com', 'stage'=>'interview', 'rating'=>4,    'source'=>'careers_page', 'days'=>6],
            ['name'=>'Hodan Hassan Ibrahim', 'email'=>'hodan.hassan@gmail.com',   'stage'=>'offer',     'rating'=>5,    'source'=>'linkedin',     'days'=>8],
            ['name'=>'Mahad Osman Jama',     'email'=>'mahad.osman@gmail.com',    'stage'=>'rejected',  'rating'=>2,    'source'=>'careers_page', 'days'=>10],
        ];

        foreach ($applicantData as $d) {
            HrApplicant::firstOrCreate(
                ['job_posting_id' => $posting->id, 'email' => $d['email']],
                [
                    'name'             => $d['name'],
                    'phone'            => '+252' . rand(610000000, 699999999),
                    'stage'            => $d['stage'],
                    'stage_changed_at' => now()->subDays($d['days']),
                    'rating'           => $d['rating'],
                    'source'           => $d['source'],
                    'notes'            => $d['rating'] && $d['rating'] >= 4 ? 'Strong candidate, good communication skills.' : null,
                ]
            );
        }

        $this->command->info('✓ 6 applicants seeded across stages.');

        // ── 3. Performance cycle — active ─────────────────────────────────────
        $cycleStart = now()->startOfYear();
        $cycleEnd   = now()->startOfYear()->addMonths(6)->subDay();

        $cycle = HrPerformanceCycle::firstOrCreate(
            ['name' => 'H1 ' . now()->year . ' Performance Review'],
            [
                'period_start' => $cycleStart,
                'period_end'   => $cycleEnd,
                'status'       => 'active',
                'created_by'   => $staff?->id,
            ]
        );

        $this->command->info("✓ Performance cycle: {$cycle->name} (ID: {$cycle->id})");

        // ── 4. Goals for 5 employees ──────────────────────────────────────────
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->orderBy('id')
            ->limit(5)
            ->get();

        $goalTemplates = [
            [
                ['title'=>'Customer Acquisition',   'weight'=>40, 'target'=>'50 new customers/month'],
                ['title'=>'Campaign Execution',      'weight'=>35, 'target'=>'4 campaigns/quarter'],
                ['title'=>'Social Media Growth',     'weight'=>25, 'target'=>'+500 followers/month'],
            ],
            [
                ['title'=>'Vendor Onboarding',       'weight'=>45, 'target'=>'10 new vendors/month'],
                ['title'=>'Customer Support Score',  'weight'=>30, 'target'=>'>4.5/5 rating'],
                ['title'=>'Reports Submitted',       'weight'=>25, 'target'=>'Monthly report on time'],
            ],
            [
                ['title'=>'Order Processing Speed',  'weight'=>50, 'target'=>'<2h avg processing time'],
                ['title'=>'Error Rate Reduction',    'weight'=>30, 'target'=>'<1% order errors'],
                ['title'=>'Training Completed',      'weight'=>20, 'target'=>'2 training courses'],
            ],
        ];

        foreach ($employees as $i => $emp) {
            $template = $goalTemplates[$i % count($goalTemplates)];
            foreach ($template as $g) {
                HrGoal::firstOrCreate(
                    ['cycle_id'=>$cycle->id, 'employee_id'=>$emp->id, 'title'=>$g['title']],
                    [
                        'weight'       => $g['weight'],
                        'target_value' => $g['target'],
                        'status'       => 'in_progress',
                    ]
                );
            }
            $this->command->line("  Goals set for: {$emp->full_name}");
        }

        $this->command->info('✓ Goals set for 5 employees.');
        $this->command->newLine();
        $this->command->line('── Phase 4 URLs ─────────────────────────────────────────────────────');
        $this->command->line('HR Job Postings:    http://168.144.117.91/hr/recruitment/postings');
        $this->command->line('HR Kanban:          http://168.144.117.91/hr/recruitment/postings/' . $posting->id);
        $this->command->line('HR Performance:     http://168.144.117.91/hr/performance');
        $this->command->line('HR Goals:           http://168.144.117.91/hr/performance/' . $cycle->id . '/goals');
        $this->command->line('HR Reviews:         http://168.144.117.91/hr/performance/' . $cycle->id . '/reviews');
        $this->command->line('Public Careers:     http://168.144.117.91/careers');
        $this->command->line('Admin Recruitment:  http://168.144.117.91/admin/hr/recruitment');
        $this->command->line('Admin Performance:  http://168.144.117.91/admin/hr/performance');
        $this->command->line('────────────────────────────────────────────────────────────────────');
    }
}
