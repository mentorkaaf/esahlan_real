@extends('hr.layouts.app')
@section('title', 'New Salary Component')
@section('heading', 'New Salary Component')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <form method="POST" action="{{ route('hr.components.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('name') border-red-400 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Code *</label>
                    <input type="text" name="code" value="{{ old('code') }}" required
                           placeholder="e.g. TRANSPORT"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('code') border-red-400 @enderror">
                    @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                    <select name="type" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        <option value="earning"   {{ old('type') === 'earning' ? 'selected' : '' }}>Earning</option>
                        <option value="deduction" {{ old('type') === 'deduction' ? 'selected' : '' }}>Deduction</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Calculation *</label>
                    <select name="calculation" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        <option value="fixed"      {{ old('calculation') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                        <option value="percentage" {{ old('calculation') === 'percentage' ? 'selected' : '' }}>% of Base Salary</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Value *</label>
                <input type="number" name="value" value="{{ old('value') }}" required min="0" step="0.01"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('value') border-red-400 @enderror">
                <p class="mt-1 text-xs text-gray-400">Dollar amount for fixed, or percentage number (e.g. 5 for 5%) for percentage.</p>
                @error('value')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_taxable" value="1" {{ old('is_taxable') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-brand focus:ring-brand/30">
                    Taxable
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" checked
                           class="rounded border-gray-300 text-brand focus:ring-brand/30">
                    Active
                </label>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">{{ old('description') }}</textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-brand hover:bg-brand-600 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Save Component
                </button>
                <a href="{{ route('hr.components.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
