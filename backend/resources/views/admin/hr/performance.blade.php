@extends('admin.layouts.app')
@section('title', 'HR Performance Oversight')

@section('content')
<div class="container-fluid">

    <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">HR Performance — Admin View</h4>
        <span class="badge bg-info">Read-only</span>
    </div>

    @foreach($cycles as $cycle)
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-semibold">{{ $cycle->name }}</h6>
                <small class="text-muted">{{ $cycle->period_start->format('d M Y') }} – {{ $cycle->period_end->format('d M Y') }}</small>
            </div>
            <span class="badge {{ match($cycle->status) { 'active'=>'bg-success', 'closed'=>'bg-secondary', default=>'bg-primary' } }}">
                {{ ucfirst($cycle->status) }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-auto"><span class="text-muted" style="font-size:.85rem;">{{ $cycle->goals_count }} goals</span></div>
                <div class="col-auto"><span class="text-muted" style="font-size:.85rem;">{{ $cycle->reviews_count }} reviews</span></div>
            </div>

            @if(isset($deptAverages[$cycle->id]) && $deptAverages[$cycle->id]->count())
            <h6 class="text-muted fw-semibold mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;">Department Averages</h6>
            <div class="d-flex flex-wrap gap-3">
                @foreach($deptAverages[$cycle->id] as $dept => $avg)
                <div class="text-center border rounded px-3 py-2">
                    <div class="fw-bold {{ $avg >= 4 ? 'text-success' : ($avg >= 3 ? 'text-warning' : 'text-danger') }}"
                         style="font-size:1.2rem;">{{ number_format($avg, 2) }}</div>
                    <div class="text-muted" style="font-size:.75rem;">{{ $dept }}</div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-muted" style="font-size:.85rem;">No submitted reviews yet.</p>
            @endif
        </div>
    </div>
    @endforeach

    @if($cycles->isEmpty())
    <div class="text-center py-5 text-muted">No performance cycles created yet.</div>
    @endif

</div>
@endsection
