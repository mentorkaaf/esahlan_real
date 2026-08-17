@extends('hr.layouts.app')
@section('title', 'Daily Attendance')
@section('heading', 'Attendance — ' . \Carbon\Carbon::parse($date)->format('D, d M Y'))

@section('content')
{{-- Controls bar --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex gap-3 items-center">
        <input type="date" name="date" value="{{ $date }}"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
               onchange="this.form.submit()">
    </form>

    <div class="flex gap-3">
        <a href="{{ route('hr.attendance.import') }}"
           class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            CSV Import
        </a>
        {{-- Bulk mark present --}}
        <button onclick="document.getElementById('bulk-modal').classList.remove('hidden')"
                class="bg-[#1B1444] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#2D2467] transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            Mark All Present
        </button>
    </div>
</div>

{{-- Summary cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @foreach(['present'=>['green','Present'],'late'=>['yellow','Late'],'absent'=>['red','Absent'],'leave'=>['blue','On Leave']] as $key=>[$color,$label])
    <div class="bg-white rounded-xl border border-gray-100 p-4 flex items-center gap-3">
        <div class="w-3 h-3 rounded-full bg-{{ $color }}-400 flex-shrink-0"></div>
        <div>
            <div class="text-2xl font-bold text-gray-900">{{ $summary[$key] }}</div>
            <div class="text-xs text-gray-500">{{ $label }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Attendance table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Department</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Check In</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Check Out</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Late (min)</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="attendance-table">
            @foreach($employees as $emp)
            @php
                $att = $emp->attendance->first(); // eager load already filtered by date
                $status = $att?->status ?? 'absent';
            @endphp
            <tr class="hover:bg-gray-50/50" data-emp="{{ $emp->id }}">
                <td class="px-6 py-3">
                    <div class="font-medium text-gray-900 text-sm">{{ $emp->full_name }}</div>
                    <div class="text-gray-400 text-xs">{{ $emp->employee_no }}</div>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ $emp->department?->name }}</td>
                <td class="px-4 py-3 text-center">
                    <span class="status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $att ? $att->status_badge_class : 'bg-red-100 text-red-700' }}">
                        {{ ucfirst(str_replace('_',' ', $status)) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-center text-gray-600 text-sm">{{ $att?->check_in ?? '—' }}</td>
                <td class="px-4 py-3 text-center text-gray-600 text-sm">{{ $att?->check_out ?? '—' }}</td>
                <td class="px-4 py-3 text-center text-gray-600 text-sm">{{ $att?->late_minutes ?: '—' }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-1 flex-wrap">
                        @foreach(['present'=>'P','late'=>'L','absent'=>'A','leave'=>'Off'] as $s=>$lbl)
                        <button onclick="quickMark({{ $emp->id }}, '{{ $s }}', this)"
                                class="px-2 py-1 rounded text-xs font-medium border transition-colors
                                    {{ $status === $s ? 'bg-[#1B1444] text-white border-[#1B1444]' : 'bg-white text-gray-600 border-gray-300 hover:border-[#1B1444]' }}">
                            {{ $lbl }}
                        </button>
                        @endforeach
                        <button onclick="openDetail({{ $emp->id }}, '{{ $date }}', {{ json_encode($att) }})"
                                class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200">
                            ✎
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Bulk present modal --}}
<div id="bulk-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full">
        <h3 class="font-bold text-gray-900 mb-4">Mark All Present</h3>
        <form method="POST" action="{{ route('hr.attendance.bulk_present') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Shift</label>
                <select name="shift_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    @foreach($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->name }} ({{ $shift->start_time }}–{{ $shift->end_time }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-[#1B1444] text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-[#2D2467]">Confirm</button>
                <button type="button" onclick="document.getElementById('bulk-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Detail/correction modal --}}
<div id="detail-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full">
        <h3 class="font-bold text-gray-900 mb-4">Mark Attendance</h3>
        <form id="detail-form" onsubmit="submitDetail(event)">
            @csrf
            <input type="hidden" id="d-employee_id" name="employee_id">
            <input type="hidden" name="date" value="{{ $date }}">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                    <select id="d-status" name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="half_day">Half Day</option>
                        <option value="leave">Leave</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Check In</label>
                        <input type="time" id="d-check_in" name="check_in" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Check Out</label>
                        <input type="time" id="d-check_out" name="check_out" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Note</label>
                    <input type="text" id="d-note" name="note" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                </div>
            </div>
            <div id="d-error" class="hidden mt-3 text-red-600 text-sm"></div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="flex-1 bg-[#F7941D] text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-[#E07800]">Save</button>
                <button type="button" onclick="document.getElementById('detail-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const MARK_URL = '{{ route('hr.attendance.mark') }}';
const TOKEN = document.querySelector('meta[name=csrf-token]').content;

function quickMark(empId, status, btn) {
    const row = document.querySelector(`tr[data-emp="${empId}"]`);
    fetch(MARK_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN,'Accept':'application/json'},
        body: JSON.stringify({employee_id: empId, date: '{{ $date }}', status})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            row.querySelectorAll('button').forEach(b => {
                if (b.textContent.trim() !== '✎') b.className = 'px-2 py-1 rounded text-xs font-medium border transition-colors bg-white text-gray-600 border-gray-300 hover:border-[#1B1444]';
            });
            btn.className = 'px-2 py-1 rounded text-xs font-medium border transition-colors bg-[#1B1444] text-white border-[#1B1444]';
            const badge = row.querySelector('.status-badge');
            badge.textContent = d.status.charAt(0).toUpperCase() + d.status.slice(1).replace('_',' ');
        } else {
            alert(d.error || 'Error marking attendance');
        }
    });
}

function openDetail(empId, date, att) {
    document.getElementById('d-employee_id').value = empId;
    document.getElementById('d-status').value = att?.status || 'present';
    document.getElementById('d-check_in').value = att?.check_in || '';
    document.getElementById('d-check_out').value = att?.check_out || '';
    document.getElementById('d-note').value = att?.note || '';
    document.getElementById('d-error').classList.add('hidden');
    document.getElementById('detail-modal').classList.remove('hidden');
}

function submitDetail(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form));
    fetch(MARK_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN,'Accept':'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('detail-modal').classList.add('hidden');
            location.reload();
        } else {
            const err = document.getElementById('d-error');
            err.textContent = d.error || 'Error saving';
            err.classList.remove('hidden');
        }
    });
}
</script>
@endpush
@endsection
