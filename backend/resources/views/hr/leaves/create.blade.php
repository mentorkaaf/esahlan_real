@extends('hr.layouts.app')
@section('title', 'New Leave Request')
@section('heading', 'New Leave Request')

@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.leaves.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Employee <span class="text-red-500">*</span></label>
                <select name="employee_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">Select Employee</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ old('employee_id', $selected?->id)==$emp->id?'selected':'' }}>{{ $emp->full_name }} ({{ $emp->employee_no }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Leave Type <span class="text-red-500">*</span></label>
                <select name="leave_type_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">Select Type</option>
                    @foreach($leaveTypes as $lt)
                    <option value="{{ $lt->id }}" {{ old('leave_type_id')==$lt->id?'selected':'' }}>
                        {{ $lt->name }} ({{ $lt->days_per_year > 0 ? $lt->days_per_year . ' days/yr' : 'Unlimited' }}, {{ $lt->is_paid ? 'Paid' : 'Unpaid' }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-hr-input name="start_date" label="Start Date" type="date" required/>
                <x-hr-input name="end_date"   label="End Date"   type="date" required/>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
                <textarea name="reason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('reason') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Supporting Document</label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png"
                       class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#1B1444] file:text-white hover:file:bg-[#2D2467]">
                <p class="text-xs text-gray-400 mt-1">Required for Sick leave</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">Submit Request</button>
            <a href="{{ route('hr.leaves.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
