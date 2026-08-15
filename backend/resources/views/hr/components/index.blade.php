@extends('hr.layouts.app')

@section('title', 'Salary Components')
@section('heading', 'Salary Components')

@section('content')
<div class="space-y-6">

    <div class="flex justify-end">
        <a href="{{ route('hr.components.create') }}"
           class="inline-flex items-center gap-2 bg-brand hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Component
        </a>
    </div>

    {{-- Earnings --}}
    @foreach(['earning' => ['Earnings', 'green'], 'deduction' => ['Deductions', 'red']] as $type => [$label, $color])
    @php $group = $components->where('type', $type); @endphp
    @if($group->count())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-{{ $color }}-50">
            <h2 class="text-sm font-semibold text-{{ $color }}-800">{{ $label }}</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-6 py-3 text-left">Name</th>
                    <th class="px-6 py-3 text-left">Code</th>
                    <th class="px-6 py-3 text-left">Calculation</th>
                    <th class="px-6 py-3 text-right">Value</th>
                    <th class="px-6 py-3 text-left">Taxable</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($group as $comp)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $comp->name }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ $comp->code }}</td>
                    <td class="px-6 py-4 text-gray-500 capitalize">{{ $comp->calculation }}</td>
                    <td class="px-6 py-4 text-right font-tabular">
                        @if($comp->calculation === 'percentage')
                        {{ $comp->value }}%
                        @else
                        ${{ number_format($comp->value, 2) }}
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($comp->is_taxable)
                        <span class="text-yellow-600 text-xs">Yes</span>
                        @else
                        <span class="text-gray-400 text-xs">No</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($comp->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-3">
                        <a href="{{ route('hr.components.edit', $comp) }}" class="text-navy hover:text-brand text-xs font-medium">Edit</a>
                        <form method="POST" action="{{ route('hr.components.destroy', $comp) }}" class="inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('Delete this component?')"
                                    class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endforeach

    @if($components->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-400 text-sm">
        No salary components yet. <a href="{{ route('hr.components.create') }}" class="text-brand underline">Add one</a>.
    </div>
    @endif

</div>
@endsection
