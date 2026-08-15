<div class="overflow-x-auto">
<table class="min-w-full text-sm divide-y divide-gray-100">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-left font-semibold text-gray-600">Month</th>
            <th class="px-4 py-2 text-right font-semibold text-green-600">Hires</th>
            <th class="px-4 py-2 text-right font-semibold text-red-600">Exits</th>
            <th class="px-4 py-2 text-right font-semibold text-gray-600">Net</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
    @foreach($rows as $row)
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 font-medium">{{ $row['label'] }}</td>
            <td class="px-4 py-2 text-right text-green-700">+{{ $row['hires'] }}</td>
            <td class="px-4 py-2 text-right text-red-600">-{{ $row['exits'] }}</td>
            <td class="px-4 py-2 text-right font-semibold {{ ($row['hires'] - $row['exits']) >= 0 ? 'text-green-700' : 'text-red-600' }}">
                {{ ($row['hires'] - $row['exits']) >= 0 ? '+' : '' }}{{ $row['hires'] - $row['exits'] }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
