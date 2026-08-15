@extends('hr.layouts.app')
@section('title', $employee->full_name)
@section('heading', $employee->full_name)

@section('content')
<div class="max-w-4xl space-y-6">

    {{-- Header card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start gap-5">
            @if($employee->photo)
            <img src="{{ Storage::url($employee->photo) }}" class="w-20 h-20 rounded-xl object-cover flex-shrink-0">
            @else
            <div class="w-20 h-20 rounded-xl bg-[#1B1444] flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                {{ $employee->initials }}
            </div>
            @endif
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-xl font-bold text-gray-900">{{ $employee->full_name }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $employee->status_color === 'green' ? 'bg-green-100 text-green-700' :
                           ($employee->status_color === 'yellow' ? 'bg-yellow-100 text-yellow-700' :
                           ($employee->status_color === 'red' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                        {{ ucfirst($employee->status) }}
                    </span>
                </div>
                <div class="text-gray-500 text-sm mt-1">
                    {{ $employee->position?->title }} · {{ $employee->department?->name }}
                </div>
                <div class="text-gray-400 text-xs mt-1">{{ $employee->employee_no }}</div>
                <div class="flex gap-3 mt-4 flex-wrap">
                    <a href="{{ route('hr.employees.edit', $employee) }}"
                       class="bg-[#1B1444] text-white text-xs px-4 py-2 rounded-lg hover:bg-[#2D2467] transition-colors">Edit</a>
                    @if(Auth::guard('hr')->user()->isManager() && $employee->status !== 'terminated')
                    <button onclick="document.getElementById('terminate-modal').classList.remove('hidden')"
                            class="bg-red-50 text-red-600 text-xs px-4 py-2 rounded-lg hover:bg-red-100 transition-colors">Terminate</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Personal</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-400">Gender</dt><dd class="text-gray-700 capitalize">{{ $employee->gender }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Date of Birth</dt><dd class="text-gray-700">{{ $employee->dob?->format('d M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Phone</dt><dd class="text-gray-700">{{ $employee->phone }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Email</dt><dd class="text-gray-700">{{ $employee->email ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">District</dt><dd class="text-gray-700">{{ $employee->district ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Address</dt><dd class="text-gray-700">{{ $employee->address ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Employment</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-400">Type</dt><dd class="text-gray-700 capitalize">{{ str_replace('_',' ',$employee->employment_type) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Hire Date</dt><dd class="text-gray-700">{{ $employee->hire_date?->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-400">Probation End</dt><dd class="text-gray-700">{{ $employee->probation_end?->format('d M Y') ?? '—' }}</dd></div>
                @if(Auth::guard('hr')->user()->canDo('view_salary'))
                <div class="flex justify-between"><dt class="text-gray-400">Base Salary</dt><dd class="text-gray-700">${{ number_format($employee->base_salary, 2) }}</dd></div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Contracts --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Contracts</h3>
            <a href="{{ route('hr.employees.contracts.create', $employee) }}"
               class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors">+ Add</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($employee->contracts as $contract)
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <div class="font-medium text-sm text-gray-800">{{ $contract->type }}</div>
                    <div class="text-xs text-gray-400">{{ $contract->start_date?->format('d M Y') }} — {{ $contract->end_date?->format('d M Y') ?? 'Ongoing' }}</div>
                </div>
                <div class="text-right">
                    @if(Auth::guard('hr')->user()->canDo('view_salary'))
                    <div class="text-sm font-semibold text-gray-700">${{ number_format($contract->salary, 2) }}</div>
                    @endif
                    <span class="text-xs {{ $contract->status === 'active' ? 'text-green-600' : 'text-gray-400' }}">{{ ucfirst($contract->status) }}</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-6 text-center text-gray-400 text-sm">No contracts</div>
            @endforelse
        </div>
    </div>

    {{-- Documents --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Documents</h3>
            <a href="{{ route('hr.employees.documents.create', $employee) }}"
               class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors">+ Upload</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($employee->documents as $doc)
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <div class="font-medium text-sm text-gray-800">{{ $doc->title }}</div>
                    <div class="text-xs text-gray-400">{{ $doc->type_label }}{{ $doc->expires_at ? ' · Expires ' . $doc->expires_at->format('d M Y') : '' }}</div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('hr.documents.show', $doc) }}" target="_blank"
                       class="text-xs text-[#1B1444] hover:underline">View</a>
                    <form method="POST" action="{{ route('hr.documents.destroy', $doc) }}"
                          onsubmit="return confirm('Delete this document?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="px-6 py-6 text-center text-gray-400 text-sm">No documents</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Terminate modal --}}
@if(Auth::guard('hr')->user()->isManager())
<div id="terminate-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full">
        <h3 class="font-bold text-gray-900 mb-2">Terminate Employee</h3>
        <p class="text-sm text-gray-500 mb-4">This action cannot be undone. Please provide a reason.</p>
        <form method="POST" action="{{ route('hr.employees.terminate', $employee) }}">
            @csrf
            <textarea name="reason" required rows="3" placeholder="Reason for termination..."
                      class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 mb-4"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">Terminate</button>
                <button type="button" onclick="document.getElementById('terminate-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
