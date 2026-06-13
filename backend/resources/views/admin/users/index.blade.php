@extends('admin.layouts.app')
@section('title', 'Users')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Users</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Users</li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search name, phone, email…" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select name="role" class="form-control" style="width:160px;">
                <option value="">All Roles</option>
                <option value="customer"     {{ request('role')==='customer'     ?'selected':'' }}>Customer</option>
                <option value="vendor_owner" {{ request('role')==='vendor_owner' ?'selected':'' }}>Vendor Owner</option>
                <option value="deliveryman"  {{ request('role')==='deliveryman'  ?'selected':'' }}>Deliveryman</option>
            </select>
            <select name="status" class="form-control" style="width:140px;">
                <option value="">All Status</option>
                <option value="active"   {{ request('status')==='active'   ?'selected':'' }}>Active</option>
                <option value="inactive" {{ request('status')==='inactive' ?'selected':'' }}>Inactive</option>
                <option value="banned"   {{ request('status')==='banned'   ?'selected':'' }}>Banned</option>
            </select>
            @if(request()->hasAny(['search','role','status']))
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                <i class="fas fa-users"></i>
            </div>
            Users
            <span class="badge badge-info" style="margin-left:4px;">{{ $users->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Verified</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php
                    $colors = ['customer'=>'blue','vendor_owner'=>'purple','deliveryman'=>'green'];
                    $c = $colors[$user->role?->name ?? ''] ?? 'orange';
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if(!empty($user->avatar_url) && !str_contains($user->avatar_url,'null'))
                                <img src="{{ $user->avatar_url }}" style="width:36px;height:36px;border-radius:9px;object-fit:cover;flex-shrink:0;">
                            @else
                                <div class="avatar avatar-sm avatar-{{ $c }}">{{ strtoupper(substr($user->name,0,1)) }}</div>
                            @endif
                            <div>
                                <div style="font-weight:700;font-size:13px;">{{ $user->name }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted);">{{ $user->phone }}</td>
                    <td>
                        @php $rn = $user->role?->name ?? 'N/A'; @endphp
                        <span class="badge badge-{{ $colors[$rn] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$rn)) }}</span>
                    </td>
                    <td>
                        @php $st = $user->status ?? 'active'; @endphp
                        <span class="badge {{ $st==='active'?'badge-success':($st==='banned'?'badge-danger':'badge-warning') }} badge-dot">
                            {{ ucfirst($st) }}
                        </span>
                    </td>
                    <td>
                        @if($user->phone_verified_at)
                            <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-times"></i> No</span>
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:5px;align-items:center;">
                            <a href="{{ route('admin.users.show',$user) }}" class="btn btn-outline btn-xs"><i class="fas fa-eye"></i> View</a>
                            <form method="POST" action="{{ route('admin.users.status',$user) }}">
                                @csrf @method('PATCH')
                                @if(($user->status ?? 'active') === 'active')
                                    <input type="hidden" name="status" value="banned">
                                    <button type="submit" class="btn btn-xs" style="background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;" title="Ban">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @else
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="btn btn-xs" style="background:rgba(16,185,129,0.1);color:var(--success);border:1.5px solid rgba(16,185,129,0.3);" title="Activate">
                                        <i class="fas fa-check"></i>
                                    </button>
                                @endif
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No users found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $users->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
