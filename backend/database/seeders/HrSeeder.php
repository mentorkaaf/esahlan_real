<?php

namespace Database\Seeders;

use App\Models\HR\HrStaff;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrPosition;
use App\Models\HR\HrEmployee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        // ── HR Staff (login users) ───────────────────────────────────────
        HrStaff::firstOrCreate(['email' => 'hr@esahlan.com'], [
            'name'      => 'HR Manager',
            'phone'     => '+252612000001',
            'password'  => Hash::make('Hr@eSahlan2024!'),
            'role'      => 'hr_manager',
            'is_active' => true,
        ]);

        HrStaff::firstOrCreate(['email' => 'officer@esahlan.com'], [
            'name'      => 'HR Officer',
            'phone'     => '+252612000002',
            'password'  => Hash::make('Hr@eSahlan2024!'),
            'role'      => 'hr_officer',
            'is_active' => true,
        ]);

        // ── Departments ──────────────────────────────────────────────────
        $departments = [
            ['name' => 'Management',      'code' => 'MGT'],
            ['name' => 'Operations',       'code' => 'OPS'],
            ['name' => 'Technology',       'code' => 'TECH'],
            ['name' => 'Finance',          'code' => 'FIN'],
            ['name' => 'Customer Support', 'code' => 'CS'],
            ['name' => 'Marketing',        'code' => 'MKT'],
        ];

        $deptMap = [];
        foreach ($departments as $d) {
            $dept = HrDepartment::firstOrCreate(['code' => $d['code']], [
                'name'      => $d['name'],
                'is_active' => true,
            ]);
            $deptMap[$d['code']] = $dept->id;
        }

        // ── Positions ────────────────────────────────────────────────────
        $positions = [
            ['dept' => 'MGT',  'title' => 'Chief Executive Officer',    'grade' => 'C1', 'min' => 3000, 'max' => 5000],
            ['dept' => 'OPS',  'title' => 'Operations Manager',         'grade' => 'M1', 'min' => 1500, 'max' => 2500],
            ['dept' => 'OPS',  'title' => 'Operations Officer',         'grade' => 'L2', 'min' => 600,  'max' => 1000],
            ['dept' => 'TECH', 'title' => 'Software Engineer',          'grade' => 'L3', 'min' => 800,  'max' => 1500],
            ['dept' => 'TECH', 'title' => 'Junior Developer',           'grade' => 'L1', 'min' => 400,  'max' => 700],
            ['dept' => 'FIN',  'title' => 'Finance Officer',            'grade' => 'L2', 'min' => 700,  'max' => 1200],
            ['dept' => 'CS',   'title' => 'Customer Support Agent',     'grade' => 'L1', 'min' => 350,  'max' => 600],
            ['dept' => 'MKT',  'title' => 'Marketing Specialist',       'grade' => 'L2', 'min' => 600,  'max' => 1000],
        ];

        $posMap = [];
        foreach ($positions as $p) {
            $pos = HrPosition::firstOrCreate(
                ['title' => $p['title'], 'department_id' => $deptMap[$p['dept']]],
                [
                    'grade'       => $p['grade'],
                    'min_salary'  => $p['min'],
                    'max_salary'  => $p['max'],
                    'is_active'   => true,
                ]
            );
            $posMap[$p['title']] = $pos->id;
        }

        // ── Sample Employees ─────────────────────────────────────────────
        $employees = [
            ['first' => 'Ahmed',   'last' => 'Mohamed',  'dept' => 'MGT',  'pos' => 'Chief Executive Officer',    'gender' => 'male',   'type' => 'full_time', 'status' => 'active',    'salary' => 4000, 'hire' => '2021-01-15'],
            ['first' => 'Faadumo', 'last' => 'Hassan',   'dept' => 'OPS',  'pos' => 'Operations Manager',         'gender' => 'female', 'type' => 'full_time', 'status' => 'active',    'salary' => 2000, 'hire' => '2021-03-10'],
            ['first' => 'Abdi',    'last' => 'Ali',       'dept' => 'TECH', 'pos' => 'Software Engineer',          'gender' => 'male',   'type' => 'full_time', 'status' => 'active',    'salary' => 1200, 'hire' => '2022-06-01'],
            ['first' => 'Hodan',   'last' => 'Ibrahim',   'dept' => 'FIN',  'pos' => 'Finance Officer',            'gender' => 'female', 'type' => 'full_time', 'status' => 'active',    'salary' => 900,  'hire' => '2022-08-15'],
            ['first' => 'Mahad',   'last' => 'Omar',      'dept' => 'TECH', 'pos' => 'Junior Developer',           'gender' => 'male',   'type' => 'full_time', 'status' => 'probation', 'salary' => 500,  'hire' => '2024-11-01'],
            ['first' => 'Nasra',   'last' => 'Yusuf',     'dept' => 'CS',   'pos' => 'Customer Support Agent',     'gender' => 'female', 'type' => 'full_time', 'status' => 'active',    'salary' => 450,  'hire' => '2023-02-20'],
            ['first' => 'Bashir',  'last' => 'Farah',     'dept' => 'OPS',  'pos' => 'Operations Officer',         'gender' => 'male',   'type' => 'part_time', 'status' => 'active',    'salary' => 600,  'hire' => '2023-07-05'],
            ['first' => 'Amina',   'last' => 'Warsame',   'dept' => 'MKT',  'pos' => 'Marketing Specialist',       'gender' => 'female', 'type' => 'full_time', 'status' => 'active',    'salary' => 750,  'hire' => '2023-10-01'],
            ['first' => 'Ilyas',   'last' => 'Mukhtar',   'dept' => 'TECH', 'pos' => 'Junior Developer',           'gender' => 'male',   'type' => 'contract',  'status' => 'active',    'salary' => 600,  'hire' => '2024-05-01'],
            ['first' => 'Deeqa',   'last' => 'Abdullahi', 'dept' => 'CS',   'pos' => 'Customer Support Agent',     'gender' => 'female', 'type' => 'full_time', 'status' => 'active',    'salary' => 400,  'hire' => '2024-01-15'],
            ['first' => 'Khalid',  'last' => 'Ahmed',     'dept' => 'FIN',  'pos' => 'Finance Officer',            'gender' => 'male',   'type' => 'full_time', 'status' => 'active',    'salary' => 850,  'hire' => '2023-04-10'],
            ['first' => 'Sahra',   'last' => 'Hussein',   'dept' => 'MKT',  'pos' => 'Marketing Specialist',       'gender' => 'female', 'type' => 'full_time', 'status' => 'probation', 'salary' => 650,  'hire' => '2025-01-10'],
        ];

        foreach ($employees as $i => $e) {
            // Skip if employee with same name+dept exists
            $exists = HrEmployee::where('first_name', $e['first'])
                ->where('last_name', $e['last'])
                ->exists();
            if ($exists) continue;

            HrEmployee::create([
                'employee_no'     => HrEmployee::generateEmployeeNo(),
                'first_name'      => $e['first'],
                'last_name'       => $e['last'],
                'gender'          => $e['gender'],
                'phone'           => '+25261' . str_pad($i + 1000000, 7, '0', STR_PAD_LEFT),
                'department_id'   => $deptMap[$e['dept']],
                'position_id'     => $posMap[$e['pos']],
                'employment_type' => $e['type'],
                'status'          => $e['status'],
                'hire_date'       => $e['hire'],
                'base_salary'     => $e['salary'],
            ]);
        }

        $this->command->info('HR seeder complete: 2 staff, ' . count($departments) . ' departments, ' . count($positions) . ' positions, ' . count($employees) . ' employees.');
    }
}
