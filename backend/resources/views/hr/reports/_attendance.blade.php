<form method="GET" class="flex items-center gap-3 mb-5">
    <input type="hidden" name="report" value="attendance">
    <label class="text-sm text-gray-600">Month:</label>
    <input type="month" name="month" value="{{ $month }}"
           class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400">
    <button type="submit" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm">View</button>
</form>
<div class="grid grid-cols-4 gap-4 mb-5">
    @foreach(['Present'=>[$present,'green'],'Absent'=>[$absent,'red'],'Leave'=>[$leave,'yellow'],'Late'=>[$late,'orange']] as $label=>[$val,$color])
    <div class="p-4 rounded-lg bg-{{ $color }}-50 border border-{{ $color }}-100 text-center">
        <p class="text-2xl font-bold text-{{ $color }}-700">{{ $val }}</p>
        <p class="text-xs text-{{ $color }}-600">{{ $label }}</p>
    </div>
    @endforeach
</div>
<p class="text-sm text-gray-500">Total attendance records this month: {{ $total }}</p>
