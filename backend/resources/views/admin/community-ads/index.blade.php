@extends('admin.layouts.app')
@section('title', 'Community Ads & Business Pages')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-bullhorn" style="color:#FF8A00"></i> Community Ads</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>Community Ads</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #FF8A00;"><div style="font-size:22px;font-weight:900;">{{ $stats['total_ads'] }}</div><div style="font-size:11px;color:#8A8A9A;">Total Ads</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #10B981;"><div style="font-size:22px;font-weight:900;">{{ $stats['active_ads'] }}</div><div style="font-size:11px;color:#8A8A9A;">Active</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #F59E0B;"><div style="font-size:22px;font-weight:900;">{{ $stats['pending_ads'] }}</div><div style="font-size:11px;color:#8A8A9A;">Pending</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #3B82F6;"><div style="font-size:22px;font-weight:900;">${{ number_format($stats['total_revenue'], 2) }}</div><div style="font-size:11px;color:#8A8A9A;">Revenue</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #8B5CF6;"><div style="font-size:22px;font-weight:900;">{{ number_format($stats['total_clicks']) }}</div><div style="font-size:11px;color:#8A8A9A;">Clicks</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #EC4899;"><div style="font-size:22px;font-weight:900;">{{ number_format($stats['total_impressions']) }}</div><div style="font-size:11px;color:#8A8A9A;">Impressions</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #14B8A6;"><div style="font-size:22px;font-weight:900;">{{ $stats['total_pages'] }}</div><div style="font-size:11px;color:#8A8A9A;">Biz Pages</div></div>
</div>

@php $tab = request('tab', 'ads'); @endphp
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #f0f1f5;">
    @foreach(['ads'=>'Ads','pricing'=>'Ad Pricing','pages'=>'Business Pages'] as $k=>$v)
    <a href="?tab={{ $k }}" style="padding:10px 20px;font-weight:700;font-size:13px;color:{{ $tab===$k?'#FF8A00':'#8A8A9A' }};border-bottom:{{ $tab===$k?'3px solid #FF8A00':'none' }};text-decoration:none;">{{ $v }}</a>
    @endforeach
</div>

