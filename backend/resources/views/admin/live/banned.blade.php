@extends('admin.layouts.app')
@section('title', 'Live-Banned Users')

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:13px 20px; border-bottom:1px solid #F9FAFB; font-size:13px; vertical-align:middle; }
.lv-table tr:last-child td { border-bottom:none; }
.btn-sm { padding:5px 14px; border-radius:8px; font-size:12px; font-weight:700; border:none; cursor:pointer; }
</style>
@endpush

@section('content')
<div class="lv-page">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.live.index') }}" style="color:#667085;font-size:20px;"><i class="fas fa-arrow-left"></i></a>
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">ðŸš« Live-Banned Users</h1>
      <p style="color:#667085;font-size:13px;margin:3px 0 0;">Users who are restricted from going live</p>
    </div>
  </div>

  @if(session('success'))
    <div style="background:#ECFDF3;border:1px solid #A9EFC5;color:#027A48;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-weight:600;font-size:13px;">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  <div class="lv-section">
    <table class="lv-table">
      <thead><tr>
        <th>User</th><th>Username</th><th>Email</th><th>Banned Since</th><th>Action</th>
      </tr></thead>
      <tbody>
      @foreach($users as $user)
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#B42318,#7F0000);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px;">
                {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
              </div>
              <div style="font-weight:700;">{{ $user->name }}</div>
            </div>
          </td>
          <td style="color:#667085;">&#64;{{ $user->communityProfile?->username ?? '-' }}</td>
          <td style="color:#667085;">{{ $user->email }}</td>
          <td style="color:#667085;font-size:12px;">{{ $user->updated_at->format('M d Y') }}</td>
          <td>
            <form method="POST" action="{{ route('admin.live.users.toggle-ban', $user->id) }}">
              @csrf
              <button class="btn-sm" style="background:#ECFDF3;color:#027A48;">
                <i class="fas fa-unlock"></i> Unban
              </button>
            </form>
          </td>
        </tr>
      @endforeach
      @if($users->isEmpty())
        <tr><td colspan="5" style="text-align:center;color:#667085;padding:40px;">
          <i class="fas fa-check-circle" style="font-size:28px;opacity:.3;display:block;margin-bottom:10px;"></i>
          No banned users
        </td></tr>
      @endif
      </tbody>
    </table>
    <div style="padding:14px 20px;">{{ $users->links() }}</div>
  </div>
</div>
@endsection

