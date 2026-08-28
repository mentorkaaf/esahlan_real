<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:11px; color:#1E293B; background:#fff; }

  /* ── Header ── */
  .header { background:#07003B; padding:22px 28px; display:flex; justify-content:space-between; align-items:center; }
  .logo-text { font-size:22px; font-weight:700; color:#fff; letter-spacing:-0.5px; }
  .logo-sub  { font-size:10px; color:#94A3B8; margin-top:2px; }
  .header-right { text-align:right; color:#94A3B8; font-size:9px; line-height:1.6; }

  /* ── Customer Info ── */
  .customer-block { padding:18px 28px; border-bottom:2px solid #F1F5F9; display:flex; justify-content:space-between; }
  .cust-name { font-size:17px; font-weight:700; color:#07003B; }
  .cust-meta { font-size:10px; color:#64748B; margin-top:4px; line-height:1.7; }
  .period-badge { background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:6px 14px; font-size:10px; color:#1D4ED8; font-weight:700; text-align:center; }
  .period-label { font-size:9px; color:#94A3B8; margin-bottom:2px; }

  /* ── KPI Row ── */
  .kpi-row { display:flex; gap:0; border-bottom:1px solid #F1F5F9; }
  .kpi { flex:1; padding:14px 20px; border-right:1px solid #F1F5F9; }
  .kpi:last-child { border-right:none; }
  .kpi-label { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94A3B8; margin-bottom:5px; }
  .kpi-val   { font-size:18px; font-weight:800; letter-spacing:-0.5px; }
  .kpi-val.green  { color:#16A34A; }
  .kpi-val.red    { color:#DC2626; }
  .kpi-val.navy   { color:#07003B; }
  .kpi-val.orange { color:#D97706; }

  /* ── Section title ── */
  .section-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#94A3B8; padding:14px 28px 8px; }

  /* ── Table ── */
  table { width:100%; border-collapse:collapse; }
  th { background:#F8FAFC; padding:8px 12px; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#94A3B8; text-align:left; border-bottom:1px solid #E2E8F0; }
  td { padding:9px 12px; font-size:10px; border-bottom:1px solid #F8FAFC; vertical-align:middle; }
  tr.credit-row { background:#F0FDF4; }
  tr.debit-row  { background:#FFF7F7; }
  .pill { display:inline-block; padding:2px 8px; border-radius:20px; font-size:9px; font-weight:700; }
  .pill-credit { background:#DCFCE7; color:#15803D; }
  .pill-debit  { background:#FEE2E2; color:#B91C1C; }
  .pill-approved  { background:#DCFCE7; color:#15803D; }
  .pill-rejected  { background:#FEE2E2; color:#B91C1C; }
  .pill-pending   { background:#FEF3C7; color:#B45309; }
  .pill-processed { background:#EFF6FF; color:#1D4ED8; }
  .amount-credit { color:#16A34A; font-weight:700; }
  .amount-debit  { color:#DC2626; font-weight:700; }

  /* ── Footer ── */
  .footer { margin-top:30px; padding:16px 28px; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; }
  .footer-left  { font-size:9px; color:#94A3B8; line-height:1.6; }
  .footer-right { font-size:9px; color:#94A3B8; text-align:right; }
  .confidential { background:#FEF3C7; border:1px solid #FDE68A; border-radius:5px; padding:4px 10px; font-size:9px; font-weight:700; color:#92400E; }

  .page-break { page-break-before: always; }
  .no-data { padding:20px 28px; font-size:11px; color:#94A3B8; font-style:italic; }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<div class="header">
  <div>
    <div class="logo-text">eSahlan</div>
    <div class="logo-sub">Financial Statement — Official Record</div>
  </div>
  <div class="header-right">
    Generated: {{ now()->format('d M Y, H:i') }}<br>
    Document ID: STM-{{ $user->id }}-{{ now()->format('YmdHis') }}<br>
    <span class="confidential">CONFIDENTIAL</span>
  </div>
</div>

{{-- ══ CUSTOMER INFO ══ --}}
<div class="customer-block">
  <div>
    <div class="cust-name">{{ $user->name }}</div>
    <div class="cust-meta">
      Phone: {{ $user->phone }}<br>
      Email: {{ $user->email ?? 'N/A' }}<br>
      User ID: #{{ $user->id }} &nbsp;|&nbsp; Wallet ID: {{ $wallet?->id ?? 'N/A' }}<br>
      Account Status: {{ $wallet?->is_frozen ? '⛔ Frozen' : '✅ Active' }}
    </div>
  </div>
  <div>
    <div class="period-label">Statement Period</div>
    <div class="period-badge">{{ $period }}</div>
    <div style="margin-top:8px; font-size:9px; color:#94A3B8; text-align:center;">
      {{ $transactions->count() }} transactions
    </div>
  </div>
</div>

{{-- ══ KPI ROW ══ --}}
<div class="kpi-row">
  <div class="kpi">
    <div class="kpi-label">Current Balance</div>
    <div class="kpi-val navy">${{ number_format($wallet?->balance ?? 0, 2) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total Credits</div>
    <div class="kpi-val green">+${{ number_format($totalCredit, 2) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total Debits</div>
    <div class="kpi-val red">-${{ number_format($totalDebit, 2) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Net Flow</div>
    <div class="kpi-val {{ ($totalCredit - $totalDebit) >= 0 ? 'green' : 'red' }}">
      ${{ number_format($totalCredit - $totalDebit, 2) }}
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total Withdrawn</div>
    <div class="kpi-val orange">-${{ number_format($totalWithdrawn, 2) }}</div>
  </div>
</div>

{{-- ══ TRANSACTIONS ══ --}}
<div class="section-title">Transaction History</div>

@if($transactions->isEmpty())
  <div class="no-data">No transactions found for this period.</div>
@else
<table>
  <thead>
    <tr>
      <th style="width:30px">#</th>
      <th>Date & Time</th>
      <th>Type</th>
      <th>Amount</th>
      <th>Balance After</th>
      <th>Channel</th>
      <th>Note / Reference</th>
    </tr>
  </thead>
  <tbody>
    @foreach($transactions as $i => $tx)
    <tr class="{{ $tx->type === 'credit' ? 'credit-row' : 'debit-row' }}">
      <td style="color:#94A3B8">{{ $i + 1 }}</td>
      <td>{{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}<br>
          <span style="color:#94A3B8;font-size:9px">{{ \Carbon\Carbon::parse($tx->created_at)->format('H:i') }}</span></td>
      <td><span class="pill pill-{{ $tx->type }}">{{ strtoupper($tx->type) }}</span></td>
      <td class="amount-{{ $tx->type }}">
        {{ $tx->type === 'credit' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
      </td>
      <td style="font-weight:600">${{ number_format($tx->balance_after, 2) }}</td>
      <td style="color:#64748B">{{ $tx->channel ?? 'Wallet' }}</td>
      <td style="color:#374151;max-width:160px">{{ Str::limit($tx->description ?? '', 50) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

{{-- ══ WITHDRAWALS ══ --}}
@if($withdrawals->isNotEmpty())
<div class="page-break"></div>
<div class="header" style="padding:14px 28px;">
  <div class="logo-text" style="font-size:16px">eSahlan — Withdrawal History</div>
  <div class="header-right">{{ $user->name }} | {{ $period }}</div>
</div>

<div class="section-title">Withdrawal Requests</div>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Date</th>
      <th>Amount</th>
      <th>Method</th>
      <th>Account</th>
      <th>Status</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    @foreach($withdrawals as $i => $w)
    <tr>
      <td style="color:#94A3B8">{{ $i + 1 }}</td>
      <td>{{ \Carbon\Carbon::parse($w->created_at)->format('d M Y') }}</td>
      <td style="font-weight:700; color:#DC2626">-${{ number_format($w->amount, 2) }}</td>
      <td>{{ strtoupper($w->method ?? '') }}</td>
      <td style="font-family:monospace">{{ $w->account_number ?? '—' }}</td>
      <td><span class="pill pill-{{ $w->status ?? 'pending' }}">{{ strtoupper($w->status ?? 'PENDING') }}</span></td>
      <td style="color:#64748B">{{ $w->notes ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

{{-- ══ FOOTER ══ --}}
<div class="footer">
  <div class="footer-left">
    <strong>eSahlan Financial Services</strong><br>
    This statement is an official record generated from the eSahlan platform.<br>
    For disputes or inquiries, contact support@esahlan.com
  </div>
  <div class="footer-right">
    Generated by eSahlan Admin Panel<br>
    {{ now()->format('d M Y H:i') }} UTC+3<br>
    <strong>Page 1</strong>
  </div>
</div>

</body>
</html>
