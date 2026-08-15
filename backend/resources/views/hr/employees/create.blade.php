@extends('hr.layouts.app')
@section('title', 'Add Employee')
@section('heading', 'Add Employee')

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('hr.employees.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Personal --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-800 mb-5">Personal Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-hr-input name="first_name" label="First Name" required/>
                <x-hr-input name="middle_name" label="Middle Name"/>
                <x-hr-input name="last_name" label="Last Name" required/>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Gender <span class="text-red-500">*</span></label>
                    <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        <option value="">Select</option>
                        <option value="male" {{ old('gender')=='male'?'selected':'' }}>Male</option>
                        <option value="female" {{ old('gender')=='female'?'selected':'' }}>Female</option>
                        <option value="other" {{ old('gender')=='other'?'selected':'' }}>Other</option>
                    </select>
                </div>
                <x-hr-input name="dob" label="Date of Birth" type="date"/>
                <x-hr-input name="national_id" label="National ID"/>
                <x-hr-input name="phone" label="Phone" required/>
                <x-hr-input name="email" label="Email" type="email"/>
                <x-hr-input name="district" label="District"/>
            </div>
            <div class="mt-4">
                <x-hr-input name="address" label="Address"/>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Photo</label>
                <input type="file" name="photo" accept="image/*" class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#1B1444] file:text-white hover:file:bg-[#2D2467]">
            </div>
        </div>

        {{-- Employment --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-800 mb-5">Employment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Department <span class="text-red-500">*</span></label>
                    <select name="department_id" required id="dept_select" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        <option value="">Select Department</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id')==$dept->id?'selected':'' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Position <span class="text-red-500">*</span></label>
                    <select name="position_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        <option value="">Select Position</option>
                        @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" {{ old('position_id')==$pos->id?'selected':'' }}>{{ $pos->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Employment Type <span class="text-red-500">*</span></label>
                    <select name="employment_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','intern'=>'Intern'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('employment_type')==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach(['active'=>'Active','probation'=>'Probation'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('status')==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <x-hr-input name="hire_date" label="Hire Date" type="date" required/>
                <x-hr-input name="probation_end" label="Probation End Date" type="date"/>
                <x-hr-input name="base_salary" label="Base Salary (USD)" type="number" step="0.01"/>
                <x-hr-input name="bank_account" label="Bank Account No."/>
                <x-hr-input name="mobile_money_number" label="Mobile Money No."/>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                Create Employee
            </button>
            <a href="{{ route('hr.employees.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
