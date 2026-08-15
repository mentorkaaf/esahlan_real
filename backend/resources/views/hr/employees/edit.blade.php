@extends('hr.layouts.app')
@section('title', 'Edit ' . $employee->full_name)
@section('heading', 'Edit Employee')

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('hr.employees.update', $employee) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- Personal --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-800 mb-5">Personal Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-hr-input name="first_name"  label="First Name"  required :value="$employee->first_name"/>
                <x-hr-input name="middle_name" label="Middle Name" :value="$employee->middle_name"/>
                <x-hr-input name="last_name"   label="Last Name"   required :value="$employee->last_name"/>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Gender <span class="text-red-500">*</span></label>
                    <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach(['male','female','other'] as $g)
                        <option value="{{ $g }}" {{ $employee->gender==$g?'selected':'' }}>{{ ucfirst($g) }}</option>
                        @endforeach
                    </select>
                </div>
                <x-hr-input name="dob"         label="Date of Birth"  type="date" :value="$employee->dob?->format('Y-m-d')"/>
                <x-hr-input name="national_id"  label="National ID"   :value="$employee->national_id"/>
                <x-hr-input name="phone"        label="Phone"         required :value="$employee->phone"/>
                <x-hr-input name="email"        label="Email"         type="email" :value="$employee->email"/>
                <x-hr-input name="district"     label="District"      :value="$employee->district"/>
            </div>
            <div class="mt-4">
                <x-hr-input name="address" label="Address" :value="$employee->address"/>
            </div>
        </div>

        {{-- Employment --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-800 mb-5">Employment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Department <span class="text-red-500">*</span></label>
                    <select name="department_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ $employee->department_id==$dept->id?'selected':'' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Position <span class="text-red-500">*</span></label>
                    <select name="position_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" {{ $employee->position_id==$pos->id?'selected':'' }}>{{ $pos->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Employment Type <span class="text-red-500">*</span></label>
                    <select name="employment_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','intern'=>'Intern'] as $v=>$l)
                        <option value="{{ $v }}" {{ $employee->employment_type==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach(['active','probation','suspended','terminated','resigned'] as $s)
                        <option value="{{ $s }}" {{ $employee->status==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <x-hr-input name="hire_date"     label="Hire Date"         type="date" required :value="$employee->hire_date?->format('Y-m-d')"/>
                <x-hr-input name="probation_end" label="Probation End"     type="date" :value="$employee->probation_end?->format('Y-m-d')"/>
                @if(Auth::guard('hr')->user()->canDo('view_salary'))
                <x-hr-input name="base_salary"           label="Base Salary (USD)"    type="number" step="0.01" :value="$employee->base_salary"/>
                <x-hr-input name="bank_account"          label="Bank Account No."     :value="$employee->bank_account"/>
                <x-hr-input name="mobile_money_number"   label="Mobile Money No."     :value="$employee->mobile_money_number"/>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Save Changes</button>
            <a href="{{ route('hr.employees.show', $employee) }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