@if($tab === 'ads')
<form action="{{ route('admin.community-ads.bulk-delete') }}" method="POST" id="bulkForm">
    @csrf
    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;gap:8px;">
        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete selected ads?')" style="display:none;" id="bulkDeleteBtn"><i class="fas fa-trash"></i> Delete Selected</button>
    </div>
    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr>
                <th><input type="checkbox" id="selectAll" onchange="document.querySelectorAll('.ad-check').forEach(c=>{c.checked=this.checked});document.getElementById('bulkDeleteBtn').style.display=this.checked?'block':'none'"></th>
                <th>ID</th><th>Media</th><th>Title</th><th>Advertiser</th><th>Page</th><th>Type</th><th>Placement</th><th>Budget</th><th>Spent</th><th>Clicks</th><th>Impr.</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
                @forelse($ads as $ad)
                @php $sc = ['active'=>'badge-success','pending'=>'badge-warning','paused'=>'badge-secondary','completed'=>'badge-info','rejected'=>'badge-danger'][$ad->status] ?? 'badge-secondary'; @endphp
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $ad->id }}" class="ad-check" onchange="document.getElementById('bulkDeleteBtn').style.display=document.querySelectorAll('.ad-check:checked').length?'block':'none'"></td>
                    <td style="font-weight:700;">#{{ $ad->id }}</td>
                    <td>
                        @if($ad->ad_type === 'video')
                            <div onclick="openMediaModal('{{ cdn_url($ad->media_url) }}', 'video')" style="width:50px;height:50px;border-radius:8px;background:#1a1b2e;display:flex;align-items:center;justify-content:center;cursor:pointer;"><i class="fas fa-play" style="color:#fff;font-size:16px;"></i></div>
                        @else
                            <img src="{{ cdn_url($ad->media_url) }}" style="width:50px;height:50px;border-radius:8px;object-fit:cover;cursor:pointer;" onclick="openMediaModal('{{ cdn_url($ad->media_url) }}', 'image')" onerror="this.style.display='none'">
                        @endif
                    </td>
                    <td><strong>{{ $ad->title }}</strong><br><small style="color:#8A8A9A;">{{ Str::limit($ad->description, 40) }}</small></td>
                    <td>{{ $ad->user?->name ?? '—' }}</td>
                    <td>{{ $ad->page?->name ?? '—' }}</td>
                    <td><span class="badge badge-info">{{ $ad->ad_type }}</span></td>
                    <td>{{ $ad->placement }}</td>
                    <td style="font-weight:700;">${{ number_format($ad->budget, 2) }}</td>
                    <td>${{ number_format($ad->spent, 2) }}</td>
                    <td>{{ number_format($ad->clicks) }}</td>
                    <td>{{ number_format($ad->impressions) }}</td>
                    <td><span class="badge {{ $sc }}">{{ ucfirst($ad->status) }}</span></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <form action="{{ route('admin.community-ads.status', $ad->id) }}" method="POST" style="display:flex;gap:4px;">@csrf @method('PATCH')
                                <select name="status" class="form-control" style="width:100px;font-size:11px;">@foreach(['pending','active','paused','completed','rejected'] as $s)<option value="{{ $s }}" {{ $ad->status===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                            </form>
                            <form action="{{ route('admin.community-ads.delete', $ad->id) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf<button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="14" style="text-align:center;padding:30px;color:#8A8A9A;">No ads yet</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($ads->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $ads->withQueryString()->links() }}</div>@endif
    </div>
</form>
@endif

@if($tab === 'pricing')
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Type</th><th>Placement</th><th>Per Click ($)</th><th>Per Impression ($)</th><th>Per 1000 ($)</th><th>Min Budget ($)</th><th>Active</th><th>Actions</th></tr></thead>
    <tbody>
        @foreach($pricing as $p)
        <tr><form action="{{ route('admin.community-ads.pricing', $p->id) }}" method="POST">@csrf @method('PATCH')
            <td style="font-weight:700;">{{ $p->name }}</td><td><span class="badge badge-info">{{ $p->ad_type }}</span></td><td>{{ $p->placement }}</td>
            <td><input type="number" name="cost_per_click" value="{{ $p->cost_per_click }}" step="0.01" class="form-control" style="width:90px;"></td>
            <td><input type="number" name="cost_per_impression" value="{{ $p->cost_per_impression }}" step="0.0001" class="form-control" style="width:90px;"></td>
            <td><input type="number" name="cost_per_1000" value="{{ $p->cost_per_1000 }}" step="0.01" class="form-control" style="width:90px;"></td>
            <td><input type="number" name="min_budget" value="{{ $p->min_budget }}" step="0.01" class="form-control" style="width:90px;"></td>
            <td><label><input type="checkbox" name="is_active" value="1" {{ $p->is_active ? 'checked' : '' }}> On</label></td>
            <td><button class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button></td>
        </form></tr>
        @endforeach
    </tbody>
</table></div></div>
@endif

@if($tab === 'pages')
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Avatar</th><th>Page</th><th>Owner</th><th>Category</th><th>Followers</th><th>Verified</th><th>Active</th><th>Created</th></tr></thead>
    <tbody>
        @forelse($pages as $p)
        <tr>
            <td>@if($p->avatar)<img src="{{ cdn_url($p->avatar) }}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">@else<div style="width:40px;height:40px;border-radius:50%;background:#f0f1f5;display:flex;align-items:center;justify-content:center;"><i class="fas fa-store" style="color:#8A8A9A;"></i></div>@endif</td>
            <td><strong>{{ $p->name }}</strong><br><small style="color:#8A8A9A;">{{ $p->slug }}</small></td>
            <td>{{ $p->user?->name ?? '—' }}</td><td>{{ $p->category ?? '—' }}</td>
            <td style="font-weight:700;">{{ number_format($p->followers_count) }}</td>
            <td><span class="badge {{ $p->is_verified ? 'badge-success' : 'badge-secondary' }}">{{ $p->is_verified ? 'Yes' : 'No' }}</span></td>
            <td><span class="badge {{ $p->is_active ? 'badge-success' : 'badge-danger' }}">{{ $p->is_active ? 'Active' : 'Off' }}</span></td>
            <td style="font-size:12px;color:#8A8A9A;">{{ $p->created_at->format('d M Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;padding:30px;color:#8A8A9A;">No business pages yet</td></tr>
        @endforelse
    </tbody>
</table></div></div>
@endif

{{-- Media Preview Modal --}}
<div id="mediaModal" class="modal-overlay" style="display:none;z-index:9999;" onclick="if(event.target===this)closeMediaModal()">
    <div style="position:relative;max-width:800px;margin:auto;padding-top:60px;">
        <button onclick="closeMediaModal()" style="position:absolute;top:20px;right:0;background:rgba(0,0,0,0.5);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;">&times;</button>
        <div id="mediaContent"></div>
    </div>
</div>

@push('scripts')
<script>
function openMediaModal(url, type) {
    var el = document.getElementById('mediaContent');
    if (type === 'video') {
        el.innerHTML = '<video src="'+url+'" controls autoplay style="width:100%;max-height:80vh;border-radius:12px;background:#000;"></video>';
    } else {
        el.innerHTML = '<img src="'+url+'" style="width:100%;max-height:80vh;border-radius:12px;object-fit:contain;background:#000;">';
    }
    document.getElementById('mediaModal').style.display = 'flex';
}
function closeMediaModal() {
    document.getElementById('mediaModal').style.display = 'none';
    document.getElementById('mediaContent').innerHTML = '';
}
</script>
@endpush
@endsection
