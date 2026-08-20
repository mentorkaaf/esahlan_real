@extends('admin.layouts.app')
@section('title', 'eWholesale — Review Moderation')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Review Moderation</h2>
    <div style="display:flex;gap:8px">
        <form method="GET" style="display:flex;gap:6px">
            <select name="visible" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px">
                <option value="">All Reviews</option>
                <option value="1" @selected(request('visible')==='1')>Visible Only</option>
                <option value="0" @selected(request('visible')==='0')>Hidden Only</option>
            </select>
        </form>
    </div>
</div>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead>
<tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;font-weight:600">Buyer</th>
    <th style="padding:10px 14px;text-align:left;font-weight:600">Supplier</th>
    <th style="padding:10px 14px;text-align:center;font-weight:600">Rating</th>
    <th style="padding:10px 14px;text-align:left;font-weight:600">Comment</th>
    <th style="padding:10px 14px;text-align:center;font-weight:600">Status</th>
    <th style="padding:10px 14px;text-align:left;font-weight:600">Date</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600">Action</th>
</tr>
</thead>
<tbody>
@forelse($reviews as $review)
<tr style="border-bottom:1px solid #f3f4f6;{{ !$review->is_visible ? 'background:#fef2f2' : '' }}">
    <td style="padding:10px 14px;font-weight:500">{{ $review->buyer->user->name ?? '—' }}</td>
    <td style="padding:10px 14px;color:#374151">{{ $review->supplier->display_name ?? '—' }}</td>
    <td style="padding:10px 14px;text-align:center">
        <span style="color:#f59e0b;font-weight:700">{{ $review->rating }}★</span>
    </td>
    <td style="padding:10px 14px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#374151">
        {{ $review->comment ?? '—' }}
    </td>
    <td style="padding:10px 14px;text-align:center">
        @if($review->is_visible)
            <span style="padding:3px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534">Visible</span>
        @else
            <span style="padding:3px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b">Hidden</span>
            @if($review->hidden_reason)
            <div style="font-size:10px;color:#6b7280;margin-top:2px">{{ $review->hidden_reason }}</div>
            @endif
        @endif
    </td>
    <td style="padding:10px 14px;color:#6b7280;font-size:12px">{{ $review->created_at->format('M d, Y') }}</td>
    <td style="padding:10px 14px;text-align:right">
        @if($review->is_visible)
        <button onclick="moderateReview({{ $review->id }}, 'hide')" style="padding:5px 10px;border:1px solid #ef4444;color:#ef4444;background:transparent;border-radius:5px;font-size:12px;cursor:pointer">Hide</button>
        @else
        <button onclick="moderateReview({{ $review->id }}, 'show')" style="padding:5px 10px;border:1px solid #10b981;color:#10b981;background:transparent;border-radius:5px;font-size:12px;cursor:pointer">Show</button>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="7" style="padding:32px;text-align:center;color:#6b7280">No reviews found.</td></tr>
@endforelse
</tbody>
</table>
</div>

<div style="margin-top:16px">{{ $reviews->links() }}</div>
</div>

{{-- Hide modal --}}
<div id="hideModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:10px;padding:24px;width:400px;max-width:90vw">
        <h3 style="margin:0 0 12px;font-size:16px;color:#111">Hide Review</h3>
        <form id="moderateForm" method="POST">
            @csrf
            <input type="hidden" name="action" id="moderateAction">
            <textarea name="reason" id="moderateReason" placeholder="Reason for hiding (required)" required rows="3"
                style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:6px;padding:8px;font-size:13px"></textarea>
            <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
                <button type="button" onclick="closeModal()" style="padding:8px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:8px 14px;background:#ef4444;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function moderateReview(id, action) {
    const modal = document.getElementById('hideModal');
    const form  = document.getElementById('moderateForm');
    form.action = `/admin/module-data/wholesale/reviews/${id}/moderate`;
    document.getElementById('moderateAction').value = action;
    const reasonField = document.getElementById('moderateReason');
    if (action === 'show') {
        reasonField.required = false; reasonField.style.display = 'none';
        form.querySelector('h3') && (form.parentElement.querySelector('h3').textContent = 'Show Review');
        form.submit(); return;
    }
    reasonField.required = true; reasonField.style.display = '';
    modal.style.display = 'flex';
}
function closeModal() {
    document.getElementById('hideModal').style.display = 'none';
}
</script>
@endsection
