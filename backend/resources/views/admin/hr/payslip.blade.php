@extends('admin.layouts.app')
@section('title', 'Payslip — ' . $payslip->employee->full_name)

@section('content')
<div class="container-fluid">

    <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">Payslip Detail</h4>
        <span class="badge bg-info">Admin View</span>
    </div>

    <div class="row g-4">
        {{-- Employee card --}}
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                             style="width:52px;height:52px;background:#1B1444;font-size:1.1rem;flex-shrink:0;">
                            {{ $payslip->employee->initials }}
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $payslip->employee->full_name }}</div>
                            <div class="text-muted" style="font-size:.82rem;">{{ $payslip->employee->employee_no }}</div>
                        </div>
                    </div>
                    <table class="table table-sm mb-0">
                        <tr><th class="text-muted fw-normal" style="font-size:.8rem;">Department</th><td>{{ $payslip->employee->department?->name ?? '—' }}</td></tr>
                        <tr><th class="text-muted fw-normal" style="font-size:.8rem;">Position</th><td>{{ $payslip->employee->position?->title ?? '—' }}</td></tr>
                        <tr><th class="text-muted fw-normal" style="font-size:.8rem;">Period</th><td class="font-monospace fw-semibold">{{ $payslip->run->period }}</td></tr>
                        <tr><th class="text-muted fw-normal" style="font-size:.8rem;">Run Status</th>
                            <td><span class="badge bg-{{ match($payslip->run->status) { 'approved','paid'=>'success', 'pending_approval'=>'warning', default=>'secondary' } }}">{{ $payslip->run->status_label }}</span></td></tr>
                        <tr><th class="text-muted fw-normal" style="font-size:.8rem;">Payment</th>
                            <td>@if($payslip->paid_at)<span class="text-success fw-semibold">Paid</span> <span class="text-muted">({{ $payslip->payment_method }})</span>@else<span class="text-muted">Unpaid</span>@endif</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Items breakdown --}}
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success bg-opacity-10">
                    <h6 class="mb-0 text-success fw-semibold">Earnings</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                            @foreach($payslip->items->where('type','earning')->sortBy('sort_order') as $item)
                            <tr>
                                <td class="ps-4">{{ $item->label }}</td>
                                <td class="text-muted pe-1" style="font-size:.8rem;">{{ ucfirst($item->source) }}</td>
                                <td class="text-end pe-4 font-monospace fw-semibold">${{ number_format($item->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-success">
                            <tr><td class="ps-4 fw-bold" colspan="2">Gross Pay</td>
                                <td class="text-end pe-4 fw-bold font-monospace">${{ number_format($payslip->gross_pay, 2) }}</td></tr>
                        </tfoot>
                    </table>
                </div>

                @if($payslip->items->where('type','deduction')->count())
                <div class="card-header bg-danger bg-opacity-10 border-top">
                    <h6 class="mb-0 text-danger fw-semibold">Deductions</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                            @foreach($payslip->items->where('type','deduction')->sortBy('sort_order') as $item)
                            <tr class="text-danger">
                                <td class="ps-4">{{ $item->label }}</td>
                                <td class="text-muted pe-1" style="font-size:.8rem;">{{ ucfirst($item->source) }}</td>
                                <td class="text-end pe-4 font-monospace">−${{ number_format($item->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-danger">
                            <tr><td class="ps-4 fw-bold" colspan="2">Total Deductions</td>
                                <td class="text-end pe-4 fw-bold font-monospace">−${{ number_format($payslip->total_deductions, 2) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
                @endif

                <div class="card-footer bg-dark text-white d-flex align-items-center justify-content-between py-3 px-4">
                    <span class="fw-bold text-uppercase tracking-wide" style="letter-spacing:.08em;">Net Pay</span>
                    <span class="fw-bold font-monospace" style="font-size:1.4rem;">${{ number_format($payslip->net_pay, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
