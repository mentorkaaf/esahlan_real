@extends('vendor.layouts.app')
@section('title', 'RFQs / Inquiries')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-invoice" style="color:var(--brand)"></i> RFQs / Inquiries</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eWholesale</li><li>RFQs</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

{{-- Tab Filter --}}
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="{{ route('vendor.wholesale.rfqs', ['tab' => 'open']) }}"
       class="btn btn-sm {{ $tab === 'open' ? 'btn-primary' : 'btn-outline' }}">
        Open RFQs (Marketplace)
    </a>
    <a href="{{ route('vendor.wholesale.rfqs', ['tab' => 'my_quotes']) }}"
       class="btn btn-sm {{ $tab === 'my_quotes' ? 'btn-primary' : 'btn-outline' }}">
        My Quotes
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Buyer</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rfqs as $rfq)
                <tr>
                    <td>{{ $rfq->id }}</td>
                    <td>{{ $rfq->title ?? 'RFQ #' . $rfq->id }}</td>
                    <td>{{ $rfq->buyer?->business_name ?? 'N/A' }}</td>
                    <td>{{ $rfq->category?->name ?? '-' }}</td>
                    <td>{{ $rfq->qty }} {{ $rfq->unit }}</td>
                    <td>{{ $rfq->target_price ? '$' . number_format($rfq->target_price, 2) : '-' }}</td>
                    <td>
                        <span class="badge badge-{{ match($rfq->status) {
                            'pending'  => 'yellow',
                            'quoted'   => 'blue',
                            'accepted' => 'green',
                            'rejected' => 'red',
                            'expired'  => 'gray',
                            default    => 'gray'
                        } }}">{{ ucfirst($rfq->status) }}</span>
                    </td>
                    <td>{{ $rfq->created_at->format('M d, Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted">No RFQs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rfqs->hasPages())
    <div class="card-footer">{{ $rfqs->withQueryString()->links() }}</div>
    @endif
</div>

<div class="alert" style="margin-top:16px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:14px 18px;font-size:13px;color:var(--text-muted);">
    <i class="fas fa-info-circle" style="color:var(--brand);margin-right:6px;"></i>
    To submit a quote on an open RFQ, contact your account manager or use the admin portal. Buyers will receive notifications automatically.
</div>

@endsection
