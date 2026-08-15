<div class="overflow-x-auto">
<table class="min-w-full text-sm divide-y divide-gray-100">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-left font-semibold text-gray-600">Period</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Employees</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Gross</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Deductions</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Net</th>
            <th class="px-4 py-2 text-center font-semibold text-gray-600">Status</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
    @foreach($rows as $row)
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 font-medium">{{ $row['period'] }}</td>
            <td class="px-4 py-2 text-right">{{ $row['employee_count'] }}</td>
            <td class="px-4 py-2 text-right font-variant-numeric tabular-nums">{{ number_format($row['total_gross'],2) }}</td>
            <td class="px-4 py-2 text-right text-red-600">{{ number_format($row['total_deductions'],2) }}</td>
            <td class="px-4 py-2 text-right font-semibold text-green-700">{{ number_format($row['total_net'],2) }}</td>
            <td class="px-4 py-2 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $row['status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($row['status']) }}
                </span>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
