@extends('employee.layouts.app')
@section('title', 'Payslip — ' . ($payslip->run?->period ?? $payslip->id))

@push('head')
<style>
.payslip-doc {
  max-width: 760px;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 20px;
  overflow: hidden;
}
.ps-header {
  background: linear-gradient(135deg, #1B1444, #2d1e7a);
  padding: 32px 36px;
  color: #fff;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 20px;
}
.ps-company { font-size: 20px; font-weight: 900; }
.ps-company span { color: #F7941D; }
.ps-period { font-size: 12px; color: rgba(255,255,255,.6); margin-top:4px; }
.ps-run-label { font-size: 13px; color: rgba(255,255,255,.8); margin-top:2px; font-weight: 600; }
.ps-badge-paid { background: rgba(5,150,105,.3); color: #6ee7b7; border: 1px solid rgba(5,150,105,.4); border-radius: 20px; padding: 4px 14px; font-size: 11px; font-weight: 700; }
.ps-badge-unpaid { background: rgba(217,119,6,.3); color: #fcd34d; border: 1px solid rgba(217,119,6,.4); border-radius: 20px; padding: 4px 14px; font-size: 11px; font-weight: 700; }

.ps-employee { padding: 20px 36px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; gap: 40px; flex-wrap: wrap; }
.ps-emp-field { }
.ps-emp-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 3px; }
.ps-emp-val   { font-size: 13px; font-weight: 700; color: #111827; }

.ps-attendance { display: grid; grid-template-columns: repeat(4,1fr); gap: 1px; background: #e5e7eb; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
.ps-att-cell { background: #fff; padding: 16px; text-align: center; }
.ps-att-num { font-size: 20px; font-weight: 900; color: #111827; }
.ps-att-lbl { font-size: 11px; color: #6b7280; margin-top: 3px; }

.ps-body { padding: 28px 36px; display: flex; flex-direction: column; gap: 24px; }

.ps-section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #6b7280; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.ps-section-title::after { content:''; flex:1; height:1px; background:#e5e7eb; }

.ps-items { width: 100%; border-collapse: collapse; }
.ps-items th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; padding: 6px 0; text-align: left; border-bottom: 1px solid #e5e7eb; }
.ps-items th:last-child { text-align: right; }
.ps-items td { padding: 10px 0; font-size: 13px; color: #374151; border-bottom: 1px solid #f9fafb; }
.ps-items td:last-child { text-align: right; font-weight: 600; }
.ps-items .earning  { color: #059669; }
.ps-items .deduction { color: #dc2626; }
.ps-items .subtotal td { font-weight: 800; color: #111827; font-size: 14px; border-top: 1.5px solid #e5e7eb; border-bottom: none; padding-top: 12px; }
.ps-items .subtotal .deduction { color: #dc2626; }
.ps-items .subtotal .earning   { color: #059669; }

.ps-net {
  background: #1B1444; border-radius: 14px; padding: 20px 24px;
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px;
}
.ps-net-label { font-size: 13px; color: rgba(255,255,255,.7); font-weight: 600; }
.ps-net-amount { font-size: 30px; font-weight: 900; color: #fff; }

.ps-payment { background: #f0fdf4; border: 1px solid #86efac; border-radius: 12px; padding: 16px 20px; display: flex; gap: 24px; flex-wrap: wrap; }
.ps-pay-field { }
.ps-pay-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #6b7280; margin-bottom: 3px; }
.ps-pay-val   { font-size: 13px; font-weight: 700; color: #111827; }

@media print {
  .topbar, .sidebar, .back-btn, nav { display: none !important; }
  .layout { display: block !important; }
  .main { padding: 0 !important; overflow: visible !important; }
  .payslip-doc { border: none !important; border-radius: 0 !important; }
}
</style>
@endpush

@section('content')
<div style="max-width:760px;">

  {{-- Back + Print --}}
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;" class="back-btn">
    <a href="{{ route('employee.payslips') }}" class="btn btn-outline btn-sm">
      <i class="fas fa-arrow-left"></i> Dib u noqo
    </a>
    <button onclick="window.print()" class="btn btn-outline btn-sm">
      <i class="fas fa-print"></i> Daabac
    </button>
  </div>

  <div class="payslip-doc">

    {{-- Header --}}
    <div class="ps-header">
      <div>
        <div class="ps-company">e<span>Sahlan</span></div>
        <div class="ps-period">Pay Period: {{ $payslip->run?->period ?? '—' }}</div>
        <div class="ps-run-label">{{ $payslip->run?->label ?? '' }}</div>
      </div>
      <div style="text-align:right;">
        @if($payslip->payment_status === 'paid')
          <span class="ps-badge-paid"><i class="fas fa-check-circle"></i> PAID</span>
        @else
          <span class="ps-badge-unpaid"><i class="fas fa-clock"></i> PENDING</span>
        @endif
        <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:8px;">
          Ref: PS-{{ str_pad($payslip->id, 5, '0', STR_PAD_LEFT) }}
        </div>
      </div>
    </div>

    {{-- Employee info --}}
    <div class="ps-employee">
      @foreach([
        ['Magaca',       $employee->full_name],
        ['Nambarka',     $employee->employee_no],
        ['Waaxda',       $employee->department?->name ?? '—'],
        ['Xilka',        $employee->position?->title ?? '—'],
      ] as [$lbl,$val])
      <div class="ps-emp-field">
        <div class="ps-emp-label">{{ $lbl }}</div>
        <div class="ps-emp-val">{{ $val }}</div>
      </div>
      @endforeach
    </div>

    {{-- Attendance summary --}}
    <div class="ps-attendance">
      @foreach([
        ['Working Days', $payslip->working_days, '#6b7280'],
        ['Present Days', $payslip->present_days, '#059669'],
        ['Absent Days',  $payslip->absent_days,  '#dc2626'],
        ['Leave Days',   $payslip->leave_days,   '#d97706'],
      ] as [$lbl,$val,$col])
      <div class="ps-att-cell">
        <div class="ps-att-num" style="color:{{ $col }};">{{ $val }}</div>
        <div class="ps-att-lbl">{{ $lbl }}</div>
      </div>
      @endforeach
    </div>

    <div class="ps-body">

      {{-- Earnings --}}
      @php $earnings = $payslip->items->where('type','earning'); @endphp
      @if($earnings->isNotEmpty())
      <div>
        <div class="ps-section-title"><i class="fas fa-arrow-up" style="color:#059669;"></i> Dakhliga</div>
        <table class="ps-items">
          <thead>
            <tr><th>Sharaxaad</th><th>Source</th><th>Lacagta</th></tr>
          </thead>
          <tbody>
            @foreach($earnings as $item)
            <tr>
              <td>{{ $item->label }}</td>
              <td><span class="badge badge-green" style="font-size:10px;">{{ $item->source }}</span></td>
              <td class="earning">+${{ number_format($item->amount,2) }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
              <td colspan="2">Guud ahaan Dakhliga</td>
              <td class="earning">+${{ number_format($payslip->gross_earnings,2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      @endif

      {{-- Deductions --}}
      @php $deductions = $payslip->items->where('type','deduction'); @endphp
      @if($deductions->isNotEmpty() || $payslip->total_deductions > 0)
      <div>
        <div class="ps-section-title"><i class="fas fa-arrow-down" style="color:#dc2626;"></i> Kajaridda</div>
        <table class="ps-items">
          <thead>
            <tr><th>Sharaxaad</th><th>Source</th><th>Lacagta</th></tr>
          </thead>
          <tbody>
            @foreach($deductions as $item)
            <tr>
              <td>{{ $item->label }}</td>
              <td><span class="badge badge-red" style="font-size:10px;">{{ $item->source }}</span></td>
              <td class="deduction">-${{ number_format($item->amount,2) }}</td>
            </tr>
            @endforeach
            @if($payslip->attendance_deduction > 0)
            <tr>
              <td>Attendance Deduction ({{ $payslip->absent_days }} day{{ $payslip->absent_days != 1 ? 's' : '' }})</td>
              <td><span class="badge badge-red" style="font-size:10px;">attendance</span></td>
              <td class="deduction">-${{ number_format($payslip->attendance_deduction,2) }}</td>
            </tr>
            @endif
            <tr class="subtotal">
              <td colspan="2">Guud ahaan Kajaridda</td>
              <td class="deduction">-${{ number_format($payslip->total_deductions,2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      @endif

      {{-- Commission --}}
      @if($payslip->commission_total > 0)
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b7280;">Commission</div>
          <div style="font-size:13px;color:#374151;margin-top:3px;">Module performance bonus</div>
        </div>
        <div style="font-size:18px;font-weight:900;color:#2563eb;">+${{ number_format($payslip->commission_total,2) }}</div>
      </div>
      @endif

      {{-- Net pay --}}
      <div class="ps-net">
        <div class="ps-net-label">Net Pay (Mushaharka Nadiifka)</div>
        <div class="ps-net-amount">${{ number_format($payslip->net_pay,2) }}</div>
      </div>

      {{-- Payment details --}}
      @if($payslip->payment_status === 'paid')
      <div class="ps-payment">
        <div class="ps-pay-field">
          <div class="ps-pay-label">Taariikhdii la bixiyay</div>
          <div class="ps-pay-val">{{ $payslip->paid_at?->format('d M Y H:i') ?? '—' }}</div>
        </div>
        @if($payslip->payment_method)
        <div class="ps-pay-field">
          <div class="ps-pay-label">Hab-bixinta</div>
          <div class="ps-pay-val">{{ $payslip->payment_method }}</div>
        </div>
        @endif
        @if($payslip->payment_ref)
        <div class="ps-pay-field">
          <div class="ps-pay-label">Ref</div>
          <div class="ps-pay-val" style="font-family:monospace;">{{ $payslip->payment_ref }}</div>
        </div>
        @endif
      </div>
      @endif

    </div>{{-- /body --}}

    {{-- Footer --}}
    <div style="padding:16px 36px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;text-align:center;">
      Dukumigani waa xogta rasmiga ah ee eSahlan HR System. Wararkiisa PDF ah ayaa loo keeni karaa markii la codsado.
    </div>

  </div>{{-- /doc --}}
</div>
@endsection
