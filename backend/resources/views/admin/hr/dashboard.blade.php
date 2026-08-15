@extends('admin.layouts.app')
@section('title', 'HR Dashboard')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">HR Overview</h1>

{{-- KPI Header --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
        <p class="text-3xl font-bold text-blue-600">{{ $activeCount }}</p>
        <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Active Staff</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
        <p class="text-3xl font-bold text-green-600">{{ $attendancePct }}%</p>
        <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Present Today</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
        <p class="text-3xl font-bold text-gray-900">
            {{ number_format($payrollCost) }}
        </p>
        <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">
            Net Payroll {{ $latestRun?->period ?? '—' }}
        </p>
    </div>
    <div class="bg-white rounded-xl border {{ $openCases > 0 ? 'border-red-200 bg-red-50' : 'border-gray-100' }} shadow-sm p-5 text-center">
        <p class="text-3xl font-bold {{ $openCases > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $openCases }}</p>
        <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Open Cases</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
        <p class="text-3xl font-bold text-orange-600">{{ $openPositions }}</p>
        <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Open Positions</p>
    </div>
</div>

{{-- Quick links --}}
<div class="flex flex-wrap gap-3 mb-8">
    @foreach([
        ['HR Attendance',  route('admin.hr.attendance'),  'bg-blue-600'],
        ['Payroll',        route('admin.hr.payroll'),     'bg-green-600'],
        ['Discipline',     route('admin.hr.discipline'),  'bg-red-600'],
        ['Recruitment',    route('admin.hr.recruitment'), 'bg-orange-500'],
        ['Audit Explorer', route('admin.hr.audit'),       'bg-gray-700'],
    ] as [$label, $url, $color])
    <a href="{{ $url }}" class="{{ $color }} hover:opacity-90 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Activity Feed --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-5">Recent Activity</h2>
    <div class="space-y-3">
    @forelse($feed as $log)
        @php
            // Human-readable sentence from action
            $subject = class_basename($log->subject_type ?? '');
            $id = $log->subject_id ? ' #' . $log->subject_id : '';
            $sentences = [
                'employee.created'      => "created a new employee record {$id}",
                'employee.updated'      => "updated employee{$id}",
                'employee.terminated'   => "terminated employee{$id}",
                'employee.reactivated'  => "reactivated employee{$id} [ADMIN OVERRIDE]",
                'payroll.generated'     => "generated payroll run{$id}",
                'payroll.submitted'     => "submitted payroll run{$id} for approval",
                'payroll.approved'      => "approved payroll run{$id}",
                'payroll.rejected'      => "rejected payroll run{$id}",
                'payroll.unlocked'      => "unlocked paid payroll run{$id} [ADMIN OVERRIDE]",
                'payslip.paid'          => "marked payslip{$id} as paid",
                'discipline.opened'     => "opened a disciplinary case{$id}",
                'discipline.investigating' => "updated investigation notes on case{$id}",
                'discipline.closed'     => "closed disciplinary case{$id}",
                'discipline.force_closed'  => "force-closed case{$id} [ADMIN OVERRIDE]",
                'warning.acknowledged'  => "marked warning{$id} as acknowledged",
                'announcement.published'=> "published an announcement{$id}",
                'applicant.stage_changed'=> "moved applicant{$id} to next stage",
                'applicant.hired'       => "hired applicant{$id} as employee",
                'leave.approved'        => "approved leave request{$id}",
                'leave.rejected'        => "rejected leave request{$id}",
                'commission.approved'   => "approved commission{$id}",
            ];
            $sentence = $sentences[$log->action] ?? str_replace('.', ' ', $log->action) . ($id ? " (#{$log->subject_id})" : '');
        @endphp
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600 flex-shrink-0 mt-0.5">
                {{ strtoupper(substr($log->actor_name ?? 'S', 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-700">
                    <span class="font-semibold">{{ $log->actor_name }}</span>
                    {{ $sentence }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $log->created_at->diffForHumans() }}</p>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 text-center py-8">No activity yet.</p>
    @endforelse
    </div>
    <div class="mt-5 pt-4 border-t border-gray-100 text-center">
        <a href="{{ route('admin.hr.audit') }}" class="text-sm text-blue-600 hover:underline">View full audit log →</a>
    </div>
</div>
@endsection
