<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulePositionSeeder extends Seeder
{
    /**
     * Default positions per module.
     *
     * Structure: module_slug => [ [name, description, level, dept_name|null], ... ]
     * dept_name must match a name seeded by ModuleDepartmentSeeder.
     * hr_position_id is auto-resolved via title match in hr_positions.
     */
    public function run(): void
    {
        $positions = [
            'efood' => [
                ['Food Delivery Driver',       'Picks up and delivers customer food orders on time',          'mid',     'Delivery Dispatch'],
                ['Restaurant Coordinator',     'Liaison between eSahlan and partner restaurants',             'mid',     'Operations'],
                ['Order Dispatcher',           'Assigns and monitors live delivery orders',                   'senior',  'Delivery Dispatch'],
                ['Customer Support Agent',     'Handles food order complaints, refunds, and enquiries',       'junior',  'Customer Support'],
                ['Operations Supervisor',      'Oversees daily eFood operations and driver performance',      'lead',    'Operations'],
            ],
            'eshop' => [
                ['Sales Representative',       'Onboards vendors and manages seller accounts',                'mid',     'Sales'],
                ['Warehouse Picker',           'Picks and packs customer orders in the warehouse',            'junior',  'Warehouse'],
                ['Returns Handler',            'Processes returned items, quality checks, and refunds',       'mid',     'Returns & Refunds'],
                ['Customer Service Agent',     'Resolves order enquiries and post-purchase issues',           'junior',  'Customer Service'],
                ['Store Operations Coordinator','Coordinates daily operations across vendor stores',          'senior',  'Sales'],
            ],
            'eticket' => [
                ['Reservation Agent',          'Books flights and travel packages for customers',             'mid',     'Reservations'],
                ['Travel Consultant',          'Advises on travel plans, visa, and itineraries',              'senior',  'Reservations'],
                ['Ticketing Finance Officer',  'Manages billing, refunds, and financial reconciliation',      'senior',  'Finance'],
                ['Operations Coordinator',     'Manages partner airline and route operations',                'mid',     'Operations'],
            ],
            'ehealth' => [
                ['Healthcare Coordinator',     'Coordinates appointments between patients and providers',     'mid',     'Clinical'],
                ['Medical Receptionist',       'Manages appointment bookings and patient records',            'junior',  'Administration'],
                ['Patient Support Agent',      'Handles patient enquiries and follow-up communications',      'junior',  'Customer Support'],
                ['Clinical Data Analyst',      'Analyses health data and generates performance reports',      'senior',  'Analytics'],
            ],
            'edata' => [
                ['Technical Support Specialist','Activates data bundles and resolves network issues',         'mid',     'Technical Support'],
                ['Data Sales Agent',           'Promotes data bundles and manages partner telco relations',   'mid',     'Sales'],
                ['Network Operations Analyst', 'Monitors bundle inventory and network availability',          'senior',  'Operations'],
                ['Analytics Specialist',       'Produces usage reports and tracks bundle performance',        'senior',  'Analytics'],
            ],
            'eparcel' => [
                ['Courier Driver',             'Collects and delivers parcels on assigned routes',            'junior',  'Drivers'],
                ['Dispatch Controller',        'Plans routes, assigns drivers, and tracks shipments',         'senior',  'Dispatch'],
                ['Warehouse Sorter',           'Sorts and scans parcels at the hub',                         'junior',  'Warehouse'],
                ['Parcel Support Agent',       'Handles tracking enquiries and delivery exceptions',          'junior',  'Customer Support'],
                ['Fleet Supervisor',           'Manages driver roster and vehicle compliance',                'lead',    'Drivers'],
            ],
            'erent' => [
                ['Listing Agent',              'Manages property and vehicle listings on the platform',       'mid',     'Listings'],
                ['Property Inspector',         'Conducts on-site inspections and quality checks',             'mid',     'Listings'],
                ['Customer Relations Officer', 'Handles tenant enquiries, negotiations, and onboarding',      'senior',  'Customer Relations'],
                ['Rental Finance Coordinator', 'Processes payments, deposits, and financial reconciliation',  'mid',     'Finance'],
                ['Agent Network Supervisor',   'Manages field agents and their territory assignments',        'lead',    'Agent Network'],
            ],
            'emoving' => [
                ['Moving Crew Member',         'Handles packing, loading, and unloading at job sites',       'junior',  'Fleet Management'],
                ['Fleet Driver',               'Operates moving vehicles and ensures safe transit',           'mid',     'Fleet Management'],
                ['Operations Coordinator',     'Schedules moves and coordinates crew assignments',            'mid',     'Scheduling'],
                ['Customer Support Agent',     'Pre-move surveys, queries, and after-service follow-up',      'junior',  'Customer Support'],
                ['Scheduling Manager',         'Manages calendar, capacity, and resource allocation',         'lead',    'Scheduling'],
            ],
            'ewholesale' => [
                ['Account Manager',            'Manages B2B client relationships and sales targets',          'senior',  'Sales'],
                ['Procurement Officer',        'Sources products from suppliers and negotiates bulk rates',   'mid',     'Procurement'],
                ['Logistics Coordinator',      'Coordinates freight, shipping, and large-order delivery',     'mid',     'Logistics'],
                ['B2B Support Agent',          'Handles wholesale client queries and order issues',           'junior',  'Customer Support'],
                ['Procurement Manager',        'Leads supplier relations and procurement strategy',           'manager', 'Procurement'],
            ],
            'egrocery' => [
                ['Procurement Specialist',     'Sources fresh produce and manages supplier contracts',        'mid',     'Procurement'],
                ['Grocery Delivery Rider',     'Delivers grocery orders to customers within SLA windows',     'junior',  'Delivery'],
                ['Customer Support Agent',     'Handles substitutions, missing items, and order queries',     'junior',  'Customer Support'],
                ['Quality Inspector',          'Ensures freshness standards are met at pick and dispatch',    'mid',     'Quality Control'],
                ['Delivery Supervisor',        'Manages rider roster, route assignments, and performance',    'lead',    'Delivery'],
            ],
            'eexchange' => [
                ['Exchange Trader',            'Monitors and executes exchange orders on the platform',       'senior',  'Trading Desk'],
                ['Compliance Officer',         'Conducts KYC, AML checks, and regulatory reporting',         'senior',  'Compliance'],
                ['Exchange Support Agent',     'Resolves customer transaction queries and disputes',          'mid',     'Customer Support'],
                ['Tech Operations Engineer',   'Ensures platform uptime, integrations, and API reliability',  'senior',  'Tech Operations'],
                ['Trading Desk Lead',          'Leads the trading team and sets rate management strategy',    'lead',    'Trading Desk'],
            ],
            'elaundry' => [
                ['Laundry Operative',          'Sorts, washes, and presses garments to quality standards',    'junior',  'Operations'],
                ['Pickup Driver',              'Collects and returns laundry on scheduled routes',            'junior',  'Pickup & Delivery'],
                ['Laundry Support Agent',      'Handles order status queries and damage claims',              'junior',  'Customer Support'],
                ['Quality Controller',         'Inspects finished garments before dispatch to customer',      'mid',     'Quality Control'],
                ['Operations Supervisor',      'Manages laundry facility shift operations',                   'lead',    'Operations'],
            ],
            'elearning' => [
                ['Content Creator',            'Produces course videos, materials, and assessments',          'mid',     'Content Creation'],
                ['Learning Support Specialist','Supports learners with queries, progress, and coaching',       'mid',     'Student Support'],
                ['Instructor Relations Manager','Onboards instructors and manages performance agreements',     'senior',  'Instructor Relations'],
                ['Learning Analyst',           'Tracks completion rates, outcomes, and learner engagement',   'senior',  'Analytics'],
                ['Curriculum Manager',         'Designs learning pathways and oversees course portfolio',     'lead',    'Content Creation'],
            ],
        ];

        // Load modules, departments, and existing HR positions
        $modules    = DB::table('modules')->pluck('id', 'slug');
        $hrPositions = DB::table('hr_positions')->pluck('id', 'title'); // for reuse matching

        // Load module departments indexed by module_id + name
        $allDepts = DB::table('module_departments')->get(['id', 'module_id', 'name']);
        $deptIndex = []; // key: "{module_id}|{name}" => id
        foreach ($allDepts as $d) {
            $deptIndex["{$d->module_id}|{$d->name}"] = $d->id;
        }

        $now  = now()->toDateTimeString();
        $rows = [];

        foreach ($positions as $slug => $positionList) {
            $moduleId = $modules[$slug] ?? null;
            if (!$moduleId) continue;

            foreach ($positionList as [$name, $description, $level, $deptName]) {
                $deptId      = $deptName ? ($deptIndex["{$moduleId}|{$deptName}"] ?? null) : null;
                $hrPositionId = $hrPositions[$name] ?? null; // exact title match → reuse

                $rows[] = [
                    'module_id'            => $moduleId,
                    'module_department_id' => $deptId,
                    'hr_position_id'       => $hrPositionId,
                    'name'                 => $name,
                    'description'          => $description,
                    'level'                => $level,
                    'status'               => 'active',
                    'sort_order'           => 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        // insertOrIgnore — safe to re-run (unique: module_id + name)
        $inserted = 0;
        foreach (array_chunk($rows, 50) as $chunk) {
            $inserted += DB::table('module_positions')->insertOrIgnore($chunk);
        }

        $this->command->info("ModulePositionSeeder: {$inserted} positions inserted (" . count($rows) . ' attempted).');
    }
}
