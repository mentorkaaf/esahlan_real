{{-- Reusable vendor table partial
     Props:
       $vendorList   — Collection or LengthAwarePaginator of Vendor
       $showCheckbox — bool
--}}
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                @if($showCheckbox)
                <th style="width:36px;">
                    <input type="checkbox" id="check-all" onchange="toggleAll(this)" style="width:16px;height:16px;cursor:pointer;">
                </th>
                @endif
                <th>Vendor</th>
                <th>District</th>
                <th>Rating</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendorList as $vendor)
            <tr id="row-{{ $vendor->id }}">
                @if($showCheckbox)
                <td>
                    <input type="checkbox" name="ids[]" value="{{ $vendor->id }}" class="row-check"
                           onchange="updateBulkBar()" style="width:16px;height:16px;cursor:pointer;">
                </td>
                @endif
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        @if(!empty($vendor->logo_url) && !str_contains($vendor->logo_url ?? '', 'null'))
                            <img src="{{ $vendor->logo_url }}"
                                 style="width:38px;height:38px;border-radius:9px;object-fit:cover;flex-shrink:0;border:1px solid var(--border);">
                        @else
                            <div class="avatar avatar-sm avatar-purple">{{ strtoupper(substr($vendor->name,0,1)) }}</div>
                        @endif
                        <div>
                            <div style="font-weight:700;font-size:13px;">{{ $vendor->name }}</div>
                            <div style="font-size:11px;color:var(--text-muted);">{{ $vendor->phone }}</div>
                        </div>
                    </div>
                </td>
                <td style="font-size:12.5px;color:var(--text-muted);">{{ $vendor->district?->name ?? '—' }}</td>
                <td>
                    <span style="color:#f59e0b;font-size:13px;">★</span>
                    <span style="font-weight:700;font-size:13px;">{{ number_format($vendor->rating ?? 0, 1) }}</span>
                    <span style="font-size:11px;color:var(--text-muted);">({{ $vendor->total_reviews ?? 0 }})</span>
                </td>
                <td>
                    <span class="badge {{ $vendor->is_active ? 'badge-success' : 'badge-danger' }} badge-dot">
                        {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.vendors.toggle-featured', $vendor) }}">
                        @csrf
                        <button type="submit" class="btn btn-xs {{ $vendor->is_featured ? 'btn-primary' : 'btn-outline' }}" title="Toggle Featured">
                            <i class="fas fa-star"></i>
                        </button>
                    </form>
                </td>
                <td style="font-size:12px;color:var(--text-muted);">{{ $vendor->created_at->format('d M Y') }}</td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <a href="{{ route('admin.vendors.show', $vendor) }}" class="btn btn-outline btn-xs">
                            <i class="fas fa-eye"></i> View
                        </a>
                        @if(!$vendor->is_active)
                        <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">
                            @csrf
                            <button class="btn btn-xs btn-success"><i class="fas fa-check"></i> Approve</button>
                        </form>
                        @endif
                        @if($vendor->is_active)
                        <form method="POST" action="{{ route('admin.vendors.reject', $vendor) }}">
                            @csrf
                            <button class="btn btn-xs btn-warning"
                                    onclick="return confirm('Reject {{ addslashes($vendor->name) }}?')">
                                <i class="fas fa-ban"></i> Reject
                            </button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('admin.vendors.destroy', $vendor) }}"
                              onsubmit="return confirm('Delete {{ addslashes($vendor->name) }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $showCheckbox ? 8 : 7 }}">
                    <div class="empty-state">
                        <i class="fas fa-store"></i>
                        <h3>No vendors</h3>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
