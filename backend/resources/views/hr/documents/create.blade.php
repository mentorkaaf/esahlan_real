@extends('hr.layouts.app')
@section('title', 'Upload Document')
@section('heading', 'Upload Document — ' . $employee->full_name)
@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.documents.store', $employee) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Document Type <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    @foreach(['id'=>'ID','contract'=>'Contract','certificate'=>'Certificate','cv'=>'CV/Resume','photo'=>'Photo','other'=>'Other'] as $v=>$l)
                    <option value="{{ $v }}" {{ old('type')==$v?'selected':'' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <x-hr-input name="title" label="Document Title" required placeholder="e.g. National ID Card"/>
            <x-hr-input name="expires_at" label="Expiry Date" type="date"/>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">File <span class="text-red-500">*</span></label>
                <input type="file" name="file" required class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#1B1444] file:text-white hover:file:bg-[#2D2467]">
                <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, JPG, PNG — max 10MB</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"></textarea>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">Upload</button>
            <a href="{{ route('hr.employees.show', $employee) }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
