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

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #FF8A00;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['total_ads'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Total Ads</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #10B981;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['active_ads'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Active</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #F59E0B;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['pending_ads'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Pending</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #3B82F6;">
        <div style="font-size:22px;font-weight:900;">${{ number_format($stats['total_revenue'], 2) }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Revenue</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #8B5CF6;">
        <div style="font-size:22px;font-weight:900;">{{ number_format($stats['total_clicks']) }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Clicks</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #EC4899;">
        <div style="font-size:22px;font-weight:900;">{{ number_format($stats['total_impressions']) }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Impressions</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #14B8A6;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['total_pages'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Biz Pages</div>
    </div>
</div>

{{-- Tabs --}}
@php $tab = request('tab', 'ads'); @endphp
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #f0f1f5;">
    @foreach(['ads'=>'Ads','pricing'=>'Ad Pricing','pages'=>'Business Pages'] as $k=>$v)
    <a href="?tab={{ $k }}" style="padding:10px 20px;font-weight:700;font-size:13px;color:{{ $tab===$k?'#FF8A00':'#8A8A9A' }};border-bottom:{{ $tab===$k?'3px solid #FF8A00':'none' }};text-decoration:none;">{{ $v }}</a>
    @endforeach
</div>

{{-- ADS TAB --}}
@if($tab === 'ads')
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Media</th><th>Title</th><th>Advertiser</th><th>Page</th><th>Type</th><th>Placement</th><th>Budget</th><th>Spent</th><th>Clicks</th><th>Impr.</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($ads as $ad)
            @php $sc = ['active'=>'badge-success','pending'=>'badge-warning','paused'=>'badge-secondary','completed'=>'badge-info','rejected'=>'badge-danger','draft'=>'badge-secondary'][$ad->status] ?? 'badge-secondary'; @endphp
            <tr>
                <td style="font-weight:700;">#{{ $ad->id }}</td>
                <td>
                    @if($ad->ad_type === 'video')
                        <div style="width:50px;height:50px;border-radius:8px;background:#f0f1f5;display:flex;align-items:center;justify-content:center;"><i class="fas fa-video" style="color:#8A8A9A;"></i></div>
                    @else
                        <img src="{{ $ad->media_url }}" style="width:50px;height:50px;border-radius:8px;object-fit:cover;" onerror="this.style.display='none'">
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
                    <form action="{{ route('admin.community-ads.status', $ad->id) }}" method="POST" style="display:flex;gap:4px;">
                        @csrf @method('PATCH')
                        <select name="status" class="form-control" style="width:110px;font-size:11px;">
                            @foreach(['pending','active','paused','completed','rejected'] as $s)
                            <option value="{{ $s }}" {{ $ad->status===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="13" style="text-align:center;padding:30px;color:#8A8A9A;">No ads yet</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($ads->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $ads->withQueryString()->links() }}</div>@endif
</div>
@endif

{{-- PRICING TAB --}}
@if($tab === 'pricing')
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Type</th><th>Placement</th><th>Per Click ($)</th><th>Per Impression ($)</th><th>Per 1000 ($)</th><th>Min Budget ($)</th><th>Active</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($pricing as $p)
            <tr>
                <form action="{{ route('admin.community-ads.pricing', $p->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <td style="font-weight:700;">{{ $p->name }}</td>
                    <td><span class="badge badge-info">{{ $p->ad_type }}</span></td>
                    <td>{{ $p->placement }}</td>
                    <td><input type="number" name="cost_per_click" value="{{ $p->cost_per_click }}" step="0.01" class="form-control" style="width:90px;"></td>
                    <td><input type="number" name="cost_per_impression" value="{{ $p->cost_per_impression }}" step="0.0001" class="form-control" style="width:90px;"></td>
                    <td><input type="number" name="cost_per_1000" value="{{ $p->cost_per_1000 }}" step="0.01" class="form-control" style="width:90px;"></td>
                    <td><input type="number" name="min_budget" value="{{ $p->min_budget }}" step="0.01" class="form-control" style="width:90px;"></td>
                    <td><label><input type="checkbox" name="is_active" value="1" {{ $p->is_active ? 'checked' : '' }}> On</label></td>
                    <td><button class="btn btn-sm btn-primary"><i class="fas fa-save"></i> Save</button></td>
                </form>
            </tr>
            @endforeach
        </tbody>
    </table></div>
</div>
@endif

{{-- BUSINESS PAGES TAB --}}
@if($tab === 'pages')
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Avatar</th><th>Page</th><th>Owner</th><th>Category</th><th>Followers</th><th>Verified</th><th>Active</th><th>Created</th></tr></thead>
        <tbody>
            @forelse($pages as $p)
            <tr>
                <td>@if($p->avatar)<img src="{{ $p->avatar }}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">@else<div style="width:40px;height:40px;border-radius:50%;background:#f0f1f5;display:flex;align-items:center;justify-content:center;"><i class="fas fa-store" style="color:#8A8A9A;"></i></div>@endif</td>
                <td><strong>{{ $p->name }}</strong><br><small style="color:#8A8A9A;">{{ $p->slug }}</small></td>
                <td>{{ $p->user?->name ?? '—' }}</td>
                <td>{{ $p->category ?? '—' }}</td>
                <td style="font-weight:700;">{{ number_format($p->followers_count) }}</td>
                <td><span class="badge {{ $p->is_verified ? 'badge-success' : 'badge-secondary' }}">{{ $p->is_verified ? 'Yes' : 'No' }}</span></td>
                <td><span class="badge {{ $p->is_active ? 'badge-success' : 'badge-danger' }}">{{ $p->is_active ? 'Active' : 'Off' }}</span></td>
                <td style="font-size:12px;color:#8A8A9A;">{{ $p->created_at->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:30px;color:#8A8A9A;">No business pages yet</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endif

@endsection
