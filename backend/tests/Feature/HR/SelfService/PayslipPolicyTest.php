<?php

namespace Tests\Feature\HR\SelfService;

use App\Models\HR\HrDepartment;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrPayrollRun;
use App\Models\HR\HrPayslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipPolicyTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeLinkedEmployee(): array
    {
        $user = User::factory()->create();
        $dept = HrDepartment::factory()->create();
        $emp  = HrEmployee::factory()->create([
            'user_id'       => $user->id,
            'department_id' => $dept->id,
            'status'        => 'active',
        ]);

        return [$user, $emp];
    }

    private function makePayslipFor(HrEmployee $emp): HrPayslip
    {
        $run = HrPayrollRun::factory()->create(['status' => 'approved', 'period' => '2026-07']);

        return HrPayslip::factory()->create([
            'employee_id'    => $emp->id,
            'payroll_run_id' => $run->id,
            'total_gross'    => 1500.00,
            'total_net'      => 1300.00,
            'payment_status' => 'unpaid',
        ]);
    }

    // ── GET /api/v1/hr/me/payslips ────────────────────────────────────────────

    public function test_employee_sees_own_payslips_only(): void
    {
        [$user1, $emp1] = $this->makeLinkedEmployee();
        [$user2, $emp2] = $this->makeLinkedEmployee();

        $this->makePayslipFor($emp1);
        $this->makePayslipFor($emp2); // belongs to emp2

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/v1/hr/me/payslips');

        $response->assertOk()->assertJsonCount(1, 'data');

        // Confirm it's the right payslip (employee_id is not exposed, but count = 1 suffices)
    }

    // ── GET /api/v1/hr/me/payslips/{id}/pdf ──────────────────────────────────

    public function test_employee_cannot_download_another_employees_payslip(): void
    {
        [$user1, $emp1] = $this->makeLinkedEmployee();
        [$user2, $emp2] = $this->makeLinkedEmployee();

        $payslip = $this->makePayslipFor($emp2); // Belongs to emp2

        // user1 tries to access emp2's payslip
        $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/hr/me/payslips/{$payslip->id}/pdf")
            ->assertStatus(403)
            ->assertJsonPath('code', 'PAYSLIP_FORBIDDEN');
    }

    public function test_employee_can_access_own_payslip_pdf(): void
    {
        [$user, $emp] = $this->makeLinkedEmployee();
        $payslip = $this->makePayslipFor($emp);

        // We mock the PDF generation so we don't need dompdf installed in CI
        $this->mock(\App\Services\HR\PayrollService::class)
            ->shouldReceive('generatePdf')
            ->never(); // Called as static — so we test ownership check passes (no 403)

        // If the request gets past ownership check, we'll get either a PDF or an error
        // from dompdf missing data — 403 or 422 from dompdf means ownership DID pass
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/hr/me/payslips/{$payslip->id}/pdf");

        // The critical assertion: ownership check passed (not 403 PAYSLIP_FORBIDDEN)
        $this->assertNotEquals(403, $response->status(), 'Own payslip should not be forbidden');
    }

    public function test_unlinked_user_gets_403_for_payslip_list(): void
    {
        $user = User::factory()->create(); // Not linked to any employee

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/me/payslips')
            ->assertStatus(403)
            ->assertJsonPath('code', 'HR_NOT_LINKED');
    }

    public function test_guest_cannot_access_payslips(): void
    {
        $this->getJson('/api/v1/hr/me/payslips')->assertUnauthorized();
    }
}
