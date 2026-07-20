@extends('admin.layouts.app')
@section('title', 'Gift Management')

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-hdr { padding:18px 24px; border-bottom:1px solid #F2F4F7; display:flex; align-items:center; justify-content:space-between; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:13px 20px; border-bottom:1px solid #F9FAFB; font-size:13px; vertical-align:middle; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-table tr:hover td { background:#FAFAFA; }
.lv-input { border:1px solid #D0D5DD; border-radius:8px; padding:8px 12px; font-size:13px; width:100%; outline:none; }
.lv-input:focus { border-color:#FF8A00; box-shadow:0 0 0 3px rgba(255,138,0,.1); }
.btn-primary { background:#FF8A00; color:#fff; border:none; border-radius:8px; padding:9px 18px; font-size:13px; font-weight:700; cursor:pointer; }
.btn-sm { padding:5px 12px; border-radius:7px; font-size:12px; font-weight:700; border:none; cursor:pointer; }
.badge-active   { background:#ECFDF3; color:#027A48; font-size:11px; font-weight:700; padding:2px 8px; border-radius:100px; }
.badge-inactive { background:#FEF3F2; color:#B42318; font-size:11px; font-weight:700; padding:2px 8px; border-radius:100px; }
</style>
@endpush

@section('content')
<div class="lv-page">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.live.index') }}" style="color:#667085;font-size:20px;"><i class="fas fa-arrow-left"></i></a>
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">🎁 Gift Management</h1>
      <p style="color:#667085;font-size:13px;margin:3px 0 0;">Create and manage gifts that viewers can send during live streams</p>
    </div>
  </div>

  @if(session('success'))
    <div style="background:#ECFDF3;border:1px solid #A9EFC5;color:#027A48;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-weight:600;font-size:13px;">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

    {{-- Gifts table --}}
    <div class="lv-section">
      <div class="lv-hdr">
        <div style="font-size:14px;font-weight:800;color:#101828;">All Gifts ({{ $gifts->count() }})</div>
      </div>
      <table class="lv-table">
        <thead><tr>
          <th>Sort</th><th>Gift</th><th>Coins</th><th>Sent</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        @foreach($gifts as $gift)
          <tr>
            <td style="color:#667085;">{{ $gift->sort }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:28px;">{{ $gift->emoji }}</span>
                <div>
                  <div style="font-weight:700;color:#101828;">{{ $gift->name }}</div>
                  <div style="font-size:11px;color:#667085;">{{ $gift->animation }}</div>
                </div>
              </div>
            </td>
            <td style="font-weight:800;color:#7F56D9;font-size:15px;">{{ $gift->coins }} 🪙</td>
            <td style="color:#667085;">{{ number_format($gift->times_sent ?? 0) }}×</td>
            <td>
              @if($gift->is_active)
                <span class="badge-active">Active</span>
              @else
                <span class="badge-inactive">Inactive</span>
              @endif
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                {{-- Edit (inline form trigger) --}}
                <button class="btn-sm" style="background:#EFF8FF;color:#1570EF;"
                  onclick="openEditModal({{ $gift->id }}, '{{ addslashes($gift->name) }}', '{{ $gift->emoji }}', '{{ $gift->animation }}', {{ $gift->coins }}, {{ $gift->sort }})">
                  <i class="fas fa-edit"></i>
                </button>
                {{-- Toggle status --}}
                <form method="POST" action="{{ route('admin.live.gifts.toggle', $gift->id) }}">
                  @csrf
                  <button class="btn-sm" style="background:{{ $gift->is_active ? '#FFF4ED' : '#ECFDF3' }};color:{{ $gift->is_active ? '#B54708' : '#027A48' }}">
                    <i class="fas fa-{{ $gift->is_active ? 'eye-slash' : 'eye' }}"></i>
                  </button>
                </form>
                {{-- Delete --}}
                <form method="POST" action="{{ route('admin.live.gifts.destroy', $gift->id) }}"
                      onsubmit="return confirm('Delete {{ $gift->name }}?')">
                  @csrf @method('DELETE')
                  <button class="btn-sm" style="background:#FEE4E2;color:#B42318;">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
        @if($gifts->isEmpty())
          <tr><td colspan="6" style="text-align:center;color:#667085;padding:40px;">No gifts yet. Add one!</td></tr>
        @endif
        </tbody>
      </table>
    </div>

    {{-- Add gift panel --}}
    <div class="lv-section">
      <div class="lv-hdr" style="font-size:14px;font-weight:800;color:#101828;">+ Add New Gift</div>
      <form method="POST" action="{{ route('admin.live.gifts.store') }}" style="padding:20px;">
        @csrf
        <div style="margin-bottom:14px;">
          <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:5px;">NAME</label>
          <input class="lv-input" name="name" placeholder="Rose" required>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:5px;">EMOJI</label>
          <input class="lv-input" name="emoji" placeholder="🌹" maxlength="10" required>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:5px;">ANIMATION TYPE</label>
          <select class="lv-input" name="animation" required>
            <option value="confetti">confetti</option>
            <option value="hearts">hearts</option>
            <option value="stars">stars</option>
            <option value="fireworks">fireworks</option>
            <option value="rain">rain</option>
            <option value="float">float</option>
          </select>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:5px;">COINS COST</label>
          <input class="lv-input" type="number" name="coins" min="1" placeholder="10" required>
        </div>
        <div style="margin-bottom:18px;">
          <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:5px;">SORT ORDER</label>
          <input class="lv-input" type="number" name="sort" min="0" placeholder="0">
        </div>
        <button class="btn-primary" style="width:100%;">Create Gift</button>
      </form>
    </div>
  </div>
</div>

{{-- Edit modal --}}
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:28px;width:380px;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <div style="font-size:16px;font-weight:800;margin-bottom:20px;">Edit Gift</div>
    <form id="editForm" method="POST">
      @csrf @method('PUT')
      <div style="margin-bottom:12px;">
        <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">NAME</label>
        <input class="lv-input" id="editName" name="name" required>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">EMOJI</label>
        <input class="lv-input" id="editEmoji" name="emoji" maxlength="10" required>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">ANIMATION</label>
        <select class="lv-input" id="editAnimation" name="animation">
          <option value="confetti">confetti</option>
          <option value="hearts">hearts</option>
          <option value="stars">stars</option>
          <option value="fireworks">fireworks</option>
          <option value="rain">rain</option>
          <option value="float">float</option>
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">COINS</label>
        <input class="lv-input" id="editCoins" type="number" name="coins" min="1" required>
      </div>
      <div style="margin-bottom:20px;">
        <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">SORT</label>
        <input class="lv-input" id="editSort" type="number" name="sort" min="0">
      </div>
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="document.getElementById('editModal').style.display='none'"
          style="flex:1;padding:9px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;font-weight:700;background:#fff;cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="flex:1;">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, emoji, animation, coins, sort) {
  document.getElementById('editName').value = name;
  document.getElementById('editEmoji').value = emoji;
  document.getElementById('editAnimation').value = animation;
  document.getElementById('editCoins').value = coins;
  document.getElementById('editSort').value = sort;
  document.getElementById('editForm').action = '/admin/live/gifts/' + id;
  const modal = document.getElementById('editModal');
  modal.style.display = 'flex';
}
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
@endsection
