<?php

namespace Database\Seeders;

use App\Models\HR\HrEmployee;
use App\Models\HR\HrSalaryComponent;
use App\Models\HR\HrEmployeeComponent;
use App\Models\HR\HrPayrollRun;
use App\Models\HR\HrCommission;
use App\Services\HR\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HrPhase3Seeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Salary Components ───────────────────────────────────────────
        $transport = HrSalaryComponent::firstOrCreate(['code' => 'TRANSPORT'], [
            'name'        => 'Transport Allowance',
            'type'        => 'earning',
            'calculation' => 'fixed',
            'value'       => 30.00,
            'is_taxable'  => false,
            'is_active'   => true,
            'description' => 'Monthly transport allowance',
        ]);

        $airtime = HrSalaryComponent::firstOrCreate(['code' => 'AIRTIME'], [
            'name'        => 'Airtime Allowance',
            'type'        => 'earning',
            'calculation' => 'fixed',
            'value'       => 10.00,
            'is_taxable'  => false,
            'is_active'   => true,
            'description' => 'Monthly airtime/data allowance',
        ]);

        $advance = HrSalaryComponent::firstOrCreate(['code' => 'ADVANCE_DED'], [
            'name'        => 'Salary Advance Deduction',
            'type'        => 'deduction',
            'calculation' => 'fixed',
            'value'       => 50.00,
            'is_taxable'  => false,
            'is_active'   => true,
            'description' => 'Deduction for salary advance taken',
        ]);

        $this->command->info('✓ 3 salary components created.');

        // ── 2. Assign components to all active employees ───────────────────
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])->get();

        foreach ($employees as $emp) {
            // Transport & Airtime for all
            foreach ([$transport, $airtime] as $comp) {
                HrEmployeeComponent::firstOrCreate([
                    'employee_id'  => $emp->id,
                    'component_id' => $comp->id,
                ], [
                    'override_value'  => null,
                    'effective_from'  => now()->startOfYear(),
                    'effective_to'    => null,
                ]);
            }

            // Advance deduction: assign but with override_value = 0 (opt-in manually)
            HrEmployeeComponent::firstOrCreate([
                'employee_id'  => $emp->id,
                'component_id' => $advance->id,
            ], [
                'override_value' => 0.00,
                'effective_from' => now()->startOfYear(),
                'effective_to'   => null,
            ]);
        }

        $this->command->info("✓ Components assigned to {$employees->count()} employees.");

        // ── 3. Draft payroll run for last month ────────────────────────────
        $lastMonth = now()->subMonth()->format('Y-m');

        $existingRun = HrPayrollRun::where('period', $lastMonth)->first();
        if ($existingRun) {
            $this->command->warn("Payroll run for {$lastMonth} already exists — skipping generate.");
        } else {
            try {
                $result = PayrollService::generateRun($lastMonth);
                if ($result['error']) {
                    $this->command->error("Payroll run error: " . $result['error']);
                } else {
                    $this->command->info("✓ Draft payroll run generated for {$lastMonth}. Run ID: {$result['run']->id}");
                }
            } catch (\Exception $e) {
                $this->command->error("Failed to generate payroll run: " . $e->getMessage());
            }
        }

        // ── 4. Commissions for Marketing Officers ─────────────────────────
        // Find employees in "Marketing" department (or just pick first 2 active employees)
        $marketingEmps = HrEmployee::whereIn('status', ['active', 'probation'])
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%Marketing%'))
            ->limit(2)
            ->get();

        // Fallback: just use first 2 employees if no marketing dept
        if ($marketingEmps->count() < 2) {
            $marketingEmps = HrEmployee::whereIn('status', ['active', 'probation'])
                ->limit(2)
                ->get();
        }

        $commissionPeriod = now()->subMonth()->format('Y-m');

        if ($marketingEmps->count() >= 1) {
            HrCommission::firstOrCreate([
                'employee_id' => $marketingEmps[0]->id,
                'period'      => $commissionPeriod,
                'type'        => 'customer_signups',
            ], [
                'description' => 'Customer sign-up commission',
                'target'      => 50,
                'achieved'    => 63,
                'rate'        => 2.00,
                'amount'      => 126.00,
                'status'      => 'pending',
            ]);
        }

        if ($marketingEmps->count() >= 2) {
            HrCommission::firstOrCreate([
                'employee_id' => $marketingEmps[1]->id,
                'period'      => $commissionPeriod,
                'type'        => 'vendor_signups',
            ], [
                'description' => 'Vendor sign-up commission',
                'target'      => 10,
                'achieved'    => 8,
                'rate'        => 15.00,
                'amount'      => 120.00,
                'status'      => 'pending',
            ]);
        }

        $this->command->info('✓ 2 commissions seeded for ' . $commissionPeriod . '.');
        $this->command->newLine();
        $this->command->line('── Phase 3 URLs ─────────────────────────────────────────');
        $this->command->line('HR Payroll:       http://168.144.117.91/hr/payroll');
        $this->command->line('HR Components:    http://168.144.117.91/hr/components');
        $this->command->line('HR Commissions:   http://168.144.117.91/hr/commissions');
        $this->command->line('Admin Payroll:    http://168.144.117.91/admin/hr/payroll');
        $this->command->line('────────────────────────────────────────────────────────');
    }
}
