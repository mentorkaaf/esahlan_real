@extends('hr.layouts.app')
@section('title', 'Record Commission')
@section('heading', 'Record Commission')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('hr.commissions.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Employee *</label>
                <select name="employee_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('employee_id') border-red-400 @enderror">
                    <option value="">— Select employee —</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                        {{ $emp->full_name }} ({{ $emp->employee_no }})
                    </option>
                    @endforeach
                </select>
                @error('employee_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Period *</label>
                    <input type="month" name="period" value="{{ old('period', now()->format('Y-m')) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('period') border-red-400 @enderror">
                    @error('period')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                    <select name="type" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        <option value="customer_signups" {{ old('type') === 'customer_signups' ? 'selected' : '' }}>Customer Sign-ups</option>
                        <option value="vendor_signups"   {{ old('type') === 'vendor_signups' ? 'selected' : '' }}>Vendor Sign-ups</option>
                        <option value="other"            {{ old('type') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                <input type="text" name="description" value="{{ old('description') }}"
                       placeholder="e.g. July referrals"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Target</label>
                    <input type="number" name="target" value="{{ old('target', 0) }}" min="0" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Achieved *</label>
                    <input type="number" name="achieved" value="{{ old('achieved') }}" min="0" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('achieved') border-red-400 @enderror"
                           oninput="computeAmount()">
                    @error('achieved')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Rate ($/unit) *</label>
                    <input type="number" name="rate" value="{{ old('rate') }}" min="0" step="0.01" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('rate') border-red-400 @enderror"
                           oninput="computeAmount()">
                    @error('rate')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Live amount preview --}}
            <div class="bg-brand/5 border border-brand/20 rounded-lg px-4 py-3 flex items-center justify-between">
                <span class="text-sm text-gray-600">Commission Amount</span>
                <span id="amountPreview" class="text-xl font-bold text-brand font-tabular">$0.00</span>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
                <textarea name="note" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">{{ old('note') }}</textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-brand hover:bg-brand-600 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Record Commission
                </button>
                <a href="{{ route('hr.commissions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function computeAmount() {
    const achieved = parseFloat(document.querySelector('[name=achieved]').value) || 0;
    const rate     = parseFloat(document.querySelector('[name=rate]').value) || 0;
    document.getElementById('amountPreview').textContent = '$' + (achieved * rate).toFixed(2);
}
</script>
@endpush
