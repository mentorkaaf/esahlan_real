@extends('admin.layouts.app')
@section('title', 'Community Users')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-user-shield" style="color:#FF8A00"></i> Community Users</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.community.index') }}">Community</a></li><li>Users</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card" style="margin-bottom:16px;padding:12px 16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search users..." style="flex:2">
        <select name="verified" class="form-control" style="flex:1"><option value="">All</option><option value="1" @selected(request('verified')==='1')>Verified</option><option value="0" @selected(request('verified')==='0')>Unverified</option></select>
        <button class="btn btn-primary"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="card">
    <div class="table-wrap"><table>
        <thead><tr>
            <th>User</th>
            <th>Username</th>
            <th>Country</th>
            <th>City</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Interests</th>
            <th>Followers</th>
            <th>Posts</th>
            <th>Verified</th>
            <th>Onboarded</th>
            <th>Actions</th>
        </tr></thead>
        <tbody>
            @forelse($users as $profile)
            @php
                $age = $profile->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->age : null;
                $interests = is_array($profile->interests) ? $profile->interests : (is_string($profile->interests) ? json_decode($profile->interests, true) : []);
            @endphp
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:#f0f2f5;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#FF8A00;">{{ strtoupper(substr($profile->user?->name ?? '?', 0, 1)) }}</div>
                        <div>
                            <div style="font-weight:700;font-size:13px;">{{ $profile->user?->name ?? '—' }}</div>
                            <div style="font-size:11px;color:#8A8A9A;">{{ $profile->user?->phone ?? '' }}</div>
                        </div>
                    </div>
                </td>
                <td style="color:#8A8A9A;">{{ $profile->username ? '@'.$profile->username : '—' }}</td>
                <td style="font-weight:600;">{{ $profile->country ?? '—' }}</td>
                <td>{{ $profile->city ?? '—' }}</td>
                <td>
                    @if($profile->gender === 'male')<span class="badge badge-info">Male</span>
                    @elseif($profile->gender === 'female')<span class="badge badge-warning" style="background:#EC4899;color:#fff;">Female</span>
                    @else <span style="color:#8A8A9A;">—</span>@endif
                </td>
                <td style="font-weight:600;">{{ $age ?? '—' }}</td>
                <td style="max-width:200px;">
                    @if(!empty($interests))
                        @foreach(array_slice($interests, 0, 3) as $int)
                            <span style="display:inline-block;background:#FFF3E0;color:#FF8A00;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;margin:1px;">{{ $int }}</span>
                        @endforeach
                        @if(count($interests) > 3)<span style="font-size:10px;color:#8A8A9A;">+{{ count($interests) - 3 }}</span>@endif
                    @else <span style="color:#8A8A9A;">—</span>@endif
                </td>
                <td style="font-weight:700;">{{ $profile->followers_count }}</td>
                <td>{{ $profile->posts_count }}</td>
                <td><span class="badge {{ $profile->is_verified ? 'badge-success' : 'badge-secondary' }}">{{ $profile->is_verified ? 'Yes' : 'No' }}</span></td>
                <td><span class="badge {{ $profile->onboarding_completed ? 'badge-success' : 'badge-danger' }}">{{ $profile->onboarding_completed ? 'Yes' : 'No' }}</span></td>
                <td>
                    <form action="{{ route('admin.community.users.verify', $profile->id) }}" method="POST" style="display:inline;">@csrf
                        <button class="btn btn-sm {{ $profile->is_verified ? 'btn-warning' : 'btn-success' }}">
                            <i class="fas {{ $profile->is_verified ? 'fa-times' : 'fa-check' }}"></i> {{ $profile->is_verified ? 'Unverify' : 'Verify' }}
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="12" style="text-align:center;padding:30px;color:#8A8A9A;">No users found</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($users->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $users->withQueryString()->links() }}</div>@endif
</div>

@endsection
