@extends('admin.layouts.app')
@section('title', 'HR Recruitment Oversight')

@section('content')
<div class="container-fluid">

    <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">HR Recruitment — Admin View</h4>
        <span class="badge bg-info">Read-only</span>
    </div>

    {{-- Pipeline funnel overview --}}
    @php
    $stageLabels = ['applied'=>'Applied','screening'=>'Screening','interview'=>'Interview','offer'=>'Offer','hired'=>'Hired','rejected'=>'Rejected'];
    $stageCounts = collect();
    foreach ($stages as $s) {
        $stageCounts[$s] = \App\Models\HR\HrApplicant::where('stage', $s)->count();
    }
    @endphp

    <div class="row g-3 mb-4">
        @foreach($stageLabels as $s => $label)
        <div class="col">
            <div class="card text-center shadow-sm">
                <div class="card-body py-3">
                    <div class="fw-bold" style="font-size:1.4rem;">{{ $stageCounts[$s] ?? 0 }}</div>
                    <div class="text-muted" style="font-size:.8rem;">{{ $label }}</div>
                    @if(isset($avgTimeInStage[$s]))
                    <div class="text-muted" style="font-size:.7rem;">avg {{ $avgTimeInStage[$s] }}d</div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Postings --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0 fw-semibold">Open Postings</h6></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light text-uppercase" style="font-size:.75rem;letter-spacing:.05em;">
                    <tr>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3 text-center">Openings</th>
                        <th class="px-4 py-3 text-center">Total Applicants</th>
                        <th class="px-4 py-3 text-center">Applied</th>
                        <th class="px-4 py-3 text-center">Interview</th>
                        <th class="px-4 py-3 text-center">Offer</th>
                        <th class="px-4 py-3 text-center">Hired</th>
                        <th class="px-4 py-3">Closes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($postings as $p)
                    @php $pf = $funnelData[$p->id] ?? collect(); @endphp
                    <tr>
                        <td class="px-4 py-3 fw-semibold">{{ $p->title }}</td>
                        <td class="px-4 py-3 text-muted">{{ $p->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $p->getTypeLabel() }}</td>
                        <td class="px-4 py-3 text-center">{{ $p->hired_count }}/{{ $p->openings }}</td>
                        <td class="px-4 py-3 text-center fw-semibold">{{ $p->applicants_count }}</td>
                        <td class="px-4 py-3 text-center">{{ $pf->firstWhere('stage','applied')?->cnt ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">{{ $pf->firstWhere('stage','interview')?->cnt ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">{{ $pf->firstWhere('stage','offer')?->cnt ?? 0 }}</td>
                        <td class="px-4 py-3 text-center text-success fw-semibold">{{ $pf->firstWhere('stage','hired')?->cnt ?? 0 }}</td>
                        <td class="px-4 py-3 text-muted" style="font-size:.85rem;">{{ $p->closes_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center py-5 text-muted">No postings.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
