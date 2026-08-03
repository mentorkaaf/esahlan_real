@extends('admin.layouts.app')
@section('title', 'Mobile Pay Top-up Requests')

@section('content')
<div style="padding:20px 24px;font-family:'Segoe UI',system-ui,sans-serif">

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
    <div style="font-size:19px;font-weight:900;color:#0f172a">📱 Mobile Pay Top-up Requests</div>
    <a href="{{ route('admin.wallet.index') }}" style="padding:6px 14px;background:#f1f5f9;border-radius:8px;font-size:12px;font-weight:700;color:#374151;text-decoration:none">← Wallet Dashboard</a>
  </div>

  @if(session('success'))
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px">✓ {{ session('success') }}</div>
  @endif
  @if(session('error'))
  <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px">✗ {{ session('error') }}</div>
  @endif

  {{-- ⚠️ Security Notice --}}
  <div style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:8px;margin-bottom:18px">
    <div style="font-weight:800;font-size:13px;color:#92400e;margin-bottom:4px">⚠️ Security: Manual Verification Required</div>
    <div style="font-size:12px;color:#92400e;line-height:1.6">
      Check the screenshot carefully before approving. Verify: (1) Sender name, (2) Amount matches exactly, (3) Date is recent, (4) Screenshot is not edited/fake.
      <strong>Only approve after you confirm the money was received in the Mobile Pay account.</strong>
    </div>
  </div>

  {{-- Stats --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:18px">
    <div style="background:#fff;border:1px solid #e4e9f0;border-radius:10px;padding:14px 16px">
      <div style="font-size:22px;font-weight:900;color:#d97706">{{ $stats['pending'] }}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">⏳ Pending</div>
    </div>
    <div style="background:#fff;border:1px solid #e4e9f0;border-radius:10px;padding:14px 16px">
      <div style="font-size:22px;font-weight:900;color:#10b981">{{ $stats['approved'] }}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">✓ Approved</div>
    </div>
    <div style="background:#fff;border:1px solid #e4e9f0;border-radius:10px;padding:14px 16px">
      <div style="font-size:22px;font-weight:900;color:#ef4444">{{ $stats['rejected'] }}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">✗ Rejected</div>
    </div>
    <div style="background:#fff;border:1px solid #e4e9f0;border-radius:10px;padding:14px 16px">
      <div style="font-size:22px;font-weight:900;color:#3b82f6">${{ number_format($stats['total_approved_amount'],2) }}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">Total Approved</div>
    </div>
  </div>

  {{-- Filter tabs --}}
  <div style="display:flex;gap:4px;background:#f1f5f9;border-radius:10px;padding:4px;width:fit-content;margin-bottom:16px">
    @foreach(['pending'=>'⏳ Pending','approved'=>'✓ Approved','rejected'=>'✗ Rejected','all'=>'All'] as $s=>$label)
    <a href="{{ request()->fullUrlWithQuery(['status'=>$s]) }}"
       style="padding:7px 14px;border-radius:7px;font-size:12.5px;font-weight:700;text-decoration:none;
              {{ $status===$s?'background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.12)':'color:#64748b' }}">
      {{ $label }}
      @if($s==='pending' && $stats['pending']>0)
      <span style="background:#ef4444;color:#fff;border-radius:10px;padding:0 5px;font-size:10px;margin-left:3px">{{ $stats['pending'] }}</span>
      @endif
    </a>
    @endforeach
  </div>

  {{-- Table --}}
  <div style="background:#fff;border-radius:12px;border:1px solid #e4e9f0;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:12.5px">
      <thead>
        <tr style="background:#f8fafc">
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">#</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">User</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">Amount</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">Sender Phone</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">Screenshot</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">Submitted</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">Status</th>
          <th style="padding:9px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $r)
        <tr style="border-top:1px solid #f1f5f9">
          <td style="padding:11px 12px;color:#9ca3af">{{ $r->id }}</td>
          <td style="padding:11px 12px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:32px;height:32px;border-radius:50%;background:#FF8A00;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0">{{ strtoupper(substr($r->name??'?',0,1)) }}</div>
              <div>
                <div style="font-weight:600;color:#0f172a">{{ $r->name }}</div>
                <div style="font-size:11px;color:#9ca3af">{{ $r->email }}</div>
              </div>
            </div>
          </td>
          <td style="padding:11px 12px;font-weight:800;color:#10b981;font-size:14px">${{ number_format($r->amount,2) }}</td>
          <td style="padding:11px 12px;color:#6b7280;font-size:11.5px">{{ $r->sender_phone ?: '—' }}</td>
          <td style="padding:11px 12px">
            @if($r->screenshot_url)
            <a href="{{ $r->screenshot_url }}" target="_blank">
              <img src="{{ $r->screenshot_url }}" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e4e9f0;cursor:pointer" alt="Screenshot">
            </a>
            @else
            <span style="color:#9ca3af;font-size:11px">Not uploaded</span>
            @endif
          </td>
          <td style="padding:11px 12px;color:#6b7280;font-size:11.5px;white-space:nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('d M, H:i') }}</td>
          <td style="padding:11px 12px">
            @if($r->status==='pending')
            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:700;background:rgba(245,158,11,.1);color:#d97706">⏳ Pending</span>
            @elseif($r->status==='approved')
            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:700;background:rgba(16,185,129,.1);color:#059669">✓ Approved</span>
            @else
            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:700;background:rgba(239,68,68,.1);color:#dc2626">✗ Rejected</span>
            @endif
          </td>
          <td style="padding:11px 12px">
            @if($r->status==='pending')
            <div style="display:flex;flex-direction:column;gap:5px">
              <form method="POST" action="{{ route('admin.wallet.topup-requests.approve', $r->id) }}">
                @csrf
                <button style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:#10b981;color:#fff;border:none;border-radius:7px;font-size:11.5px;font-weight:700;cursor:pointer;width:100%">
                  ✓ Approve
                </button>
              </form>
              <form method="POST" action="{{ route('admin.wallet.topup-requests.reject', $r->id) }}"
                    onsubmit="return confirm('Reject this top-up request?')">
                @csrf
                <button style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:#ef4444;color:#fff;border:none;border-radius:7px;font-size:11.5px;font-weight:700;cursor:pointer;width:100%">
                  ✗ Reject
                </button>
              </form>
            </div>
            @else
            <span style="font-size:11px;color:#9ca3af">
              {{ $r->reviewed_at ? \Carbon\Carbon::parse($r->reviewed_at)->format('d M') : '—' }}
              {{ $r->admin_name ? ' · '.$r->admin_name : '' }}
            </span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" style="text-align:center;padding:40px;color:#9ca3af">
            @if($status==='pending') ✅ No pending top-up requests @else No requests found @endif
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    </div>

    @if($requests->hasPages())
    <div style="display:flex;justify-content:flex-end;padding:12px 16px;border-top:1px solid #f1f5f9">
      {!! $requests->appends(request()->query())->links('pagination::simple-bootstrap-4') !!}
    </div>
    @endif
  </div>

</div>
@endsection
