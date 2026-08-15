@extends('hr.layouts.app')
@section('title', 'Audit Log')
@section('heading', 'Audit Log')

@section('content')
<form method="GET" class="flex gap-3 mb-6 max-w-xl">
    <input type="text" name="action" value="{{ request('action') }}" placeholder="Filter by action..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
    <input type="text" name="actor_name" value="{{ request('actor_name') }}" placeholder="Filter by actor..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
    <button type="submit" class="bg-[#1B1444] text-white px-4 py-2.5 rounded-lg text-sm hover:bg-[#2D2467] transition-colors">Filter</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Time</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actor</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Subject</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($logs as $log)
            <tr class="hover:bg-gray-50/50">
                <td class="px-6 py-3 text-gray-400 text-xs whitespace-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-700 text-xs">{{ $log->actor_name }}</div>
                    <div class="text-gray-400 text-xs capitalize">{{ $log->actor_type }}</div>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        {{ str_contains($log->action, 'created') ? 'bg-green-100 text-green-700' :
                           (str_contains($log->action, 'deleted') || str_contains($log->action, 'terminated') ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">
                        {{ $log->action }}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                    {{ $log->subject_type ? class_basename($log->subject_type) : '—' }}
                    @if($log->subject_id) #{{ $log->subject_id }} @endif
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs font-mono">{{ $log->ip }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No audit logs yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
