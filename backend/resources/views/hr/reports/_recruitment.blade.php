<div class="space-y-3">
@php $maxCount = max(array_column($rows,'count') ?: [1]); @endphp
@foreach($rows as $row)
<div class="flex items-center gap-4">
    <div class="w-28 text-sm font-medium text-gray-700 text-right">{{ $row['stage'] }}</div>
    <div class="flex-1 bg-gray-100 rounded-full h-7 relative">
        <div class="bg-blue-500 h-7 rounded-full flex items-center justify-end pr-3"
             style="width: {{ $maxCount > 0 ? max(round($row['count'] / $maxCount * 100), 4) : 4 }}%">
        </div>
    </div>
    <div class="w-10 text-sm font-semibold text-gray-800 text-right">{{ $row['count'] }}</div>
</div>
@endforeach
</div>
