@extends('admin.layouts.app')
@section('title', 'Mobile Pay Accounts')

@push('styles')
<style>
.mp { background:#F4F6FB; min-height:100vh; padding:24px 28px; }
.mp-card { background:#fff; border-radius:16px; border:1px solid #EEF0F6; overflow:hidden; margin-bottom:20px; }
.mp-hdr { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #F1F5F9; }
.mp-title { font-size:14px; font-weight:800; color:#0F172A; }
.mp-table { width:100%; border-collapse:collapse; }
.mp-table th { padding:10px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; background:#F8FAFC; text-align:left; }
.mp-table td { padding:12px 16px; font-size:13px; color:#334155; border-top:1px solid #F1F5F9; vertical-align:middle; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-active { background:#DCFCE7; color:#15803D; }
.badge-off { background:#FEE2E2; color:#B91C1C; }
.btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; border:none; text-decoration:none; }
.btn-primary { background:#07003B; color:#fff; }
.btn-danger  { background:#FEE2E2; color:#B91C1C; }
.btn-sm { padding:4px 10px; font-size:11px; border-radius:6px; }
.btn-warning { background:#FEF3C7; color:#B45309; }
.ussd-code { font-family:monospace; font-size:12px; background:#F1F5F9; padding:2px 8px; border-radius:6px; color:#334155; }
</style>
@endpush

@section('content')
<div class="mp">

  {{-- Page header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0;">📱 Mobile Pay</h1>
      <p style="font-size:13px;color:#64748B;margin:4px 0 0;">Manage USSD mobile money accounts shown to customers</p>
    </div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">
      + Add Account
    </button>
  </div>

  @if(session('success'))
    <div style="background:#DCFCE7;color:#15803D;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px;">
      ✓ {{ session('success') }}
    </div>
  @endif

  {{-- Accounts table --}}
  <div class="mp-card">
    <div class="mp-hdr">
      <span class="mp-title">Active Accounts ({{ $accounts->count() }})</span>
    </div>
    <table class="mp-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Account Number</th>
          <th>USSD Template</th>
          <th>Status</th>
          <th>Order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      @forelse($accounts as $acc)
        <tr>
          <td>{{ $acc->id }}</td>
          <td>
            <span style="font-size:18px;">{{ $acc->icon ?? '📱' }}</span>
            <strong>{{ $acc->name }}</strong>
          </td>
          <td>{{ $acc->account_number }}</td>
          <td><span class="ussd-code">{{ $acc->ussd_template }}</span></td>
          <td>
            <form method="POST" action="{{ route('admin.mobile-pay.toggle', $acc) }}" style="display:inline;">
              @csrf @method('PATCH')
              <button type="submit" class="badge {{ $acc->is_active ? 'badge-active' : 'badge-off' }}" style="cursor:pointer;border:none;">
                {{ $acc->is_active ? 'Active' : 'Inactive' }}
              </button>
            </form>
          </td>
          <td>{{ $acc->sort_order }}</td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <button onclick="openEdit({{ $acc->id }}, '{{ addslashes($acc->name) }}', '{{ addslashes($acc->account_number) }}', '{{ addslashes($acc->ussd_template) }}', '{{ addslashes($acc->instructions ?? '') }}', '{{ $acc->icon ?? '' }}', {{ $acc->sort_order }})"
              class="btn btn-warning btn-sm">Edit</button>
            <form method="POST" action="{{ route('admin.mobile-pay.destroy', $acc) }}" onsubmit="return confirm('Delete this account?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;padding:32px;color:#94A3B8;">No accounts yet. Add one above.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  {{-- Instructions box --}}
  <div class="mp-card" style="padding:16px 20px;">
    <p style="font-size:13px;font-weight:700;color:#0F172A;margin:0 0 8px;">USSD Template Format</p>
    <p style="font-size:12px;color:#64748B;margin:0;">Use <code style="background:#F1F5F9;padding:1px 6px;border-radius:4px;">{amount}</code> as placeholder for the payment amount.<br>
    Example: <span class="ussd-code">*789*090376464*{amount}#</span> → when paying $50 becomes <span class="ussd-code">*789*090376464*50#</span></p>
  </div>
</div>

{{-- ADD MODAL --}}
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:24px;width:480px;max-width:95vw;">
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:800;color:#0F172A;">Add Mobile Pay Account</h3>
    <form method="POST" action="{{ route('admin.mobile-pay.store') }}">
      @csrf
      <div style="display:grid;gap:12px;">
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">Provider Name *</label>
          <input name="name" required placeholder="e.g. EVC Plus" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">Account Number (display) *</label>
          <input name="account_number" required placeholder="e.g. 0615XXXXXX" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">USSD Template * (use {amount})</label>
          <input name="ussd_template" required placeholder="*789*090376464*{amount}#" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;font-family:monospace;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">Instructions (shown to customer)</label>
          <textarea name="instructions" rows="3" placeholder="Fadlan lacagta u dir lambarka soo socda..." style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;resize:vertical;"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:12px;font-weight:700;color:#475569;">Icon (emoji)</label>
            <input name="icon" placeholder="📱" maxlength="4" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:20px;margin-top:4px;box-sizing:border-box;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;color:#475569;">Sort Order</label>
            <input name="sort_order" type="number" value="0" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;">
          </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
          <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn" style="background:#F1F5F9;color:#334155;">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Account</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- EDIT MODAL --}}
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:24px;width:480px;max-width:95vw;">
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:800;color:#0F172A;">Edit Account</h3>
    <form id="editForm" method="POST">
      @csrf @method('PATCH')
      <div style="display:grid;gap:12px;">
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">Provider Name *</label>
          <input id="eName" name="name" required style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">Account Number *</label>
          <input id="eAccount" name="account_number" required style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">USSD Template *</label>
          <input id="eUssd" name="ussd_template" required style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;font-family:monospace;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#475569;">Instructions</label>
          <textarea id="eInstr" name="instructions" rows="3" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;resize:vertical;"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:12px;font-weight:700;color:#475569;">Icon</label>
            <input id="eIcon" name="icon" maxlength="4" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:20px;margin-top:4px;box-sizing:border-box;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;color:#475569;">Sort Order</label>
            <input id="eOrder" name="sort_order" type="number" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;margin-top:4px;box-sizing:border-box;">
          </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
          <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn" style="background:#F1F5F9;color:#334155;">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, name, account, ussd, instr, icon, order) {
  document.getElementById('editForm').action = '/admin/mobile-pay/' + id;
  document.getElementById('eName').value    = name;
  document.getElementById('eAccount').value = account;
  document.getElementById('eUssd').value    = ussd;
  document.getElementById('eInstr').value   = instr;
  document.getElementById('eIcon').value    = icon;
  document.getElementById('eOrder').value   = order;
  document.getElementById('editModal').style.display = 'flex';
}
</script>
@endsection
