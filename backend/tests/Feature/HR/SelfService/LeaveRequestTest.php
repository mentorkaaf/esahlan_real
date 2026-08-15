<?php

namespace Tests\Feature\HR\SelfService;

use App\Models\HR\HrDepartment;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrLeaveBalance;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrLeaveType;
use App\Models\HR\HrStaff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeEmployee(User $user, array $attributes = []): HrEmployee
    {
        $dept = HrDepartment::factory()->create();

        return HrEmployee::factory()->create(array_merge([
            'user_id'       => $user->id,
            'department_id' => $dept->id,
            'status'        => 'active',
        ], $attributes));
    }

    private function makeLeaveType(array $attributes = []): HrLeaveType
    {
        return HrLeaveType::factory()->create(array_merge([
            'days_per_year' => 15,
            'is_active'     => true,
        ], $attributes));
    }

    private function seedBalance(HrEmployee $emp, HrLeaveType $lt, int $allocated = 15, int $used = 0, int $pending = 0): void
    {
        HrLeaveBalance::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'year'          => now()->year,
            'allocated'     => $allocated,
            'used'          => $used,
            'pending'       => $pending,
        ]);
    }

    // ── POST /api/v1/hr/me/leave/requests ────────────────────────────────────

    public function test_employee_can_submit_leave_request(): void
    {
        $user = $this->makeUser();
        $emp  = $this->makeEmployee($user);
        $lt   = $this->makeLeaveType();
        $this->seedBalance($emp, $lt, 15);

        $start = Carbon::today()->addDays(3)->format('Y-m-d');
        $end   = Carbon::today()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/me/leave/requests', [
                'leave_type_id' => $lt->id,
                'start_date'    => $start,
                'end_date'      => $end,
                'reason'        => 'Personal appointment',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data'    => ['status' => 'pending'],
            ]);

        $this->assertDatabaseHas('hr_leave_requests', [
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'status'        => 'pending',
        ]);
    }

    public function test_submit_fails_when_insufficient_balance(): void
    {
        $user = $this->makeUser();
        $emp  = $this->makeEmployee($user);
        $lt   = $this->makeLeaveType(['days_per_year' => 5]);
        $this->seedBalance($emp, $lt, 5, 4, 1); // 0 available

        $start = Carbon::today()->addDays(3)->format('Y-m-d');
        $end   = Carbon::today()->addDays(5)->format('Y-m-d');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/me/leave/requests', [
                'leave_type_id' => $lt->id,
                'start_date'    => $start,
                'end_date'      => $end,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_submit_fails_for_overlapping_request(): void
    {
        $user = $this->makeUser();
        $emp  = $this->makeEmployee($user);
        $lt   = $this->makeLeaveType();
        $this->seedBalance($emp, $lt, 15);

        $start = Carbon::today()->addDays(3)->format('Y-m-d');
        $end   = Carbon::today()->addDays(7)->format('Y-m-d');

        // First request
        HrLeaveRequest::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'start_date'    => $start,
            'end_date'      => $end,
            'working_days'  => 5,
            'status'        => 'pending',
        ]);

        // Overlapping second request
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/me/leave/requests', [
                'leave_type_id' => $lt->id,
                'start_date'    => Carbon::today()->addDays(5)->format('Y-m-d'),
                'end_date'      => Carbon::today()->addDays(6)->format('Y-m-d'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_unlinked_user_gets_403_hr_not_linked(): void
    {
        $user = $this->makeUser(); // No HrEmployee linked

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/me/leave/requests', [
                'leave_type_id' => 1,
                'start_date'    => today()->addDays(3)->format('Y-m-d'),
                'end_date'      => today()->addDays(4)->format('Y-m-d'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'HR_NOT_LINKED');
    }

    public function test_guest_cannot_submit_leave(): void
    {
        $this->postJson('/api/v1/hr/me/leave/requests', [])->assertUnauthorized();
    }

    // ── GET /api/v1/hr/me/leave/balances ─────────────────────────────────────

    public function test_employee_can_see_leave_balances(): void
    {
        $user = $this->makeUser();
        $emp  = $this->makeEmployee($user);
        $lt   = $this->makeLeaveType(['name' => 'Annual Leave']);
        $this->seedBalance($emp, $lt, 15, 3, 0);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/me/leave/balances')
            ->assertOk()
            ->assertJsonFragment([
                'leave_type' => 'Annual Leave',
                'allocated'  => 15.0,
                'used'       => 3.0,
                'available'  => 12.0,
            ]);
    }

    // ── GET /api/v1/hr/me/leave/requests ─────────────────────────────────────

    public function test_employee_can_list_own_leave_requests(): void
    {
        $user = $this->makeUser();
        $emp  = $this->makeEmployee($user);
        $lt   = $this->makeLeaveType();

        HrLeaveRequest::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'start_date'    => today()->addDays(1),
            'end_date'      => today()->addDays(2),
            'working_days'  => 2,
            'status'        => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/me/leave/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }
}
