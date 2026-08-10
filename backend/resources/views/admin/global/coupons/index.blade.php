@extends('admin.layouts.app')
@section('title', 'Global Coupons')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🎟 Global Coupons</h1>
        <p class="page-subtitle">Special deals & discount coupons for Global Store</p>
    </div>
    <button onclick="document.getElementById('add-modal').style.display='flex'"
        class="btn-primary" style="display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-plus"></i> New Coupon
    </button>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">⚠️ {{ session('error') }}</div>
@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px">
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#1A1A2E">{{ $coupons->count() }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Total Coupons</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#059669">{{ $coupons->where('is_active',true)->count() }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Active</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#F59E0B">{{ $coupons->where('is_new_user_only',true)->count() }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">New User Deals</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#7C3AED">{{ $coupons->sum('user_coupons_count') }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Times Collected</div>
    </div>
</div>

{{-- Coupon Table --}}
<div style="background:#fff;border-radius:16px;border:1px solid #e5e7eb;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">COUPON</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">DISCOUNT</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">MIN ORDER</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">CODE</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">COLLECTED</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">EXPIRES</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">STATUS</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280">ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            @forelse($coupons as $coupon)
            <tr style="border-bottom:1px solid #f3f4f6;transition:background .15s"
                onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                <td style="padding:14px 16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;
                                    {{ $coupon->is_new_user_only ? 'background:#fef3c7' : 'background:#ede9fe' }}">
                            {{ $coupon->is_new_user_only ? '🆕' : '🎟' }}
                        </div>
                        <div>
                            <div style="font-weight:700;color:#1A1A2E">{{ $coupon->name }}</div>
                            <div style="font-size:11px;color:#6b7280">
                                @if($coupon->is_new_user_only)
                                    <span style="background:#fef3c7;color:#92400e;padding:1px 7px;border-radius:20px;font-size:10px;font-weight:700">New User</span>
                                @endif
                                {{ $coupon->label }}
                            </div>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 16px">
                    <span style="font-size:18px;font-weight:900;color:#EF4444">
                        {{ $coupon->type === 'percentage' ? $coupon->value.'%' : '$'.$coupon->value }}
                    </span>
                    <span style="font-size:11px;color:#6b7280"> OFF</span>
                    @if($coupon->maximum_discount)
                        <div style="font-size:10px;color:#9ca3af">Cap ${{ $coupon->maximum_discount }}</div>
                    @endif
                </td>
                <td style="padding:14px 16px;color:#374151;font-weight:600">${{ number_format($coupon->minimum_order,2) }}</td>
                <td style="padding:14px 16px">
                    <code style="background:#f3f4f6;padding:3px 8px;border-radius:6px;font-size:12px;letter-spacing:1px">{{ $coupon->code }}</code>
                </td>
                <td style="padding:14px 16px;color:#374151">{{ $coupon->user_coupons_count }} users</td>
                <td style="padding:14px 16px;color:#6b7280;font-size:12px">
                    {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : 'No expiry' }}
                </td>
                <td style="padding:14px 16px">
                    <span style="padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;
                                 {{ $coupon->is_active ? 'background:#d1fae5;color:#065f46' : 'background:#fee2e2;color:#991b1b' }}">
                        {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td style="padding:14px 16px">
                    <div style="display:flex;gap:6px">
                        <button onclick="openEdit({{ $coupon->id }}, {{ json_encode($coupon) }})"
                            style="padding:6px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;font-size:12px;font-weight:600;cursor:pointer">
                            ✏️
                        </button>
                        <form method="POST" action="{{ route('admin.global.coupons.toggle', $coupon) }}" style="display:inline">
                            @csrf @method('PATCH')
                            <button type="submit" style="padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid;
                                {{ $coupon->is_active ? 'border-color:#fca5a5;background:#fef2f2;color:#991b1b' : 'border-color:#6ee7b7;background:#ecfdf5;color:#065f46' }}">
                                {{ $coupon->is_active ? '🔴' : '🟢' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.global.coupons.destroy', $coupon) }}"
                              onsubmit="return confirm('Delete coupon: {{ $coupon->name }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:6px 12px;border-radius:8px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;font-size:12px;cursor:pointer">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="padding:60px;text-align:center;color:#9ca3af">
                <div style="font-size:40px;margin-bottom:12px">🎟</div>
                <div style="font-weight:600;margin-bottom:6px">No coupons yet</div>
                <div style="font-size:12px">Create your first special deal</div>
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>


{{-- ══ ADD MODAL ════════════════════════════════════════════════════════════════ --}}
<div id="add-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;overflow:auto">
    <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:540px;margin:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;font-weight:800;color:#1A1A2E">🎟 New Coupon</h3>
            <button onclick="document.getElementById('add-modal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af">×</button>
        </div>
        <form method="POST" action="{{ route('admin.global.coupons.store') }}">
            @csrf
            @include('admin.global.coupons._form')
            <div style="display:flex;gap:10px;margin-top:20px">
                <button type="button" onclick="document.getElementById('add-modal').style.display='none'"
                    style="flex:1;padding:12px;border-radius:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="flex:2;padding:12px;border-radius:10px;border:none;background:#1A1A2E;color:#F59E0B;font-weight:800;cursor:pointer">
                    Create Coupon
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ EDIT MODAL ═══════════════════════════════════════════════════════════════ --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;overflow:auto">
    <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:540px;margin:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;font-weight:800;color:#1A1A2E">✏️ Edit Coupon</h3>
            <button onclick="document.getElementById('edit-modal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af">×</button>
        </div>
        <form id="edit-form" method="POST" action="">
            @csrf @method('PUT')
            @include('admin.global.coupons._form', ['editing' => true])
            <div style="display:flex;gap:10px;margin-top:20px">
                <button type="button" onclick="document.getElementById('edit-modal').style.display='none'"
                    style="flex:1;padding:12px;border-radius:10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="flex:2;padding:12px;border-radius:10px;border:none;background:#1A1A2E;color:#F59E0B;font-weight:800;cursor:pointer">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, c) {
    var f = document.getElementById('edit-form');
    f.action = '/admin/global/coupons/' + id;
    f.querySelector('[name=name]').value = c.name || '';
    f.querySelector('[name=description]').value = c.description || '';
    f.querySelector('[name=label]').value = c.label || '';
    f.querySelector('[name=type]').value = c.type;
    f.querySelector('[name=value]').value = c.value;
    f.querySelector('[name=minimum_order]').value = c.minimum_order || '';
    f.querySelector('[name=maximum_discount]').value = c.maximum_discount || '';
    f.querySelector('[name=usage_limit]').value = c.usage_limit || '';
    f.querySelector('[name=per_user_limit]').value = c.per_user_limit || 1;
    f.querySelector('[name=is_new_user_only]').checked = c.is_new_user_only;
    f.querySelector('[name=is_active]').checked = c.is_active;
    f.querySelector('[name=expires_at]').value = c.expires_at ? c.expires_at.substring(0,10) : '';
    document.getElementById('edit-modal').style.display = 'flex';
}
</script>
@endsection
