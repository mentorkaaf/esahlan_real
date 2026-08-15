@extends('admin.layouts.app')
@section('title', 'HR Audit Explorer')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">HR Audit Explorer</h1>
    <span class="text-sm text-gray-400">{{ $logs->total() }} events</span>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6 flex flex-wrap gap-3">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Module / Action prefix</label>
        <select name="action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400">
            <option value="">All actions</option>
            @foreach($actions as $module)
                <option value="{{ $module }}" @selected(request('action') === $module)>{{ ucfirst($module) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Actor (HR Staff)</label>
        <select name="actor_id" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400">
            <option value="">All actors</option>
            @foreach($actors as $actor)
                <option value="{{ $actor->id }}" @selected(request('actor_id') == $actor->id)>{{ $actor->first_name }} {{ $actor->last_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Subject type</label>
        <input type="text" name="subject" value="{{ request('subject') }}" placeholder="e.g. HrEmployee"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-36 focus:ring-2 focus:ring-blue-400">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">From</label>
        <input type="date" name="from" value="{{ request('from') }}"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">To</label>
        <input type="date" name="to" value="{{ request('to') }}"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400">
    </div>
    <div class="self-end flex gap-2">
        <button type="submit" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm font-medium">Filter</button>
        <a href="{{ route('admin.hr.audit') }}" class="px-4 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-100">Reset</a>
    </div>
</form>

{{-- Log table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 w-36">Time</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Actor</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Action</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Subject</th>
                <th class="px-4 py-3 w-10"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="auditBody">
        @forelse($logs as $log)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="toggleDiff({{ $log->id }})">
                <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ $log->created_at->format('d M y H:i') }}</td>
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-800">{{ $log->actor_name }}</span>
                    @if($log->actor_type === 'admin')
                        <span class="ml-1 text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">ADMIN</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <code class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $log->action }}</code>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                    {{ class_basename($log->subject_type ?? '') }}
                    @if($log->subject_id) #{{ $log->subject_id }} @endif
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs">
                    {{ ($log->before || $log->after) ? '▼' : '' }}
                </td>
            </tr>
            @if($log->before || $log->after)
            <tr id="diff-{{ $log->id }}" class="hidden bg-blue-50/30">
                <td colspan="5" class="px-4 py-4">
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <p class="font-semibold text-gray-500 mb-2 uppercase tracking-wide">Before</p>
                            @if($log->before)
                                @foreach($log->before as $key => $val)
                                @php $changed = $log->after && array_key_exists($key, $log->after); @endphp
                                <div class="flex gap-2 py-0.5 {{ $changed ? 'text-red-700 bg-red-50 -mx-2 px-2 rounded' : 'text-gray-600' }}">
                                    <span class="font-medium w-32 flex-shrink-0">{{ $key }}</span>
                                    <span>{{ is_array($val) ? json_encode($val) : $val }}</span>
                                </div>
                                @endforeach
                            @else
                                <span class="text-gray-400 italic">— new record —</span>
                            @endif
                        </div>
                        <div>
                            <p class="font-semibold text-gray-500 mb-2 uppercase tracking-wide">After</p>
                            @if($log->after)
                                @foreach($log->after as $key => $val)
                                @php $changed = $log->before && array_key_exists($key, $log->before); @endphp
                                <div class="flex gap-2 py-0.5 {{ $changed ? 'text-green-700 bg-green-50 -mx-2 px-2 rounded' : 'text-gray-600' }}">
                                    <span class="font-medium w-32 flex-shrink-0">{{ $key }}</span>
                                    <span>{{ is_array($val) ? json_encode($val) : $val }}</span>
                                </div>
                                @endforeach
                            @else
                                <span class="text-gray-400 italic">— deleted —</span>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
            @endif
        @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">No audit logs match the current filters.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>

<script>
function toggleDiff(id) {
    const row = document.getElementById('diff-' + id);
    if (row) row.classList.toggle('hidden');
}
</script>
@endsection
