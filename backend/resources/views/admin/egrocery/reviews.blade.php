@extends('admin.layouts.app')

@section('title', 'eGrocery — Reviews')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">⭐ Reviews</h4>
      <p class="text-muted small mb-0">Moderate customer product reviews</p>
    </div>
    <div class="d-flex gap-2">
      @foreach([null=>'All', 'approved'=>'Approved', 'pending'=>'Pending'] as $val => $label)
      <a href="{{ request()->fullUrlWithQuery(['filter' => $val]) }}"
         class="btn btn-sm {{ request('filter', '') === (string)$val ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ $label }}
      </a>
      @endforeach
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>Customer</th>
              <th>Rating</th>
              <th>Comment</th>
              <th>Status</th>
              <th>Date</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          @forelse($reviews as $review)
            <tr data-id="{{ $review->id }}">
              <td><span class="fw-semibold small">{{ $review->product?->name ?? '—' }}</span></td>
              <td>{{ $review->user?->name ?? 'Unknown' }}</td>
              <td>
                <span class="text-warning">{{ str_repeat('★', (int)$review->rating) }}{{ str_repeat('☆', 5-(int)$review->rating) }}</span>
                <small class="text-muted ms-1">{{ $review->rating }}/5</small>
              </td>
              <td class="small" style="max-width:280px">
                {{ Str::limit($review->comment ?? '', 120) }}
              </td>
              <td>
                <span class="badge status-badge {{ $review->is_approved ? 'bg-success' : 'bg-warning text-dark' }}">
                  {{ $review->is_approved ? 'Approved' : 'Pending' }}
                </span>
              </td>
              <td class="small text-muted">
                {{ $review->created_at ? $review->created_at->format('d M Y') : '—' }}
              </td>
              <td class="text-end">
                <button class="btn btn-sm {{ $review->is_approved ? 'btn-outline-danger' : 'btn-outline-success' }} toggle-review"
                        data-id="{{ $review->id }}" data-approved="{{ $review->is_approved ? '1' : '0' }}">
                  {{ $review->is_approved ? 'Hide' : 'Approve' }}
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No reviews found.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($reviews->hasPages())
      <div class="card-footer">{{ $reviews->links() }}</div>
    @endif
  </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.toggle-review').forEach(btn => {
  btn.addEventListener('click', function () {
    fetch('{{ url("admin/module-data/egrocery/reviews") }}/' + this.dataset.id + '/toggle', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
      const approved = data.is_approved;
      const row   = this.closest('tr');
      const badge = row.querySelector('.status-badge');
      badge.className = 'badge status-badge ' + (approved ? 'bg-success' : 'bg-warning text-dark');
      badge.textContent = approved ? 'Approved' : 'Pending';
      this.textContent = approved ? 'Hide' : 'Approve';
      this.className = 'btn btn-sm ' + (approved ? 'btn-outline-danger' : 'btn-outline-success') + ' toggle-review';
      this.dataset.approved = approved ? '1' : '0';
    });
  });
});
</script>
@endpush
@endsection
