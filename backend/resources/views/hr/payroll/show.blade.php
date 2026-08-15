@extends('hr.layouts.app')

@section('title', 'Payroll Run — ' . $payroll->period)
@section('heading', 'Payroll Run: ' . $payroll->period)

@php $run = $payroll; @endphp

@section('content')
<div class="space-y-6">

    {{-- Header / totals bar --}}
    <div class="grid grid-cols-4 gap-4">
        @foreach([
            ['label'=>'Gross Pay',    'value'=>'$'.number_format($run->total_gross,2),      'color'=>'text-gray-900'],
            ['label'=>'Deductions',   'value'=>'$'.number_format($run->total_deductions,2),  'color'=>'text-red-600'],
            ['label'=>'Net Pay',      'value'=>'$'.number_format($run->total_net,2),         'color'=>'text-green-700 text-xl font-bold'],
            ['label'=>'Employees',    'value'=>$run->employee_count,                          'color'=>'text-gray-900'],
        ] as $s)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-xs text-gray-400 mb-1">{{ $s['label'] }}</div>
            <div class="text-lg font-semibold font-tabular {{ $s['color'] }}">{{ $s['value'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Status & Actions --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $run->status_badge_class }}">
                {{ $run->status_label }}
            </span>
            <span class="text-gray-400 text-sm">Period: <strong class="text-gray-700 font-mono">{{ $run->period }}</strong></span>
        </div>

        <div class="flex items-center gap-3">
            @if($run->canSubmit() && Auth::guard('hr')->user()->canDo('generate_payroll'))
            <form method="POST" action="{{ route('hr.payroll.submit', $run) }}">
                @csrf
                <button class="bg-navy text-white text-sm px-4 py-2 rounded-lg hover:bg-navy-500 transition-colors">
                    Submit for Approval
                </button>
            </form>
            @endif

            @if($run->canApprove() && Auth::guard('hr')->user()->isManager())
            <form method="POST" action="{{ route('hr.payroll.approve', $run) }}">
                @csrf
                <button class="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 transition-colors"
                        onclick="return confirm('Approve this payroll run?')">
                    Approve
                </button>
            </form>
            <form method="POST" action="{{ route('hr.payroll.reject', $run) }}" id="rejectForm">
                @csrf
                <input type="hidden" name="note" id="rejectNote">
                <button type="button" onclick="promptReject()"
                        class="bg-red-100 text-red-600 text-sm px-4 py-2 rounded-lg hover:bg-red-200 transition-colors">
                    Reject
                </button>
            </form>
            @endif

            @if($run->canMarkPaid() && Auth::guard('hr')->user()->isManager())
            <form method="POST" action="{{ route('hr.payroll.bulk_paid', $run) }}" id="bulkPaidForm">
                @csrf
                <input type="hidden" name="payment_method" id="payMethod" value="evc_plus">
                <input type="hidden" name="payment_ref" id="payRef" value="">
                <button type="button" onclick="promptBulkPaid()"
                        class="bg-brand text-white text-sm px-4 py-2 rounded-lg hover:bg-brand-600 transition-colors">
                    Mark All Paid (EVC Plus)
                </button>
            </form>
            @endif

            @if($run->status === 'draft' && Auth::guard('hr')->user()->isManager())
            <form method="POST" action="{{ route('hr.payroll.destroy', $run) }}">
                @csrf @method('DELETE')
                <button onclick="return confirm('Delete this draft run?')"
                        class="text-red-400 hover:text-red-600 text-sm px-3 py-2 rounded-lg transition-colors">
                    Delete Draft
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Payslips table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Payslips</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-3 text-left">Employee</th>
                        <th class="px-6 py-3 text-left">Department</th>
                        <th class="px-6 py-3 text-right">Base</th>
                        <th class="px-6 py-3 text-right">Gross</th>
                        <th class="px-6 py-3 text-right">Deductions</th>
                        <th class="px-6 py-3 text-right font-bold">Net</th>
                        <th class="px-6 py-3 text-left">Payment</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($run->payslips as $slip)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $slip->employee->full_name }}</div>
                            <div class="text-xs text-gray-400">{{ $slip->employee->employee_no }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ $slip->employee->department?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-right font-tabular">${{ number_format($slip->base_salary, 2) }}</td>
                        <td class="px-6 py-4 text-right font-tabular">${{ number_format($slip->gross_pay, 2) }}</td>
                        <td class="px-6 py-4 text-right font-tabular text-red-600">${{ number_format($slip->total_deductions, 2) }}</td>
                        <td class="px-6 py-4 text-right font-tabular font-semibold text-gray-900">${{ number_format($slip->net_pay, 2) }}</td>
                        <td class="px-6 py-4">
                            @if($slip->paid_at)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                Paid • {{ $slip->payment_method }}
                            </span>
                            @else
                            <span class="text-gray-400 text-xs">Unpaid</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('hr.payroll.payslip', $slip) }}"
                               class="text-navy hover:text-brand text-xs font-medium">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Audit Timeline --}}
    @if($auditLogs->count())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Approvals Timeline</h2>
        <ol class="relative border-l border-gray-200 space-y-4 ml-3">
            @foreach($auditLogs as $log)
            <li class="pl-6">
                <span class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border border-white bg-navy"></span>
                <div class="text-xs text-gray-400">{{ $log->created_at->format('d M Y H:i') }}</div>
                <div class="text-sm font-medium text-gray-700">{{ $log->action }}</div>
                @if($log->actor_name)
                <div class="text-xs text-gray-400">by {{ $log->actor_name }}</div>
                @endif
                @if($log->extra && isset($log->extra['note']))
                <div class="text-xs text-gray-500 italic mt-0.5">{{ $log->extra['note'] }}</div>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
function promptReject() {
    const note = prompt('Rejection note (required):');
    if (!note) return;
    document.getElementById('rejectNote').value = note;
    document.getElementById('rejectForm').submit();
}
function promptBulkPaid() {
    const ref = prompt('Payment reference / transaction ID:');
    if (ref === null) return;
    document.getElementById('payRef').value = ref;
    document.getElementById('bulkPaidForm').submit();
}
</script>
@endpush
