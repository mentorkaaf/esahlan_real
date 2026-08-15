@extends('hr.layouts.app')

@section('title', 'Payslip — ' . $payslip->employee->full_name)
@section('heading', 'Payslip Detail')

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Back --}}
    <a href="{{ route('hr.payroll.show', $payslip->run) }}"
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← Back to {{ $payslip->run->period }} run
    </a>

    {{-- Employee header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-navy flex items-center justify-center text-white font-bold text-lg">
                    {{ $payslip->employee->initials }}
                </div>
                <div>
                    <div class="text-lg font-semibold text-gray-900">{{ $payslip->employee->full_name }}</div>
                    <div class="text-sm text-gray-400">{{ $payslip->employee->employee_no }} · {{ $payslip->employee->position?->title ?? '—' }}</div>
                    <div class="text-xs text-gray-400">{{ $payslip->employee->department?->name ?? '—' }}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-gray-900 font-tabular">${{ number_format($payslip->net_pay, 2) }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Net Pay — {{ $payslip->run->period }}</div>
                @if($payslip->paid_at)
                <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                    Paid via {{ $payslip->payment_method }}
                </span>
                @else
                <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                    Unpaid
                </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Itemized breakdown --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        {{-- Earnings --}}
        <div class="px-6 py-4 bg-green-50 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-green-800">Earnings</h3>
        </div>
        <table class="w-full text-sm">
            @foreach($payslip->items->where('type', 'earning')->sortBy('sort_order') as $item)
            <tr class="border-b border-gray-50 hover:bg-gray-50">
                <td class="px-6 py-3 text-gray-700">{{ $item->label }}</td>
                <td class="px-6 py-3 text-xs text-gray-400">{{ ucfirst($item->source) }}</td>
                <td class="px-6 py-3 text-right font-tabular font-medium text-gray-900">${{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="bg-green-50">
                <td class="px-6 py-3 font-semibold text-green-800" colspan="2">Gross Pay</td>
                <td class="px-6 py-3 text-right font-tabular font-bold text-green-800">${{ number_format($payslip->gross_pay, 2) }}</td>
            </tr>
        </table>

        {{-- Deductions --}}
        @if($payslip->items->where('type', 'deduction')->count())
        <div class="px-6 py-4 bg-red-50 border-t border-b border-gray-100">
            <h3 class="text-sm font-semibold text-red-800">Deductions</h3>
        </div>
        <table class="w-full text-sm">
            @foreach($payslip->items->where('type', 'deduction')->sortBy('sort_order') as $item)
            <tr class="border-b border-gray-50 hover:bg-gray-50">
                <td class="px-6 py-3 text-gray-700">{{ $item->label }}</td>
                <td class="px-6 py-3 text-xs text-gray-400">{{ ucfirst($item->source) }}</td>
                <td class="px-6 py-3 text-right font-tabular font-medium text-red-600">−${{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="bg-red-50">
                <td class="px-6 py-3 font-semibold text-red-800" colspan="2">Total Deductions</td>
                <td class="px-6 py-3 text-right font-tabular font-bold text-red-800">−${{ number_format($payslip->total_deductions, 2) }}</td>
            </tr>
        </table>
        @endif

        {{-- Net --}}
        <div class="px-6 py-5 bg-navy/5 flex items-center justify-between">
            <span class="text-base font-bold text-gray-900">NET PAY</span>
            <span class="text-2xl font-bold text-navy font-tabular">${{ number_format($payslip->net_pay, 2) }}</span>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('hr.payroll.payslip.pdf', $payslip) }}" target="_blank"
           class="inline-flex items-center gap-2 bg-navy text-white text-sm px-4 py-2 rounded-lg hover:bg-navy-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Download PDF
        </a>

        @if(!$payslip->paid_at && $payslip->run->status === 'approved' && Auth::guard('hr')->user()->isManager())
        <form method="POST" action="{{ route('hr.payroll.payslip.paid', $payslip) }}" id="markPaidForm">
            @csrf
            <input type="hidden" name="payment_method" id="singlePayMethod" value="evc_plus">
            <input type="hidden" name="payment_ref" id="singlePayRef" value="">
            <button type="button" onclick="promptSinglePaid()"
                    class="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                Mark Paid
            </button>
        </form>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
function promptSinglePaid() {
    const ref = prompt('Payment reference:');
    if (ref === null) return;
    document.getElementById('singlePayRef').value = ref;
    document.getElementById('markPaidForm').submit();
}
</script>
@endpush
