<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeModuleMetric;
use App\Models\HR\HrAuditLog;
use App\Models\HR\HrAttendance;
use App\Models\HR\HrLeave;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Auth helper
    // ─────────────────────────────────────────────────────────────────────────
    private function me()
    {
        return Auth::guard('employee')->user();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────
    public function dashboard()
    {
        $employee = $this->me()->load([
            'department', 'position',
            'workforceAssignments' => fn($q) => $q->where('status', 'active')
                ->with(['module', 'moduleDepartment', 'modulePosition', 'moduleRole']),
        ]);

        $activeAssignments = $employee->workforceAssignments;
        $primaryAssignment = $activeAssignments->firstWhere('assignment_type', 'primary')
            ?? $activeAssignments->first();

        // Active workspace module
        $workspace = $primaryAssignment?->module;

        // Performance (current month)
        $period   = now()->format('Y-m');
        $perfScores = EmployeeModuleMetric::scoresByModule($employee->id, $period);

        // Today's attendance
        $todayAttendance = HrAttendance::where('employee_id', $employee->id)
            ->whereDate('date', today())
            ->first();

        // Pending leaves
        $pendingLeaves = HrLeave::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->count();

        // Recent announcements (from hr_announcements if exists)
        $announcements = [];
        if (class_exists(\App\Models\HR\HrAnnouncement::class)) {
            $announcements = \App\Models\HR\HrAnnouncement::where('is_active', true)
                ->latest()
                ->limit(3)
                ->get();
        }

        return view('employee.dashboard', compact(
            'employee', 'activeAssignments', 'primaryAssignment',
            'workspace', 'perfScores', 'todayAttendance',
            'pendingLeaves', 'announcements', 'period'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // My Workspace (module work interface)
    // ─────────────────────────────────────────────────────────────────────────
    public function workspace(string $slug)
    {
        $employee = $this->me();

        // Verify employee is actually assigned to this module
        $assignment = WorkforceAssignment::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereHas('module', fn($q) => $q->where('slug', $slug))
            ->with(['module', 'moduleDepartment', 'modulePosition', 'moduleRole'])
            ->first();

        if (! $assignment) {
            abort(403, 'Tani module-kan lagugu xilsaari waayo.');
        }

        $module = $assignment->module;

        // Pull module-specific work data
        $workData = $this->getModuleWorkData($slug, $employee);

        // Performance for this module
        $period = now()->format('Y-m');
        $composite = EmployeeModuleMetric::compositeScore($employee->id, $module->id, $period);

        return view('employee.workspace', compact(
            'employee', 'assignment', 'module', 'workData', 'composite', 'period'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Workspace switcher
    // ─────────────────────────────────────────────────────────────────────────
    public function switchWorkspace(Request $request)
    {
        $employee = $this->me();
        $slug     = $request->input('slug');

        $hasAccess = WorkforceAssignment::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereHas('module', fn($q) => $q->where('slug', $slug))
            ->exists();

        if (! $hasAccess) {
            return back()->withErrors(['workspace' => 'Access la\'aanta.']);
        }

        $module = Module::where('slug', $slug)->firstOrFail();
        $employee->update(['active_workspace_id' => $module->id]);

        return redirect()->route('employee.workspace', $slug)
            ->with('success', $module->name . ' workspace-ka aad u bedelantay.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // My Performance
    // ─────────────────────────────────────────────────────────────────────────
    public function performance(Request $request)
    {
        $employee = $this->me();
        $period   = $request->get('period', now()->format('Y-m'));

        // Build period options (last 6 months)
        $periods = [];
        for ($i = 0; $i < 6; $i++) {
            $d = now()->subMonths($i);
            $periods[$d->format('Y-m')] = $d->format('M Y');
        }

        $allScores = EmployeeModuleMetric::scoresByModule($employee->id, $period);

        // Per-module detail
        $assignments = WorkforceAssignment::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->with(['module', 'module.performanceMetrics'])
            ->get();

        $moduleDetails = [];
        foreach ($assignments as $a) {
            $mod = $a->module;
            if (! $mod) continue;
            $composite = EmployeeModuleMetric::compositeScore($employee->id, $mod->id, $period);
            $metrics   = EmployeeModuleMetric::where('employee_id', $employee->id)
                ->where('module_id', $mod->id)
                ->where('period', $period)
                ->with('metric')
                ->get();
            $moduleDetails[] = [
                'module'    => $mod,
                'composite' => $composite,
                'metrics'   => $metrics,
            ];
        }

        // 6-month trend per module
        $trend = [];
        foreach ($assignments as $a) {
            if (! $a->module) continue;
            $slug = $a->module->slug;
            $trend[$slug] = [];
            foreach ($periods as $p => $label) {
                $trend[$slug][$p] = EmployeeModuleMetric::compositeScore($employee->id, $a->module->id, $p);
            }
        }

        return view('employee.performance', compact(
            'employee', 'period', 'periods', 'allScores', 'moduleDetails', 'trend'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // My Attendance
    // ─────────────────────────────────────────────────────────────────────────
    public function attendance(Request $request)
    {
        $employee = $this->me();
        $month    = $request->get('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);
        $records = HrAttendance::where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->orderBy('date')
            ->get();

        $summary = [
            'present'  => $records->where('status', 'present')->count(),
            'absent'   => $records->where('status', 'absent')->count(),
            'late'     => $records->where('status', 'late')->count(),
            'half_day' => $records->where('status', 'half_day')->count(),
            'leave'    => $records->where('status', 'on_leave')->count(),
        ];

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $d = now()->subMonths($i);
            $months[$d->format('Y-m')] = $d->format('M Y');
        }

        return view('employee.attendance', compact('employee', 'records', 'summary', 'month', 'months'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // My Leaves
    // ─────────────────────────────────────────────────────────────────────────
    public function leaves(Request $request)
    {
        $employee = $this->me();
        $leaves   = HrLeave::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('employee.leaves', compact('employee', 'leaves'));
    }

    public function leaveCreate()
    {
        $employee   = $this->me();
        $leaveTypes = ['annual', 'sick', 'maternity', 'paternity', 'emergency', 'unpaid'];
        return view('employee.leave-create', compact('employee', 'leaveTypes'));
    }

    public function leaveStore(Request $request)
    {
        $employee = $this->me();

        $data = $request->validate([
            'leave_type'   => 'required|in:annual,sick,maternity,paternity,emergency,unpaid',
            'start_date'   => 'required|date|after_or_equal:today',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'reason'       => 'required|string|max:500',
        ]);

        $data['employee_id'] = $employee->id;
        $data['status']      = 'pending';
        $data['days']        = now()->parse($data['start_date'])
            ->diffInWeekdays(now()->parse($data['end_date'])) + 1;

        HrLeave::create($data);

        return redirect()->route('employee.leaves')
            ->with('success', 'Codsigaaga daawo la diray. HR ayaa dib u eegi doona.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // My Profile
    // ─────────────────────────────────────────────────────────────────────────
    public function profile()
    {
        $employee = $this->me()->load(['department', 'position',
            'workforceAssignments' => fn($q) => $q->where('status', 'active')->with('module')]);
        return view('employee.profile', compact('employee'));
    }

    public function profileUpdate(Request $request)
    {
        $employee = $this->me();

        $data = $request->validate([
            'phone'   => 'required|string|max:20',
            'email'   => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
            'district'=> 'nullable|string|max:100',
        ]);

        $employee->update($data);

        return back()->with('success', 'Profile-kaaga la cusbooneysiiyay.');
    }

    public function passwordUpdate(Request $request)
    {
        $employee = $this->me();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        // Verify current password or PIN
        $validCurrent = ($employee->password && Hash::check($request->current_password, $employee->password))
                     || ($employee->pin && $request->current_password === $employee->pin);

        if (! $validCurrent) {
            return back()->withErrors(['current_password' => 'Password-ka hadda jira saxna maahan.']);
        }

        $employee->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password-kaaga la beddelay.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Module work data builder
    // ─────────────────────────────────────────────────────────────────────────
    private function getModuleWorkData(string $slug, $employee): array
    {
        // Each module pulls relevant pending/active work items for this employee
        // All data comes from real DB tables — no fake numbers
        try {
            return match($slug) {
                'efood'      => $this->efoodData($employee),
                'egrocery'   => $this->egroceryData($employee),
                'eshop'      => $this->eshopData($employee),
                'eparcel'    => $this->eparcelData($employee),
                'emoving'    => $this->emovingData($employee),
                'erent'      => $this->erentData($employee),
                'ehealth'    => $this->ehealthData($employee),
                'elearning'  => $this->elearningData($employee),
                'eticket'    => $this->eticketData($employee),
                'ewholesale' => $this->ewholesaleData($employee),
                'edata'      => $this->edataData($employee),
                'elaundry'   => $this->elaundryData($employee),
                'eexchange'  => $this->eexchangeData($employee),
                default      => ['orders' => collect(), 'stats' => []],
            };
        } catch (\Throwable $e) {
            // Module table may not have employee-scoped columns yet
            return ['orders' => collect(), 'stats' => [], 'notice' => $e->getMessage()];
        }
    }

    // ── Per-module data pullers ───────────────────────────────────────────────

    private function efoodData($employee): array
    {
        $orders = \App\Models\Order::with('items')
            ->where('module', 'efood')
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready'])
            ->orderByDesc('created_at')->limit(20)->get();
        return [
            'orders' => $orders,
            'stats'  => [
                'pending'    => $orders->whereIn('status', ['pending'])->count(),
                'preparing'  => $orders->where('status', 'preparing')->count(),
                'ready'      => $orders->where('status', 'ready')->count(),
                'today_done' => \App\Models\Order::where('module','efood')
                    ->where('status','delivered')->whereDate('updated_at', today())->count(),
            ],
        ];
    }

    private function egroceryData($employee): array
    {
        $orders = \App\Models\Order::with('items')
            ->where('module', 'egrocery')
            ->whereIn('status', ['pending', 'confirmed', 'picking', 'packed'])
            ->orderByDesc('created_at')->limit(20)->get();
        return [
            'orders' => $orders,
            'stats'  => [
                'pending'  => $orders->where('status', 'pending')->count(),
                'picking'  => $orders->where('status', 'picking')->count(),
                'packed'   => $orders->where('status', 'packed')->count(),
                'today'    => \App\Models\Order::where('module','egrocery')
                    ->whereDate('created_at', today())->count(),
            ],
        ];
    }

    private function eshopData($employee): array
    {
        $orders = \App\Models\Order::with('items')
            ->where('module', 'eshop')
            ->whereIn('status', ['pending', 'confirmed', 'processing'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $orders, 'stats' => ['pending' => $orders->count()]];
    }

    private function eparcelData($employee): array
    {
        $deliveries = \DB::table('eparcel_orders')
            ->whereIn('status', ['pending', 'picked_up', 'in_transit'])
            ->orderByDesc('created_at')->limit(20)->get();
        return [
            'orders' => $deliveries,
            'stats'  => [
                'pending'    => collect($deliveries)->where('status','pending')->count(),
                'in_transit' => collect($deliveries)->where('status','in_transit')->count(),
                'today'      => \DB::table('eparcel_orders')->whereDate('created_at',today())->count(),
            ],
        ];
    }

    private function emovingData($employee): array
    {
        $jobs = \DB::table('emoving_bookings')
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $jobs, 'stats' => ['pending' => collect($jobs)->where('status','pending')->count()]];
    }

    private function erentData($employee): array
    {
        $requests = \DB::table('erent_bookings')
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $requests, 'stats' => ['pending' => collect($requests)->where('status','pending')->count()]];
    }

    private function ehealthData($employee): array
    {
        $appointments = \DB::table('ehealth_appointments')
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->orderByDesc('appointment_date')->limit(20)->get();
        return [
            'orders' => $appointments,
            'stats'  => [
                'today'    => \DB::table('ehealth_appointments')->whereDate('appointment_date', today())->count(),
                'pending'  => collect($appointments)->where('status','pending')->count(),
                'confirmed'=> collect($appointments)->where('status','confirmed')->count(),
            ],
        ];
    }

    private function elearningData($employee): array
    {
        $enrollments = \DB::table('elearning_enrollments')
            ->whereIn('status', ['active', 'pending'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $enrollments, 'stats' => ['active' => collect($enrollments)->where('status','active')->count()]];
    }

    private function eticketData($employee): array
    {
        $tickets = \DB::table('support_tickets')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('created_at')->limit(20)->get();
        return [
            'orders' => $tickets,
            'stats'  => [
                'open'       => collect($tickets)->where('status','open')->count(),
                'in_progress'=> collect($tickets)->where('status','in_progress')->count(),
            ],
        ];
    }

    private function ewholesaleData($employee): array
    {
        $orders = \DB::table('ewholesale_orders')
            ->whereIn('status', ['pending', 'confirmed', 'processing'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $orders, 'stats' => ['pending' => collect($orders)->where('status','pending')->count()]];
    }

    private function edataData($employee): array
    {
        $orders = \DB::table('edata_orders')
            ->whereIn('status', ['pending', 'processing'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $orders, 'stats' => ['pending' => collect($orders)->count()]];
    }

    private function elaundryData($employee): array
    {
        $orders = \DB::table('elaundry_orders')
            ->whereIn('status', ['pending', 'collected', 'washing', 'ready'])
            ->orderByDesc('created_at')->limit(20)->get();
        return [
            'orders' => $orders,
            'stats'  => [
                'pending'  => collect($orders)->where('status','pending')->count(),
                'washing'  => collect($orders)->where('status','washing')->count(),
                'ready'    => collect($orders)->where('status','ready')->count(),
            ],
        ];
    }

    private function eexchangeData($employee): array
    {
        $transactions = \DB::table('eexchange_transactions')
            ->whereIn('status', ['pending', 'processing'])
            ->orderByDesc('created_at')->limit(20)->get();
        return ['orders' => $transactions, 'stats' => ['pending' => collect($transactions)->where('status','pending')->count()]];
    }
}
