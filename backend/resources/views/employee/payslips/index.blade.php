@extends('employee.layouts.app')
@section('title', 'Payslips-kayga')

@push('head')
<style>
.payslip-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.payslip-card {
  background:#fff; border:1.5px solid #e5e7eb; border-radius:16px;
  overflow:hidden; text-decoration:none; color:inherit;
  transition:box-shadow .2s,border-color .2s; display:block;
}
.payslip-card:hover { box-shadow:0 6px 24px rgba(27,20,68,.1); border-color:#a5b4fc; }
.payslip-stripe { height:5px; }
.payslip-body { padding:18px 20px; }
.payslip-period { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#6b7280; margin-bottom:6px; }
.payslip-net { font-size:26px; font-weight:900; color:#111827; line-height:1; }
.payslip-net span { font-size:14px; color:#6b7280; font-weight:500; }
.payslip-row { display:flex; justify-content:space-between; font-size:12px; color:#6b7280; margin-top:12px; }
.payslip-row strong { color:#374151; }
.payslip-footer { padding:10px 20px; background:#f9fafb; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; }

.latest-card {
  background:linear-gradient(135deg,#1B1444 0%,#2d1e7a 100%);
  border-radius:20px; padding:28px; color:#fff; margin-bottom:24px;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px;
}
.latest-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.5); margin-bottom:4px; }
.latest-val   { font-size:32px; font-weight:900; color:#fff; }
.latest-sub   { font-size:13px; color:rgba(255,255,255,.6); margin-top:4px; }
.latest-badge { background:rgba(247,148,29,.2); color:#F7941D; border:1px solid rgba(247,148,29,.4); border-radius:20px; padding:4px 12px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:5px; }

.stat-bar { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.stat-mini { background:#fff; border:1.5px solid #e5e7eb; border-radius:12px; padding:14px 18px; flex:1; min-width:140px; }
.stat-mini-val { font-size:20px; font-weight:900; color:#111827; }
.stat-mini-lbl { font-size:11px; color:#6b7280; margin-top:3px; }
</style>
@endpush

@section('content')

<h1 style="font-size:20px;font-weight:900;color:#111827;margin-bottom:20px;">
  <i class="fas fa-file-invoice-dollar" style="color:var(--brand);margin-right:8px;"></i>
  Payslips-kayga
</h1>

{{-- Latest paid payslip highlight --}}
@if($latestPaid)
<div class="latest-card">
  <div>
    <div class="latest-label">Mushaharka u dambeeyay ee la bixiyay</div>
    <div class="latest-val">${{ number_format($latestPaid->net_pay, 2) }}</div>
    <div class="latest-sub">{{ $latestPaid->run?->label ?? $latestPaid->run?->period }}</div>
  </div>
  <div style="text-align:right;">
    <div class="latest-badge"><i class="fas fa-check-circle"></i> Bixinnay</div>
    @if($latestPaid->paid_at)
    <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:8px;">
      {{ $latestPaid->paid_at->format('d M Y') }}
      @if($latestPaid->payment_method) · {{ $latestPaid->payment_method }} @endif
    </div>
    @endif
    <a href="{{ route('employee.payslips.show', $latestPaid) }}"
       style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.2);padding:7px 16px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;">
      <i class="fas fa-eye"></i> Arag
    </a>
  </div>
</div>
@endif

{{-- Summary stats --}}
@php
  $totalPaid   = $payslips->where('payment_status','paid')->sum('net_pay');
  $countPaid   = $payslips->where('payment_status','paid')->count();
  $countUnpaid = $payslips->where('payment_status','unpaid')->count();
@endphp
<div class="stat-bar">
  <div class="stat-mini">
    <div class="stat-mini-val">{{ $payslips->total() }}</div>
    <div class="stat-mini-lbl">Payslips guud</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-val" style="color:#059669;">{{ $countPaid }}</div>
    <div class="stat-mini-lbl">La bixiyay</div>
  </div>
  @if($countUnpaid)
  <div class="stat-mini">
    <div class="stat-mini-val" style="color:#d97706;">{{ $countUnpaid }}</div>
    <div class="stat-mini-lbl">La sugayo</div>
  </div>
  @endif
</div>

{{-- Grid --}}
@if($payslips->isEmpty())
  <div style="text-align:center;padding:60px;color:#9ca3af;">
    <i class="fas fa-file-invoice" style="font-size:40px;opacity:.3;display:block;margin-bottom:12px;"></i>
    <p>Wali payslip kuma jiro nidaamka.</p>
  </div>
@else
<div class="payslip-grid">
  @foreach($payslips as $p)
  @php
    $paid = $p->payment_status === 'paid';
    $color = $paid ? '#059669' : '#d97706';
  @endphp
  <a href="{{ route('employee.payslips.show', $p) }}" class="payslip-card">
    <div class="payslip-stripe" style="background:{{ $color }};"></div>
    <div class="payslip-body">
      <div class="payslip-period">{{ $p->run?->period ?? '—' }}</div>
      <div class="payslip-net">${{ number_format($p->net_pay,2) }} <span>Net Pay</span></div>
      <div class="payslip-row">
        <span>Gross: <strong>${{ number_format($p->gross_earnings,2) }}</strong></span>
        <span>Cuts: <strong style="color:#dc2626;">-${{ number_format($p->total_deductions,2) }}</strong></span>
      </div>
      <div class="payslip-row">
        <span>Days: <strong>{{ $p->present_days }}/{{ $p->working_days }}</strong></span>
        @if($p->commission_total > 0)
        <span>Commission: <strong style="color:#2563eb;">${{ number_format($p->commission_total,2) }}</strong></span>
        @endif
      </div>
    </div>
    <div class="payslip-footer">
      @if($paid)
        <span class="badge badge-green"><i class="fas fa-check-circle"></i> Bixinnay</span>
        <span style="font-size:11px;color:#6b7280;">{{ $p->paid_at?->format('d M Y') ?? '' }}</span>
      @else
        <span class="badge badge-yellow"><i class="fas fa-clock"></i> La sugayo</span>
        <span style="font-size:11px;color:#6b7280;">{{ $p->run?->label ?? '' }}</span>
      @endif
    </div>
  </a>
  @endforeach
</div>

{{-- Pagination --}}
@if($payslips->hasPages())
<div style="margin-top:24px;">{{ $payslips->links() }}</div>
@endif
@endif

@endsection
