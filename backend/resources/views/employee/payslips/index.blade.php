@extends('employee.layouts.app')
@section('title', 'Payslips-kayga')

@push('head')
<style>
.payslip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 16px;
}
.payslip-card {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  display: block;
  transition: box-shadow .2s, transform .2s, border-color .2s;
  box-shadow: var(--shadow-sm);
}
.payslip-card:hover {
  box-shadow: var(--shadow);
  transform: translateY(-2px);
  border-color: #a5b4fc;
}
.payslip-stripe { height: 4px; }
.payslip-body { padding: 18px 20px 14px; }
.payslip-period {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--muted);
  margin-bottom: 8px;
}
.payslip-net {
  font-size: 28px;
  font-weight: 900;
  color: var(--text);
  line-height: 1;
  margin-bottom: 12px;
}
.payslip-net span { font-size: 14px; color: var(--muted); font-weight: 500; }
.payslip-meta { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-top: 6px; }
.payslip-meta strong { color: var(--text2); }
.payslip-footer {
  padding: 10px 20px;
  background: #f8f9fc;
  border-top: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.hero-payslip {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy3) 60%, #3b2e9a 100%);
  border-radius: var(--radius-lg);
  padding: 28px 32px;
  color: #fff;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
  box-shadow: 0 8px 32px rgba(15,11,46,.3);
  position: relative;
  overflow: hidden;
}
.hero-payslip::before {
  content: '';
  position: absolute;
  right: -40px; top: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(247,148,29,.08);
}
.hero-payslip::after {
  content: '';
  position: absolute;
  right: 60px; bottom: -60px;
  width: 150px; height: 150px;
  border-radius: 50%;
  background: rgba(255,255,255,.04);
}
.hero-label {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .14em;
  color: rgba(255,255,255,.45);
  margin-bottom: 6px;
}
.hero-val { font-size: 38px; font-weight: 900; color: #fff; line-height: 1; }
.hero-sub { font-size: 13px; color: rgba(255,255,255,.5); margin-top: 6px; }
.hero-badge {
  background: rgba(247,148,29,.18);
  color: var(--brand);
  border: 1px solid rgba(247,148,29,.3);
  border-radius: 20px;
  padding: 5px 14px;
  font-size: 11px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.stat-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px,1fr));
  gap: 12px;
  margin-bottom: 24px;
}
.stat-mini {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  padding: 16px 18px;
  box-shadow: var(--shadow-sm);
}
.stat-mini-val { font-size: 22px; font-weight: 900; color: var(--text); }
.stat-mini-lbl { font-size: 11px; color: var(--muted); margin-top: 4px; }
</style>
@endpush

@section('content')

<div class="page-header">
  <div>
    <h1 class="page-title">
      <div class="page-title-icon"><i class="fas fa-file-invoice-dollar"></i></div>
      Payslips-kayga
    </h1>
    <div class="page-sub">Mushaharka iyo lacag-bixinta tariikhdaada</div>
  </div>
</div>

{{-- Hero: latest paid payslip --}}
@if($latestPaid)
<div class="hero-payslip">
  <div style="position:relative;z-index:1;">
    <div class="hero-label">Mushaharka u dambeeyay ee la bixiyay</div>
    <div class="hero-val">${{ number_format($latestPaid->net_pay, 2) }}</div>
    <div class="hero-sub">{{ $latestPaid->run?->label ?? $latestPaid->run?->period }}</div>
  </div>
  <div style="text-align:right;position:relative;z-index:1;">
    <div class="hero-badge"><i class="fas fa-check-circle"></i> Bixinnay</div>
    @if($latestPaid->paid_at)
    <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:10px;">
      {{ $latestPaid->paid_at->format('d M Y') }}
      @if($latestPaid->payment_method) · {{ $latestPaid->payment_method }} @endif
    </div>
    @endif
    <a href="{{ route('employee.payslips.show', $latestPaid) }}"
       style="display:inline-flex;align-items:center;gap:7px;margin-top:12px;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.18);padding:8px 18px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s;"
       onmouseover="this.style.background='rgba(255,255,255,.18)'"
       onmouseout="this.style.background='rgba(255,255,255,.1)'">
      <i class="fas fa-eye"></i> Arag
    </a>
  </div>
