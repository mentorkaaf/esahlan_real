@extends('hr.layouts.app')
@section('title', 'Leave Balances')
@section('heading', 'Leave Balances — ' . $year)

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex gap-3 items-center">
        <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]" onchange="this.form.submit()">
            @foreach(range(date('Y'), date('Y')-2) as $y)
            <option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50">Employee</th>
                @foreach($leaveTypes as $lt)
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider" colspan="3">{{ $lt->name }}</th>
                @endforeach
            </tr>
            <tr class="border-b border-gray-100">
                <th class="sticky left-0 bg-gray-50 px-6 py-2"></th>
                @foreach($leaveTypes as $lt)
                <th class="text-center px-2 py-1 text-xs text-gray-400 font-normal">Alloc</th>
                <th class="text-center px-2 py-1 text-xs text-gray-400 font-normal">Used</th>
                <th class="text-center px-2 py-1 text-xs text-green-600 font-semibold">Avail</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($employees as $emp)
            @php $balMap = $emp->leaveBalances->keyBy('leave_type_id'); @endphp
            <tr class="hover:bg-gray-50/50">
                <td class="px-6 py-3 sticky left-0 bg-white">
                    <div class="font-medium text-gray-900">{{ $emp->full_name }}</div>
                    <div class="text-gray-400 text-xs">{{ $emp->department?->name }}</div>
                </td>
                @foreach($leaveTypes as $lt)
                @php $bal = $balMap->get($lt->id); @endphp
                <td class="text-center px-2 py-3 text-gray-600">{{ $bal?->allocated ?? $lt->days_per_year }}</td>
                <td class="text-center px-2 py-3 text-gray-600">{{ $bal?->used ?? 0 }}</td>
                <td class="text-center px-2 py-3 font-semibold {{ ($bal?->available ?? $lt->days_per_year) > 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ $bal?->available ?? $lt->days_per_year }}
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
