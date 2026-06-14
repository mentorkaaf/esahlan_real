@extends('admin.layouts.app')
@section('title', 'User Details')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">User: {{ $user->name }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
            <li class="breadcrumb-item active">{{ $user->name }}</li>
        </ol>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning" onclick="document.getElementById('resetPinModal').style.display='flex'">
            <i class="fas fa-key"></i> Reset PIN
        </button>
        <form action="{{ route('admin.users.status', $user->id) }}" method="POST">
            @csrf @method('PATCH')
            @if($user->status === 'active')
                <input type="hidden" name="status" value="banned">
                <button class="btn btn-danger"><i class="fas fa-ban"></i> Ban User</button>
            @else
                <input type="hidden" name="status" value="active">
                <button class="btn btn-success"><i class="fas fa-check"></i> Activate User</button>
            @endif
        </form>
    </div>

{{-- Reset PIN Modal --}}
<div id="resetPinModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:360px;max-width:90vw;">
        <h3 style="margin:0 0 6px;font-size:18px;"><i class="fas fa-key" style="color:var(--warning);margin-right:8px;"></i>Reset PIN</h3>
        <p style="font-size:13px;color:#888;margin:0 0 20px;">Set a new 4-digit PIN for <strong>{{ $user->name }}</strong>. The user will be logged out of all devices.</p>
        <form action="{{ route('admin.users.reset-pin', $user->id) }}" method="POST">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">New PIN (4 digits)</label>
                <input type="password" name="pin" maxlength="4" pattern="\d{4}" inputmode="numeric"
                    placeholder="••••"
                    style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:20px;letter-spacing:8px;text-align:center;"
                    required>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="document.getElementById('resetPinModal').style.display='none'"
                    class="btn btn-outline" style="flex:1;">Cancel</button>
                <button type="submit" class="btn btn-warning" style="flex:1;"><i class="fas fa-save"></i> Save PIN</button>
            </div>
        </form>
    </div>
</div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header"><span><i class="fas fa-user" style="color:var(--primary);margin-right:8px;"></i>Profile</span></div>
            <div class="card-body">
                <table>
                    <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Name</td><td class="fw-bold">{{ $user->name }}</td></tr>
                    <tr><td class="text-muted">Phone</td><td>{{ $user->phone ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $user->email ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Role</td><td><span class="badge badge-info">{{ $user->role?->name ?? '—' }}</span></td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td><span class="badge {{ $user->status === 'active' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($user->status) }}</span></td>
                    </tr>
                    <tr><td class="text-muted">Language</td><td>{{ strtoupper($user->preferred_language ?? 'en') }}</td></tr>
                    <tr><td class="text-muted">Referral Code</td><td><code>{{ $user->referral_code ?? '—' }}</code></td></tr>
                    <tr><td class="text-muted">Phone Verified</td>
                        <td>{{ $user->phone_verified_at ? $user->phone_verified_at->format('d M Y') : '—' }}</td>
                    </tr>
                    <tr><td class="text-muted">Joined</td><td>{{ $user->created_at->format('d M Y H:i') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span><i class="fas fa-wallet" style="color:var(--primary);margin-right:8px;"></i>Wallet</span></div>
            <div class="card-body">
                <div style="text-align:center;padding:20px;">
                    <div style="font-size:36px;font-weight:700;color:var(--primary);">
                        ${{ number_format($user->wallet?->balance ?? 0, 2) }}
                    </div>
                    <div class="text-muted">Current Balance ({{ $user->wallet?->currency ?? 'USD' }})</div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span><i class="fas fa-shopping-bag" style="color:var(--primary);margin-right:8px;"></i>Recent Orders</span></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Order #</th><th>Module</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($user->orders ?? [] as $order)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order->id) }}" class="text-primary">{{ $order->order_number }}</a></td>
                            <td>{{ strtoupper($order->module_slug ?? '—') }}</td>
                            <td>${{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                @php $sc = ['delivered'=>'success','pending'=>'warning','cancelled'=>'danger'][$order->status] ?? 'secondary' @endphp
                                <span class="badge badge-{{ $sc }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span>
                            </td>
                            <td>{{ $order->created_at->format('d M') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:#888;">No orders yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