</div>
@endif

{{-- Stats --}}
@php
  $totalPaid   = $payslips->where('payment_status','paid')->sum('net_pay');
  $countPaid   = $payslips->where('payment_status','paid')->count();
  $countUnpaid = $payslips->where('payment_status','unpaid')->count();
@endphp
<div class="stat-row">
  <div class="stat-mini">
    <div class="stat-mini-val">{{ $payslips->total() }}</div>
    <div class="stat-mini-lbl">Payslips guud</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-val" style="color:#059669;">${{ number_format($totalPaid,0) }}</div>
    <div class="stat-mini-lbl">Wadarta la bixiyay</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-val" style="color:#059669;">{{ $countPaid }}</div>
    <div class="stat-mini-lbl">Payslip la bixiyay</div>
  </div>
  @if($countUnpaid)
  <div class="stat-mini">
    <div class="stat-mini-val" style="color:#d97706;">{{ $countUnpaid }}</div>
    <div class="stat-mini-lbl">La sugayo</div>
  </div>
  @endif
</div>

{{-- Section header --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
  <h2 style="font-size:14px;font-weight:800;color:var(--navy);">Dhammaan Payslips-ka</h2>
  <span style="font-size:12px;color:var(--muted);">{{ $payslips->total() }} payslip</span>
</div>

@if($payslips->isEmpty())
  <div class="card">
    <div class="empty-state">
      <i class="fas fa-file-invoice empty-state-icon"></i>
      <div class="empty-state-title">Payslip wali ma jiro</div>
      <div class="empty-state-sub">HR-ga la xiriir si mushaharka loo xisaabiyo.</div>
    </div>
  </div>
@else
<div class="payslip-grid">
  @foreach($payslips as $p)
  @php
    $paid  = $p->payment_status === 'paid';
    $color = $paid ? '#059669' : '#d97706';
  @endphp
  <a href="{{ route('employee.payslips.show', $p) }}" class="payslip-card">
    <div class="payslip-stripe" style="background:{{ $color }};"></div>
    <div class="payslip-body">
      <div class="payslip-period">{{ $p->run?->period ?? '—' }}</div>
      <div class="payslip-net">${{ number_format($p->net_pay,2) }} <span>Net Pay</span></div>
      <div class="payslip-meta">
        <span>Gross: <strong>${{ number_format($p->gross_earnings,2) }}</strong></span>
        <span style="color:#dc2626;">Cuts: <strong>-${{ number_format($p->total_deductions,2) }}</strong></span>
      </div>
      <div class="payslip-meta">
        <span>Joog: <strong>{{ $p->present_days }}/{{ $p->working_days }}</strong></span>
        @if($p->commission_total > 0)
        <span>Comm: <strong style="color:#7c3aed;">${{ number_format($p->commission_total,2) }}</strong></span>
        @endif
      </div>
    </div>
    <div class="payslip-footer">
      @if($paid)
        <span class="badge badge-green"><i class="fas fa-check-circle"></i> Bixinnay</span>
        <span style="font-size:11px;color:var(--muted);">{{ $p->paid_at?->format('d M Y') ?? '' }}</span>
      @else
        <span class="badge badge-yellow"><i class="fas fa-clock"></i> La sugayo</span>
        <span style="font-size:11px;color:var(--muted);">{{ $p->run?->label ?? '' }}</span>
      @endif
    </div>
  </a>
  @endforeach
</div>

@if($payslips->hasPages())
<div style="margin-top:24px;">{{ $payslips->links() }}</div>
@endif
@endif

@endsection
