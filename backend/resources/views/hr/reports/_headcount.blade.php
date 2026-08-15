<p class="text-sm text-gray-500 mb-4">Total employees: <strong>{{ $total }}</strong></p>
<div class="overflow-x-auto">
<table class="min-w-full text-sm divide-y divide-gray-100">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-left font-semibold text-gray-600">Department</th>
            <th class="px-4 py-2 text-right font-semibold text-green-600">Active</th>
            <th class="px-4 py-2 text-right font-semibold text-yellow-600">Probation</th>
            <th class="px-4 py-2 text-right font-semibold text-red-600">Terminated</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Total</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
    @foreach($rows as $row)
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 font-medium">{{ $row['department'] }}</td>
            <td class="px-4 py-2 text-right text-green-700">{{ $row['active'] }}</td>
            <td class="px-4 py-2 text-right text-yellow-700">{{ $row['probation'] }}</td>
            <td class="px-4 py-2 text-right text-red-600">{{ $row['terminated'] }}</td>
            <td class="px-4 py-2 text-right font-semibold">{{ $row['total'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
