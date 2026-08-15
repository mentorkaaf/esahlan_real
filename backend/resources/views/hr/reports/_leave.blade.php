<div class="overflow-x-auto">
<table class="min-w-full text-sm divide-y divide-gray-100">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-left font-semibold text-gray-600">Leave Type</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Allocated (days)</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Used</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Pending</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">% Used</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
    @foreach($rows as $row)
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 font-medium">{{ $row['type'] }}</td>
            <td class="px-4 py-2 text-right">{{ number_format($row['allocated'],1) }}</td>
            <td class="px-4 py-2 text-right text-blue-700">{{ number_format($row['used'],1) }}</td>
            <td class="px-4 py-2 text-right text-yellow-700">{{ number_format($row['pending'],1) }}</td>
            <td class="px-4 py-2 text-right">
                <div class="flex items-center justify-end gap-2">
                    <div class="w-20 bg-gray-200 rounded-full h-1.5">
                        <div class="bg-blue-500 h-1.5 rounded-full" style="width:{{ min($row['pct_used'],100) }}%"></div>
                    </div>
                    <span>{{ $row['pct_used'] }}%</span>
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
