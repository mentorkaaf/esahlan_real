@extends('admin.layouts.app')

@section('title', 'eGrocery — Reports')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="container-fluid py-4">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">📊 Reports</h4>
      <p class="text-muted small mb-0">Sales analytics for eGrocery</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="7d"  {{ $period === '7d'  ? 'selected' : '' }}>Last 7 days</option>
          <option value="30d" {{ $period === '30d' ? 'selected' : '' }}>Last 30 days</option>
          <option value="90d" {{ $period === '90d' ? 'selected' : '' }}>Last 90 days</option>
        </select>
      </form>
      <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-download me-1"></i> Export CSV
      </a>
    </div>
  </div>

  {{-- Summary Cards --}}
  @if($stats)
  <div class="row g-3 mb-4">
    @php
      $statCards = [
        ['label'=>'Total Orders',    'value'=> number_format($stats->total_orders),          'icon'=>'📦','color'=>'primary'],
        ['label'=>'Delivered',        'value'=> number_format($stats->delivered),              'icon'=>'✅','color'=>'success'],
        ['label'=>'Cancelled',        'value'=> number_format($stats->cancelled),              'icon'=>'❌','color'=>'danger'],
        ['label'=>'Revenue',          'value'=> '$'.number_format($stats->revenue,2),          'icon'=>'💰','color'=>'warning'],
      ];
    @endphp
    @foreach($statCards as $sc)
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="fs-2">{{ $sc['icon'] }}</div>
          <div>
            <div class="fw-bold fs-5">{{ $sc['value'] }}</div>
            <div class="text-muted small">{{ $sc['label'] }}</div>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @endif

  <div class="row g-4">

    {{-- Sales Chart --}}
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-bold py-3">Revenue by Day</div>
        <div class="card-body">
          <canvas id="salesChart" height="80"></canvas>
        </div>
      </div>
    </div>

    {{-- Top Products --}}
    <div class="col-md-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-bold py-3">🏆 Top Products (by Revenue)</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr><th>#</th><th>Product</th><th>Units</th><th>Revenue</th><th>Margin</th></tr>
              </thead>
              <tbody>
                @forelse($topProducts as $i => $p)
                <tr>
                  <td class="text-muted">{{ $i+1 }}</td>
                  <td class="fw-semibold small">{{ $p->name }}</td>
                  <td>{{ number_format($p->units,1) }}</td>
                  <td>${{ number_format($p->revenue,2) }}</td>
                  <td>
                    @php
                      $cost   = (float)$p->avg_cost * (float)$p->units;
                      $margin = $cost > 0 ? round((($p->revenue - $cost)/$p->revenue)*100,1) : null;
                    @endphp
                    {{ $margin !== null ? $margin.'%' : '—' }}
                  </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No data</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Category Share --}}
    <div class="col-md-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-bold py-3">🗂 Category Revenue Share</div>
        <div class="card-body">
          <canvas id="categoryChart"></canvas>
        </div>
      </div>
    </div>

    {{-- Slot Utilisation --}}
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-bold py-3">🕐 Slot Utilisation</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr><th>Slot</th><th>Capacity</th><th>Booked</th><th>Fill %</th></tr>
              </thead>
              <tbody>
                @forelse($slotUtil as $s)
                @php $pct = $s->capacity > 0 ? round($s->booked/$s->capacity*100) : 0; @endphp
                <tr>
                  <td class="small">{{ $s->label }}</td>
                  <td>{{ $s->capacity }}</td>
                  <td>{{ $s->booked }}</td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="progress flex-grow-1" style="height:6px">
                        <div class="progress-bar {{ $pct>=90?'bg-danger':($pct>=70?'bg-warning':'bg-success') }}"
                             style="width:{{ $pct }}%"></div>
                      </div>
                      <small>{{ $pct }}%</small>
                    </div>
                  </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">No slots</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Cancellation Reasons --}}
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-bold py-3">❌ Cancellation Reasons</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light"><tr><th>Reason</th><th>Count</th></tr></thead>
              <tbody>
                @forelse($cancelReasons as $cr)
                <tr>
                  <td class="small">{{ $cr->reason }}</td>
                  <td><span class="badge bg-secondary">{{ $cr->count }}</span></td>
                </tr>
                @empty
                <tr><td colspan="2" class="text-center text-muted py-3">No cancellations</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

@push('scripts')
<script>
// Sales chart
const salesLabels = @json($salesByDay->pluck('date'));
const salesData   = @json($salesByDay->pluck('revenue'));

new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: {
    labels: salesLabels,
    datasets: [{
      label: 'Revenue ($)',
      data: salesData,
      fill: true,
      tension: 0.4,
      borderColor: '#F97316',
      backgroundColor: 'rgba(249,115,22,0.1)',
      pointBackgroundColor: '#F97316',
      pointRadius: 3,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, ticks: { callback: v => '$'+v } }
    }
  }
});

// Category pie chart
const catLabels = @json($categoryShare->pluck('name'));
const catData   = @json($categoryShare->pluck('revenue'));
const palette   = ['#F97316','#3B82F6','#22C55E','#A855F7','#EAB308','#14B8A6','#EF4444','#8B5CF6'];

if (catLabels.length) {
  new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
      labels: catLabels,
      datasets: [{ data: catData, backgroundColor: palette }]
    },
    options: {
      plugins: {
        legend: { position: 'right', labels: { boxWidth: 12 } },
        tooltip: { callbacks: { label: ctx => '$' + ctx.parsed.toFixed(2) } }
      }
    }
  });
}
</script>
@endpush
@endsection
