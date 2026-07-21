@extends('admin.layouts.app')
@section('title', 'ePay Financial Dashboard')

@push('styles')
<style>
/* ── Layout ── */
.ep { background:#F4F6FB; min-height:100vh; padding:24px 28px; }

/* ── KPI Cards ── */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }
.kpi { background:#fff; border-radius:16px; padding:20px 22px; border:1px solid #EEF0F6; position:relative; overflow:hidden; }
.kpi::after { content:''; position:absolute; right:-16px; top:-16px; width:80px; height:80px; border-radius:50%; opacity:.06; }
.kpi-green::after { background:#22C55E; } .kpi-blue::after { background:#3B82F6; }
.kpi-orange::after { background:#F97316; } .kpi-red::after { background:#EF4444; }
.kpi-purple::after { background:#8B5CF6; } .kpi-teal::after { background:#14B8A6; }
.kpi-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#94A3B8; margin-bottom:8px; }
.kpi-value { font-size:28px; font-weight:900; color:#0F172A; line-height:1; letter-spacing:-1px; }
.kpi-sub { font-size:11px; color:#94A3B8; margin-top:6px; }
.kpi-icon { position:absolute; top:18px; right:20px; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:15px; }
.kpi-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }
.badge-up { background:#DCFCE7; color:#15803D; }
.badge-warn { background:#FEF3C7; color:#B45309; }
.badge-red  { background:#FEE2E2; color:#B91C1C; }
.badge-freeze { background:#EDE9FE; color:#6D28D9; }

/* ── Section cards ── */
.ep-card { background:#fff; border-radius:16px; border:1px solid #EEF0F6; overflow:hidden; }
.ep-card-hdr { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #F1F5F9; }
.ep-card-title { font-size:14px; font-weight:800; color:#0F172A; }
.ep-card-sub { font-size:11px; color:#94A3B8; margin-top:2px; }

/* ── Grid layouts ── */
.two-col { display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:20px; }
.two-col-eq { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px; }
.three-col { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:20px; }

/* ── Tables ── */
.ep-table { width:100%; border-collapse:collapse; font-size:13px; }
.ep-table th { padding:10px 16px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; background:#F8FAFC; border-bottom:1px solid #F1F5F9; text-align:left; }
.ep-table td { padding:12px 16px; border-bottom:1px solid #F8FAFC; vertical-align:middle; }
.ep-table tr:last-child td { border-bottom:none; }
.ep-table tr:hover td { background:#FAFBFD; }

/* ── Pill badges ── */
.pill { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; display:inline-block; }
.pill-credit { background:#DCFCE7; color:#15803D; }
.pill-debit  { background:#FEE2E2; color:#B91C1C; }
.pill-frozen { background:#EDE9FE; color:#6D28D9; }
.pill-active { background:#DCFCE7; color:#15803D; }

/* ── Forms ── */
.ep-input { padding:9px 12px; border:1.5px solid #E2E8F0; border-radius:9px; font-size:13px; width:100%; outline:none; transition:border-color .2s; }
.ep-input:focus { border-color:#3B82F6; }
.ep-label { font-size:11px; font-weight:700; color:#64748B; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:.5px; }
.ep-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:9px; font-size:12px; font-weight:700; cursor:pointer; border:none; text-decoration:none; }
.ep-btn-primary   { background:#07003B; color:#fff; }
.ep-btn-success   { background:#22C55E; color:#fff; }
.ep-btn-danger    { background:#EF4444; color:#fff; }
.ep-btn-warning   { background:#F97316; color:#fff; }
.ep-btn-outline   { background:#fff; color:#374151; border:1.5px solid #E2E8F0; }
.ep-btn-sm { padding:5px 10px; font-size:11px; border-radius:7px; }

/* ── Modal ── */
.ep-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
.ep-modal-box { background:#fff; border-radius:20px; padding:28px; width:90%; max-width:480px; }
.ep-modal-title { font-size:17px; font-weight:900; color:#0F172A; margin-bottom:4px; }
.ep-modal-sub { font-size:12px; color:#94A3B8; margin-bottom:20px; }

/* ── Alert ── */
.ep-alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; }
.ep-alert-success { background:#DCFCE7; color:#15803D; border:1px solid #86EFAC; }
.ep-alert-danger  { background:#FEE2E2; color:#B91C1C; border:1px solid #FCA5A5; }

/* ── Float / Health bar ── */
.health-bar { height:8px; border-radius:4px; background:#F1F5F9; overflow:hidden; margin-top:8px; }
.health-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#22C55E,#3B82F6); transition:width .8s; }

/* ── Tab strip ── */
.ep-tabs { display:flex; gap:2px; background:#F1F5F9; padding:3px; border-radius:10px; margin-bottom:16px; }
.ep-tab { padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:none; background:none; color:#64748B; }
.ep-tab.active { background:#fff; color:#07003B; font-weight:800; box-shadow:0 1px 3px rgba(0,0,0,.08); }
</style>
@endpush

@section('content')
<div class="ep">

{{-- ══ HEADER ══ --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <div style="width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,#07003B,#3B82F6);display:flex;align-items:center;justify-content:center">
        <i class="fas fa-university" style="color:#fff;font-size:16px"></i>
      </div>
      <h1 style="font-size:22px;font-weight:900;color:#0F172A;letter-spacing:-.5px;margin:0">ePay Financial Center</h1>
    </div>
    <p style="font-size:12px;color:#94A3B8;margin:0">Real-time wallet management · {{ now()->format('l, d M Y · H:i') }}</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="{{ route('admin.wallet.transactions') }}" class="ep-btn ep-btn-outline"><i class="fas fa-list"></i> Transactions</a>
    <a href="{{ route('admin.wallet.withdrawals') }}" class="ep-btn ep-btn-outline" style="position:relative">
      <i class="fas fa-arrow-up-from-bracket"></i> Withdrawals
      @if($stats['pending_withdrawal_cnt'] > 0)
        <span style="background:#EF4444;color:#fff;border-radius:20px;padding:1px 6px;font-size:10px;font-weight:800">{{ $stats['pending_withdrawal_cnt'] }}</span>
      @endif
    </a>
    <button class="ep-btn ep-btn-primary" onclick="showModal('creditModal')"><i class="fas fa-plus"></i> Credit User</button>
    <a href="{{ route('admin.wallet.settings') }}" class="ep-btn ep-btn-outline"><i class="fas fa-cog"></i></a>
  </div>
</div>

@if(session('success'))<div class="ep-alert ep-alert-success"><i class="fas fa-check-circle" style="margin-right:6px"></i>{{ session('success') }}</div>@endif
@if(session('error'))<div class="ep-alert ep-alert-danger"><i class="fas fa-exclamation-circle" style="margin-right:6px"></i>{{ session('error') }}</div>@endif

{{-- ══ KPI ROW 1 ══ --}}
<div class="kpi-grid">
  <div class="kpi kpi-blue">
    <div class="kpi-icon" style="background:#EFF6FF"><i class="fas fa-wallet" style="color:#3B82F6"></i></div>
    <div class="kpi-label">Total Float</div>
    <div class="kpi-value">${{ number_format($stats['total_balance'],2) }}</div>
    <div class="kpi-sub">Across {{ number_format($stats['user_count']) }} accounts</div>
  </div>
  <div class="kpi kpi-green">
    <div class="kpi-icon" style="background:#F0FDF4"><i class="fas fa-arrow-trend-up" style="color:#22C55E"></i></div>
    <div class="kpi-label">Total Topups</div>
    <div class="kpi-value">${{ number_format($stats['total_topups'],2) }}</div>
    <div class="kpi-sub">Lifetime via WaafiPay</div>
  </div>
  <div class="kpi kpi-orange">
    <div class="kpi-icon" style="background:#FFF7ED"><i class="fas fa-clock" style="color:#F97316"></i></div>
    <div class="kpi-label">Pending Payouts</div>
    <div class="kpi-value">${{ number_format($stats['pending_withdrawals'],2) }}</div>
    <div class="kpi-sub"><span class="badge-warn kpi-badge">{{ $stats['pending_withdrawal_cnt'] }} requests</span></div>
  </div>
  <div class="kpi kpi-purple">
    <div class="kpi-icon" style="background:#F5F3FF"><i class="fas fa-snowflake" style="color:#8B5CF6"></i></div>
    <div class="kpi-label">Frozen Wallets</div>
    <div class="kpi-value">{{ $stats['frozen_count'] }}</div>
    <div class="kpi-sub">{{ $stats['user_count'] - $stats['frozen_count'] }} active</div>
  </div>
</div>

{{-- ══ KPI ROW 2 ══ --}}
<div class="kpi-grid" style="margin-bottom:20px">
  <div class="kpi kpi-teal">
    <div class="kpi-icon" style="background:#F0FDFA"><i class="fas fa-calendar-day" style="color:#14B8A6"></i></div>
    <div class="kpi-label">Today's Volume</div>
    <div class="kpi-value">${{ number_format($stats['today_volume'],2) }}</div>
    <div class="kpi-sub" style="display:flex;gap:10px">
      <span style="color:#22C55E">+${{ number_format($stats['today_credit'],2) }}</span>
      <span style="color:#EF4444">-${{ number_format($stats['today_debit'],2) }}</span>
    </div>
  </div>
  <div class="kpi kpi-blue">
    <div class="kpi-icon" style="background:#EFF6FF"><i class="fas fa-calendar-week" style="color:#3B82F6"></i></div>
    <div class="kpi-label">This Month</div>
    <div class="kpi-value">${{ number_format($stats['month_volume'],2) }}</div>
    <div class="kpi-sub">Total transaction volume</div>
  </div>
  <div class="kpi kpi-green">
    <div class="kpi-icon" style="background:#F0FDF4"><i class="fas fa-arrows-rotate" style="color:#22C55E"></i></div>
    <div class="kpi-label">Month Topups</div>
    <div class="kpi-value">${{ number_format($stats['month_topups'],2) }}</div>
    <div class="kpi-sub">WaafiPay this month</div>
  </div>
  <div class="kpi kpi-orange">
    <div class="kpi-icon" style="background:#FFF7ED"><i class="fas fa-exchange-alt" style="color:#F97316"></i></div>
    <div class="kpi-label">All Transactions</div>
    <div class="kpi-value">{{ number_format($stats['total_transactions']) }}</div>
    <div class="kpi-sub">{{ number_format($stats['p2p_count']) }} P2P this month</div>
  </div>
</div>

{{-- ══ CHART + TOP USERS ══ --}}
<div class="two-col" style="margin-bottom:20px">
  {{-- 7-Day Chart --}}
  <div class="ep-card">
    <div class="ep-card-hdr">
      <div>
        <div class="ep-card-title">Transaction Volume — Last 7 Days</div>
        <div class="ep-card-sub">Daily credits vs debits</div>
      </div>
    </div>
    <div style="padding:20px">
      <canvas id="volumeChart" height="160"></canvas>
    </div>
  </div>

  {{-- Payment Method Breakdown --}}
  <div class="ep-card">
    <div class="ep-card-hdr">
      <div class="ep-card-title">This Month by Channel</div>
    </div>
    <div style="padding:16px">
      @foreach($stats['method_breakdown'] as $m)
      @php $pct = $stats['month_volume'] > 0 ? min(100, ($m->vol / $stats['month_volume']) * 100) : 0; @endphp
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span style="font-weight:700;color:#374151;text-transform:capitalize">{{ str_replace('_',' ', $m->payment_method ?? 'Unknown') }}</span>
          <span style="color:#64748B">${{ number_format($m->vol,2) }} · {{ $m->cnt }}tx</span>
        </div>
        <div class="health-bar">
          <div class="health-fill" style="width:{{ $pct }}%;background:{{ ['#3B82F6','#22C55E','#F97316','#8B5CF6','#14B8A6','#EF4444','#F59E0B','#EC4899'][($loop->index % 8)] }}"></div>
        </div>
      </div>
      @endforeach
      @if($stats['method_breakdown']->isEmpty())
        <div style="color:#94A3B8;font-size:13px;text-align:center;padding:20px 0">No transactions this month</div>
      @endif
    </div>
  </div>
</div>

{{-- ══ QUICK ACTIONS ROW ══ --}}
<div class="three-col" style="margin-bottom:20px">
  {{-- Manual Credit --}}
  <div class="ep-card">
    <div class="ep-card-hdr">
      <div>
        <div class="ep-card-title" style="color:#22C55E"><i class="fas fa-plus-circle" style="margin-right:6px"></i>Credit User</div>
        <div class="ep-card-sub">Add funds to wallet</div>
      </div>
    </div>
    <div style="padding:16px">
      <form method="POST" action="{{ route('admin.wallet.credit') }}">@csrf
        <div style="margin-bottom:10px">
          <label class="ep-label">User</label>
          <select name="user_id" required class="ep-input">
            <option value="">Select user...</option>
            @foreach($users as $u) @if($u->wallet_id)
              <option value="{{ $u->id }}">{{ $u->name }} — ${{ number_format($u->balance??0,2) }}</option>
            @endif @endforeach
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
          <div><label class="ep-label">Amount ($)</label><input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00" class="ep-input"></div>
          <div><label class="ep-label">Reason</label><input type="text" name="note" required placeholder="Reason" class="ep-input"></div>
        </div>
        <button type="submit" class="ep-btn ep-btn-success" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Add Credit</button>
      </form>
    </div>
  </div>

  {{-- Manual Debit --}}
  <div class="ep-card">
    <div class="ep-card-hdr">
      <div>
        <div class="ep-card-title" style="color:#EF4444"><i class="fas fa-minus-circle" style="margin-right:6px"></i>Debit User</div>
        <div class="ep-card-sub">Deduct from wallet</div>
      </div>
    </div>
    <div style="padding:16px">
      <form method="POST" action="{{ route('admin.wallet.debit') }}" onsubmit="return confirm('Debit this amount?')">@csrf
        <div style="margin-bottom:10px">
          <label class="ep-label">User</label>
          <select name="user_id" required class="ep-input">
            <option value="">Select user...</option>
            @foreach($users as $u) @if($u->wallet_id && ($u->balance??0) > 0)
              <option value="{{ $u->id }}">{{ $u->name }} — ${{ number_format($u->balance,2) }}</option>
            @endif @endforeach
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
          <div><label class="ep-label">Amount ($)</label><input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00" class="ep-input"></div>
          <div><label class="ep-label">Reason</label><input type="text" name="note" required placeholder="Reason" class="ep-input"></div>
        </div>
        <button type="submit" class="ep-btn ep-btn-danger" style="width:100%;justify-content:center"><i class="fas fa-minus"></i> Apply Debit</button>
      </form>
    </div>
  </div>

  {{-- P2P Transfer --}}
  <div class="ep-card">
    <div class="ep-card-hdr">
      <div>
        <div class="ep-card-title" style="color:#3B82F6"><i class="fas fa-exchange-alt" style="margin-right:6px"></i>P2P Transfer</div>
        <div class="ep-card-sub">Move funds between users</div>
      </div>
    </div>
    <div style="padding:16px">
      <form method="POST" action="{{ route('admin.wallet.transfer') }}" onsubmit="return confirm('Execute this transfer?')">@csrf
        <div style="margin-bottom:10px">
          <label class="ep-label">From User</label>
          <select name="from_user_id" required class="ep-input">
            <option value="">From...</option>
            @foreach($users as $u) @if($u->wallet_id && ($u->balance??0) > 0)
              <option value="{{ $u->id }}">{{ $u->name }} — ${{ number_format($u->balance,2) }}</option>
            @endif @endforeach
          </select>
        </div>
        <div style="margin-bottom:10px">
          <label class="ep-label">To User</label>
          <select name="to_user_id" required class="ep-input">
            <option value="">To...</option>
            @foreach($users as $u)
              <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
          <div><label class="ep-label">Amount ($)</label><input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00" class="ep-input"></div>
          <div><label class="ep-label">Note</label><input type="text" name="note" required placeholder="Note" class="ep-input"></div>
        </div>
        <button type="submit" class="ep-btn ep-btn-primary" style="width:100%;justify-content:center"><i class="fas fa-exchange-alt"></i> Transfer</button>
      </form>
    </div>
  </div>
</div>

{{-- ══ ACCOUNTS TABLE ══ --}}
<div class="ep-card" style="margin-bottom:20px">
  <div class="ep-card-hdr">
    <div>
      <div class="ep-card-title">ePay Accounts</div>
      <div class="ep-card-sub">{{ $users->total() }} users · ordered by balance</div>
    </div>
    <div style="display:flex;gap:8px">
      <button onclick="document.getElementById('bulkResetModal').style.display='flex'" class="ep-btn ep-btn-danger ep-btn-sm"><i class="fas fa-undo"></i> Reset All</button>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="ep-table">
      <thead>
        <tr>
          <th>User</th>
          <th style="text-align:right">Balance</th>
          <th style="text-align:right">Total In</th>
          <th style="text-align:right">Total Out</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($users as $u)
        <tr>
          <td>
            <div style="font-weight:700;color:#0F172A">{{ $u->name }}</div>
            <div style="font-size:11px;color:#94A3B8">{{ $u->email }} · {{ $u->phone }}</div>
          </td>
          <td style="text-align:right">
            <span style="font-weight:900;font-size:15px;color:{{ ($u->balance??0)>0?'#15803D':'#94A3B8' }}">${{ number_format($u->balance??0,2) }}</span>
          </td>
          <td style="text-align:right;color:#3B82F6;font-weight:700">${{ number_format($u->total_earned??0,2) }}</td>
          <td style="text-align:right;color:#EF4444;font-weight:700">${{ number_format($u->total_withdrawn??0,2) }}</td>
          <td style="text-align:center">
            @if($u->is_frozen)
              <span class="pill pill-frozen"><i class="fas fa-snowflake" style="margin-right:3px"></i>Frozen</span>
            @elseif($u->wallet_id)
              <span class="pill pill-active"><i class="fas fa-circle" style="margin-right:3px;font-size:6px"></i>Active</span>
            @else
              <span class="pill" style="background:#F1F5F9;color:#94A3B8">No Wallet</span>
            @endif
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
              @if($u->wallet_id)
              <a href="{{ route('admin.wallet.user-detail', $u->id) }}" class="ep-btn ep-btn-outline ep-btn-sm" title="Financial Profile"><i class="fas fa-chart-line"></i></a>
              <a href="{{ route('admin.wallet.transactions', ['user_id'=>$u->id]) }}" class="ep-btn ep-btn-outline ep-btn-sm" title="Transactions"><i class="fas fa-list"></i></a>
              @if($u->is_frozen)
                <button class="ep-btn ep-btn-success ep-btn-sm" onclick="submitForm('/admin/wallet/unfreeze/{{ $u->id }}')" title="Unfreeze"><i class="fas fa-unlock"></i></button>
              @else
                <button class="ep-btn ep-btn-outline ep-btn-sm" style="border-color:#8B5CF6;color:#8B5CF6" onclick="openFreezeModal({{ $u->id }},'{{ addslashes($u->name) }}')" title="Freeze Wallet"><i class="fas fa-snowflake"></i></button>
              @endif
              @if(($u->balance??0) > 0)
                <button class="ep-btn ep-btn-outline ep-btn-sm" style="border-color:#EF4444;color:#EF4444" onclick="openResetWallet({{ $u->id }},'{{ addslashes($u->name) }}',{{ number_format($u->balance??0,2,'.','') }})" title="Reset to $0"><i class="fas fa-undo"></i></button>
              @endif
              @endif
              <button class="ep-btn ep-btn-outline ep-btn-sm" style="border-color:#F97316;color:#F97316" onclick="openResetPin({{ $u->id }},'{{ addslashes($u->name) }}')" title="Reset PIN"><i class="fas fa-key"></i></button>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div style="padding:16px">{{ $users->links() }}</div>
  </div>
</div>

{{-- ══ MODALS ══ --}}

{{-- Freeze Wallet --}}
<div id="freezeModal" class="ep-modal">
  <div class="ep-modal-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:44px;height:44px;background:#EDE9FE;border-radius:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-snowflake" style="color:#8B5CF6;font-size:18px"></i></div>
      <div><div class="ep-modal-title">Freeze Wallet</div><div id="freezeUserName" class="ep-modal-sub" style="margin-bottom:0"></div></div>
    </div>
    <form id="freezeForm" method="POST">@csrf
      <div style="margin-bottom:16px">
        <label class="ep-label">Reason (shown in audit log)</label>
        <textarea name="reason" required rows="3" placeholder="e.g. Suspicious activity detected on 2026-07-21" class="ep-input" style="resize:none"></textarea>
      </div>
      <div style="background:#FEF3C7;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#B45309"><i class="fas fa-info-circle" style="margin-right:6px"></i>User will not be able to spend or withdraw while frozen. Credits still work.</div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="closeModal('freezeModal')" class="ep-btn ep-btn-outline" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn" style="flex:1;justify-content:center;background:#8B5CF6;color:#fff"><i class="fas fa-snowflake"></i> Freeze</button>
      </div>
    </form>
  </div>
</div>

{{-- Reset Single Wallet --}}
<div id="resetWalletModal" class="ep-modal">
  <div class="ep-modal-box">
    <div class="ep-modal-title">Reset ePay Balance</div>
    <div id="resetWalletMsg" class="ep-modal-sub"></div>
    <div style="background:#FEE2E2;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#B91C1C"><i class="fas fa-triangle-exclamation" style="margin-right:6px"></i>This cannot be undone. Transaction history is preserved.</div>
    <form id="resetWalletForm" method="POST">@csrf
      <div style="display:flex;gap:10px">
        <button type="button" onclick="closeModal('resetWalletModal')" class="ep-btn ep-btn-outline" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn ep-btn-danger" style="flex:1;justify-content:center">Reset to $0.00</button>
      </div>
    </form>
  </div>
</div>

{{-- Bulk Reset --}}
<div id="bulkResetModal" class="ep-modal">
  <div class="ep-modal-box">
    <div class="ep-modal-title" style="color:#EF4444">Reset ALL ePay Balances</div>
    <div class="ep-modal-sub">This will zero out every user's wallet. Cannot be undone.</div>
    <div style="background:#FEE2E2;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#B91C1C"><i class="fas fa-triangle-exclamation" style="margin-right:6px"></i><strong>Warning:</strong> All {{ $stats['user_count'] }} wallets (Total: ${{ number_format($stats['total_balance'],2) }}) will be debited to zero.</div>
    <form action="{{ route('admin.wallet.bulk-reset') }}" method="POST">@csrf
      <div style="display:flex;gap:10px">
        <button type="button" onclick="closeModal('bulkResetModal')" class="ep-btn ep-btn-outline" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn ep-btn-danger" style="flex:1;justify-content:center">Reset All</button>
      </div>
    </form>
  </div>
</div>

{{-- Reset PIN --}}
<div id="resetPinModal" class="ep-modal">
  <div class="ep-modal-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:44px;height:44px;background:#FFF7ED;border-radius:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-key" style="color:#F97316;font-size:18px"></i></div>
      <div><div class="ep-modal-title">Reset PIN</div><div id="resetPinUser" class="ep-modal-sub" style="margin-bottom:0"></div></div>
    </div>
    <form id="resetPinForm" method="POST">@csrf
      <div style="margin-bottom:16px">
        <label class="ep-label">New 4-digit PIN</label>
        <input type="text" name="pin" required pattern="\d{4}" maxlength="4" placeholder="••••" class="ep-input" style="font-size:28px;font-weight:900;letter-spacing:12px;text-align:center">
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="closeModal('resetPinModal')" class="ep-btn ep-btn-outline" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn ep-btn-warning" style="flex:1;justify-content:center">Set PIN</button>
      </div>
    </form>
  </div>
</div>

{{-- Hidden forms --}}
<form id="_unfreezeForm" method="POST" style="display:none">@csrf</form>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Chart ──────────────────────────────────────────────────────────────────────
const dailyData = @json($stats['daily_chart']);
const labels  = dailyData.map(d => d.date);
const credits = dailyData.map(d => parseFloat(d.credit)||0);
const debits  = dailyData.map(d => parseFloat(d.debit)||0);

new Chart(document.getElementById('volumeChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      { label:'Credit',  data:credits, backgroundColor:'rgba(34,197,94,.75)', borderRadius:5 },
      { label:'Debit',   data:debits,  backgroundColor:'rgba(239,68,68,.65)',  borderRadius:5 },
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'top', labels:{ font:{size:11}, boxWidth:12 } } },
    scales:{
      x:{ grid:{display:false}, ticks:{font:{size:10}} },
      y:{ grid:{color:'#F1F5F9'}, ticks:{font:{size:10}, callback:v=>'$'+v } }
    }
  }
});

// ── Modal helpers ──────────────────────────────────────────────────────────────
function showModal(id)  { document.getElementById(id).style.display='flex'; }
function closeModal(id) { document.getElementById(id).style.display='none'; }

function openFreezeModal(userId, name) {
  document.getElementById('freezeForm').action = '/admin/wallet/freeze/' + userId;
  document.getElementById('freezeUserName').textContent = name;
  showModal('freezeModal');
}
function openResetWallet(userId, name, bal) {
  document.getElementById('resetWalletForm').action = '/admin/wallet/reset/' + userId;
  document.getElementById('resetWalletMsg').textContent = 'Reset ' + name + '\'s balance ($' + bal + ') to $0.00?';
  showModal('resetWalletModal');
}
function openResetPin(userId, name) {
  document.getElementById('resetPinForm').action = '/admin/wallet/reset-pin/' + userId;
  document.getElementById('resetPinUser').textContent = name;
  showModal('resetPinModal');
}
function submitForm(action) {
  if (!confirm('Continue?')) return;
  const f = document.getElementById('_unfreezeForm');
  f.action = action; f.submit();
}
document.querySelectorAll('.ep-modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.style.display='none'; }));
</script>
@endpush
