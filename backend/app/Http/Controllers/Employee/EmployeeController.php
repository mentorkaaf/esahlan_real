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
    // My Workspace (module work interface) — Phase 3
    // ─────────────────────────────────────────────────────────────────────────
    public function workspace(Request $request, string $slug)
    {
        $employee = $this->me();

        // Verify assignment
        $assignment = WorkforceAssignment::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereHas('module', fn($q) => $q->where('slug', $slug))
            ->with(['module', 'moduleDepartment', 'modulePosition', 'moduleRole'])
            ->first();

        if (! $assignment) {
            abort(403, 'Tani module-kan lagugu xilsaarnayn.');
        }

        $module     = $assignment->module;
        $statusFilter = $request->get('status', 'active'); // active|all|done
        $search       = $request->get('search', '');

        // Pull work items for this module
        $workData  = $this->fetchModuleWork($slug, $statusFilter, $search);

        // Stats (always unfiltered counts)
        $stats = $this->moduleStats($slug);

        // Status transitions available per module
        $transitions = $this->moduleTransitions($slug);

        // Performance
        $period    = now()->format('Y-m');
        $composite = EmployeeModuleMetric::compositeScore($employee->id, $module->id, $period);

        return view('employee.workspace', compact(
            'employee', 'assignment', 'module',
            'workData', 'stats', 'transitions',
            'composite', 'period', 'statusFilter', 'search'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update work item status (AJAX + form POST)
    // ─────────────────────────────────────────────────────────────────────────
    public function updateStatus(Request $request, string $slug, int $itemId)
    {
        $employee = $this->me();

        // Re-verify assignment
        $hasAccess = WorkforceAssignment::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereHas('module', fn($q) => $q->where('slug', $slug))
            ->exists();

        if (! $hasAccess) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Access denied'], 403)
                : back()->withErrors(['error' => 'Access denied']);
        }

        $newStatus = $request->input('status');
        $note      = $request->input('note', '');

        // Determine table + update
        $updated = $this->applyStatusChange($slug, $itemId, $newStatus, $note, $employee);

        if ($request->expectsJson()) {
            return response()->json(['success' => $updated, 'status' => $newStatus]);
        }

        return back()->with($updated ? 'success' : 'error',
            $updated ? 'Xaaladda si guul leh loo beddelay: ' . $newStatus : 'Wax khalad ah dhacay.');
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
    // Module work data fetchers — Phase 3
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Modules that use the unified `orders` table (module_slug filter).
     * All others have dedicated tables.
     */
    private const ORDERS_TABLE_MODULES = [
        'efood','egrocery','eshop','eparcel','emoving','erent',
        'ewholesale','elaundry','elearning','edata',
    ];

    /** Active statuses per module (shown by default) */
    private const ACTIVE_STATUSES = [
        'efood'      => ['pending','confirmed','preparing','ready','dispatched'],
        'egrocery'   => ['pending','confirmed','picking','packed','dispatched'],
        'eshop'      => ['pending','confirmed','processing','packed'],
        'eparcel'    => ['pending','confirmed','picked_up','in_transit'],
        'emoving'    => ['pending','confirmed','in_progress'],
        'erent'      => ['pending','confirmed','active'],
        'ewholesale' => ['pending','confirmed','processing','packing'],
        'elaundry'   => ['pending','collected','washing','drying','ready'],
        'elearning'  => ['pending','active','in_progress'],
        'edata'      => ['pending','processing'],
        'ehealth'    => ['pending','confirmed','in_progress'],
        'eexchange'  => ['pending','processing'],
        'eticket'    => ['open','in_progress','pending'],
    ];

    /** Done statuses per module */
    private const DONE_STATUSES = [
        'efood'      => ['delivered','cancelled'],
        'egrocery'   => ['delivered','cancelled'],
        'eshop'      => ['delivered','cancelled','refunded'],
        'eparcel'    => ['delivered','failed','returned'],
        'emoving'    => ['completed','cancelled'],
        'erent'      => ['completed','cancelled'],
        'ewholesale' => ['delivered','cancelled'],
        'elaundry'   => ['delivered','cancelled'],
        'elearning'  => ['completed','cancelled'],
        'edata'      => ['completed','failed'],
        'ehealth'    => ['completed','cancelled'],
        'eexchange'  => ['completed','failed','cancelled'],
        'eticket'    => ['resolved','closed'],
    ];

    private function fetchModuleWork(string $slug, string $filter, string $search): \Illuminate\Support\Collection
    {
        $statuses = match($filter) {
            'active' => self::ACTIVE_STATUSES[$slug]  ?? [],
            'done'   => self::DONE_STATUSES[$slug]    ?? [],
            default  => [], // all
        };

        try {
            if ($slug === 'ehealth') {
                return $this->fetchAppointments($statuses, $search);
            }
            if ($slug === 'eexchange') {
                return $this->fetchExchangeOrders($statuses, $search);
            }
            if ($slug === 'eticket') {
                return $this->fetchTickets($statuses, $search);
            }
            // All others: unified orders table
            return $this->fetchOrders($slug, $statuses, $search);

        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function fetchOrders(string $slug, array $statuses, string $search): \Illuminate\Support\Collection
    {
        $q = \App\Models\Order::with(['items','vendor','user'])
            ->where('module_slug', $slug)
            ->orderByDesc('created_at')
            ->limit(50);

        if ($statuses) $q->whereIn('status', $statuses);
        if ($search)   $q->where(fn($sq) =>
            $sq->where('order_number', 'like', "%$search%")
               ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%$search%"))
               ->orWhereHas('vendor', fn($vq) => $vq->where('name', 'like', "%$search%"))
        );

        return $q->get()->map(fn($o) => [
            'id'           => $o->id,
            'ref'          => $o->order_number,
            'customer'     => $o->user?->name ?? '—',
            'vendor'       => $o->vendor?->name ?? '—',
            'status'       => $o->status,
            'payment'      => $o->payment_status,
            'amount'       => $o->total_amount,
            'items_count'  => $o->items->count(),
            'items'        => $o->items->map(fn($i) => ['name' => $i->name, 'qty' => $i->quantity, 'price' => $i->price]),
            'address'      => is_string($o->delivery_address) ? $o->delivery_address : ($o->delivery_address['address'] ?? null),
            'notes'        => $o->notes ?? $o->note,
            'placed_at'    => $o->placed_at ?? $o->created_at,
            'type'         => 'order',
            'slug'         => $slug,
        ]);
    }

    private function fetchAppointments(array $statuses, string $search): \Illuminate\Support\Collection
    {
        $q = \DB::table('appointments')
            ->join('users', 'users.id', '=', 'appointments.user_id')
            ->select('appointments.*', 'users.name as customer_name')
            ->orderByDesc('appointments.scheduled_at')
            ->limit(50);

        if ($statuses) $q->whereIn('appointments.status', $statuses);
        if ($search)   $q->where('users.name', 'like', "%$search%");

        return collect($q->get())->map(fn($a) => [
            'id'        => $a->id,
            'ref'       => 'APT-' . str_pad($a->id, 5, '0', STR_PAD_LEFT),
            'customer'  => $a->customer_name ?? '—',
            'vendor'    => 'eHealth',
            'status'    => $a->status ?: 'pending',
            'amount'    => null,
            'notes'     => $a->notes,
            'placed_at' => $a->scheduled_at,
            'type'      => 'appointment',
            'slug'      => 'ehealth',
            'extra'     => ['type' => $a->type, 'scheduled' => $a->scheduled_at],
        ]);
    }

    private function fetchExchangeOrders(array $statuses, string $search): \Illuminate\Support\Collection
    {
        $q = \DB::table('exchange_orders')
            ->orderByDesc('created_at')
            ->limit(50);

        if ($statuses) $q->whereIn('status', $statuses);
        if ($search)   $q->where('reference', 'like', "%$search%")
                          ->orWhere('recipient_phone', 'like', "%$search%");

        return collect($q->get())->map(fn($e) => [
            'id'        => $e->id,
            'ref'       => $e->reference,
            'customer'  => $e->recipient_phone ?? '—',
            'vendor'    => $e->from_wallet . ' → ' . $e->to_wallet,
            'status'    => $e->status ?: 'pending',
            'amount'    => $e->sent_amount,
            'notes'     => $e->note,
            'placed_at' => $e->created_at,
            'type'      => 'exchange',
            'slug'      => 'eexchange',
            'extra'     => ['rate' => $e->rate, 'converted' => $e->converted_amount, 'fee' => $e->fee_amount],
        ]);
    }

    private function fetchTickets(array $statuses, string $search): \Illuminate\Support\Collection
    {
        $q = \DB::table('global_support_tickets')
            ->orderByDesc('created_at')
            ->limit(50);

        if ($statuses) $q->whereIn('status', $statuses);
        if ($search)   $q->where(fn($sq) =>
            $sq->where('ticket_number', 'like', "%$search%")
               ->orWhere('subject', 'like', "%$search%")
               ->orWhere('name', 'like', "%$search%")
        );

        return collect($q->get())->map(fn($t) => [
            'id'        => $t->id,
            'ref'       => $t->ticket_number,
            'customer'  => $t->name ?? $t->email ?? '—',
            'vendor'    => ucfirst($t->category ?? 'General'),
            'status'    => $t->status ?: 'open',
            'amount'    => null,
            'notes'     => $t->message,
            'placed_at' => $t->created_at,
            'type'      => 'ticket',
            'slug'      => 'eticket',
            'extra'     => ['priority' => $t->priority, 'subject' => $t->subject],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stats (always live counts from DB)
    // ─────────────────────────────────────────────────────────────────────────
    private function moduleStats(string $slug): array
    {
        try {
            if ($slug === 'ehealth') {
                return [
                    'today'   => \DB::table('appointments')->whereDate('scheduled_at', today())->count(),
                    'active'  => \DB::table('appointments')->whereIn('status', self::ACTIVE_STATUSES['ehealth'] ?? [])->count(),
                    'done'    => \DB::table('appointments')->whereIn('status', self::DONE_STATUSES['ehealth'] ?? [])->count(),
                    'total'   => \DB::table('appointments')->count(),
                ];
            }
            if ($slug === 'eexchange') {
                return [
                    'active'  => \DB::table('exchange_orders')->whereIn('status', self::ACTIVE_STATUSES['eexchange'] ?? [])->count(),
                    'done'    => \DB::table('exchange_orders')->whereIn('status', self::DONE_STATUSES['eexchange'] ?? [])->count(),
                    'today'   => \DB::table('exchange_orders')->whereDate('created_at', today())->count(),
                    'total'   => \DB::table('exchange_orders')->count(),
                ];
            }
            if ($slug === 'eticket') {
                return [
                    'open'    => \DB::table('global_support_tickets')->whereIn('status', ['open','pending'])->count(),
                    'active'  => \DB::table('global_support_tickets')->where('status','in_progress')->count(),
                    'done'    => \DB::table('global_support_tickets')->whereIn('status', ['resolved','closed'])->count(),
                    'total'   => \DB::table('global_support_tickets')->count(),
                ];
            }
            // Orders table modules
            $active = \App\Models\Order::where('module_slug', $slug)
                ->whereIn('status', self::ACTIVE_STATUSES[$slug] ?? [])->count();
            $done   = \App\Models\Order::where('module_slug', $slug)
                ->whereIn('status', self::DONE_STATUSES[$slug] ?? [])->count();
            $today  = \App\Models\Order::where('module_slug', $slug)
                ->whereDate('created_at', today())->count();
            $total  = \App\Models\Order::where('module_slug', $slug)->count();

            return compact('active', 'done', 'today', 'total');

        } catch (\Throwable) {
            return ['active' => 0, 'done' => 0, 'today' => 0, 'total' => 0];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status transition map
    // ─────────────────────────────────────────────────────────────────────────
    private function moduleTransitions(string $slug): array
    {
        return match($slug) {
            'efood' => [
                'pending'    => [['status'=>'confirmed',  'label'=>'Confirm',    'color'=>'blue'],   ['status'=>'cancelled','label'=>'Cancel','color'=>'red']],
                'confirmed'  => [['status'=>'preparing',  'label'=>'Preparing',  'color'=>'yellow'], ['status'=>'cancelled','label'=>'Cancel','color'=>'red']],
                'preparing'  => [['status'=>'ready',      'label'=>'Ready',      'color'=>'green']],
                'ready'      => [['status'=>'dispatched', 'label'=>'Dispatch',   'color'=>'indigo']],
                'dispatched' => [['status'=>'delivered',  'label'=>'Delivered',  'color'=>'green']],
            ],
            'egrocery','eshop','ewholesale' => [
                'pending'   => [['status'=>'confirmed', 'label'=>'Confirm',  'color'=>'blue'],  ['status'=>'cancelled','label'=>'Cancel','color'=>'red']],
                'confirmed' => [['status'=>'picking',   'label'=>'Picking',  'color'=>'yellow']],
                'picking'   => [['status'=>'packed',    'label'=>'Packed',   'color'=>'green']],
                'packed'    => [['status'=>'dispatched','label'=>'Dispatch', 'color'=>'indigo']],
                'dispatched'=> [['status'=>'delivered', 'label'=>'Delivered','color'=>'green']],
            ],
            'eparcel' => [
                'pending'    => [['status'=>'confirmed',  'label'=>'Accept',     'color'=>'blue'],  ['status'=>'failed','label'=>'Reject','color'=>'red']],
                'confirmed'  => [['status'=>'picked_up',  'label'=>'Picked Up',  'color'=>'yellow']],
                'picked_up'  => [['status'=>'in_transit', 'label'=>'In Transit', 'color'=>'indigo']],
                'in_transit' => [['status'=>'delivered',  'label'=>'Delivered',  'color'=>'green'], ['status'=>'failed','label'=>'Failed','color'=>'red']],
            ],
            'elaundry' => [
                'pending'   => [['status'=>'collected', 'label'=>'Collected',  'color'=>'blue']],
                'collected' => [['status'=>'washing',   'label'=>'Washing',    'color'=>'yellow']],
                'washing'   => [['status'=>'drying',    'label'=>'Drying',     'color'=>'indigo']],
                'drying'    => [['status'=>'ready',     'label'=>'Ready',      'color'=>'green']],
                'ready'     => [['status'=>'delivered', 'label'=>'Delivered',  'color'=>'green']],
            ],
            'emoving','erent' => [
                'pending'   => [['status'=>'confirmed',  'label'=>'Confirm',   'color'=>'blue'], ['status'=>'cancelled','label'=>'Cancel','color'=>'red']],
                'confirmed' => [['status'=>'in_progress','label'=>'Start Job', 'color'=>'yellow']],
                'in_progress'=>[['status'=>'completed',  'label'=>'Complete',  'color'=>'green']],
            ],
            'ehealth' => [
                'pending'    => [['status'=>'confirmed', 'label'=>'Confirm',  'color'=>'blue'],  ['status'=>'cancelled','label'=>'Cancel','color'=>'red']],
                'confirmed'  => [['status'=>'in_progress','label'=>'Start',   'color'=>'yellow']],
                'in_progress'=> [['status'=>'completed', 'label'=>'Complete', 'color'=>'green']],
            ],
            'eexchange' => [
                'pending'    => [['status'=>'processing','label'=>'Process',  'color'=>'blue'],  ['status'=>'cancelled','label'=>'Reject','color'=>'red']],
                'processing' => [['status'=>'completed', 'label'=>'Complete', 'color'=>'green'], ['status'=>'failed','label'=>'Failed','color'=>'red']],
            ],
            'eticket' => [
                'open'       => [['status'=>'in_progress','label'=>'Take Over','color'=>'blue']],
                'in_progress'=> [['status'=>'resolved',   'label'=>'Resolve',  'color'=>'green'], ['status'=>'closed','label'=>'Close','color'=>'gray']],
            ],
            default => [],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Apply status change to the correct table
    // ─────────────────────────────────────────────────────────────────────────
    private function applyStatusChange(string $slug, int $itemId, string $newStatus, string $note, $employee): bool
    {
        try {
            if ($slug === 'ehealth') {
                return (bool) \DB::table('appointments')->where('id', $itemId)
                    ->update(['status' => $newStatus, 'notes' => $note ?: \DB::raw('notes')]);
            }
            if ($slug === 'eexchange') {
                return (bool) \DB::table('exchange_orders')->where('id', $itemId)
                    ->update(['status' => $newStatus]);
            }
            if ($slug === 'eticket') {
                $update = ['status' => $newStatus];
                if ($newStatus === 'resolved') $update['resolved_at'] = now();
                if ($newStatus === 'in_progress') $update['assigned_to'] = $employee->full_name;
                return (bool) \DB::table('global_support_tickets')->where('id', $itemId)->update($update);
            }
            // Orders table
            $timestamps = [
                'confirmed'   => ['confirmed_at'  => now()],
                'ready'       => ['ready_at'       => now()],
                'dispatched'  => ['dispatched_at'  => now()],
                'picked_up'   => ['picked_up_at'   => now()],
                'delivered'   => ['delivered_at'   => now()],
                'cancelled'   => ['cancelled_at'   => now(), 'cancellation_reason' => $note],
            ];
            $update = array_merge(['status' => $newStatus], $timestamps[$newStatus] ?? []);
            return (bool) \DB::table('orders')->where('id', $itemId)->where('module_slug', $slug)->update($update);
        } catch (\Throwable) {
            return false;
        }
    }
}
