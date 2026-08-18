@extends('admin.layouts.app')

@section('title', 'eGrocery — Coupons')

@section('content')
<div class="container-fluid py-4">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-0">🎟️ Coupons</h4>
      <p class="text-muted small mb-0">Manage discount codes for eGrocery orders</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">
      <i class="bi bi-plus-lg me-1"></i> New Coupon
    </button>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Table --}}
  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Code</th>
              <th>Title</th>
              <th>Type</th>
              <th>Value</th>
              <th>Min Order</th>
              <th>Usage</th>
              <th>Per User</th>
              <th>Schedule</th>
              <th>Categories</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          @forelse($coupons as $coupon)
            <tr>
              <td><code class="fw-bold">{{ $coupon->code }}</code></td>
              <td>{{ $coupon->title }}</td>
              <td>
                @if($coupon->type === 'percentage')
                  <span class="badge bg-info">%</span>
                @else
                  <span class="badge bg-secondary">Fixed</span>
                @endif
              </td>
              <td>
                {{ $coupon->type === 'percentage' ? $coupon->value.'%' : '$'.number_format($coupon->value,2) }}
                @if($coupon->max_discount)
                  <br><small class="text-muted">Max ${{ number_format($coupon->max_discount,2) }}</small>
                @endif
              </td>
              <td>{{ $coupon->min_order_amount ? '$'.number_format($coupon->min_order_amount,2) : '—' }}</td>
              <td>{{ $coupon->used_count ?? 0 }} / {{ $coupon->usage_limit ?? '∞' }}</td>
              <td>{{ $coupon->usage_per_user ?? '∞' }}</td>
              <td class="small">
                @if($coupon->starts_at || $coupon->ends_at)
                  {{ $coupon->starts_at ? \Carbon\Carbon::parse($coupon->starts_at)->format('d M') : '—' }}
                  →
                  {{ $coupon->ends_at ? \Carbon\Carbon::parse($coupon->ends_at)->format('d M') : '—' }}
                @else
                  <span class="text-muted">Always</span>
                @endif
              </td>
              <td class="small">
                @if($coupon->category_ids)
                  @php $catIds = (array)$coupon->category_ids; @endphp
                  {{ $categories->whereIn('id', $catIds)->pluck('name')->implode(', ') ?: 'All' }}
                @else
                  All
                @endif
              </td>
              <td>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input toggle-coupon" type="checkbox" value="{{ $coupon->id }}"
                    {{ $coupon->is_active ? 'checked' : '' }}>
                </div>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" onclick='editCoupon(@json($coupon))'>Edit</button>
                <form method="POST" action="{{ route('admin.egrocery.coupon.destroy', $coupon->id) }}" class="d-inline"
                      onsubmit="return confirm('Delete coupon {{ $coupon->code }}?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="11" class="text-center text-muted py-5">No coupons yet. Create your first one!</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($coupons->hasPages())
      <div class="card-footer">{{ $coupons->links() }}</div>
    @endif
  </div>
</div>

{{-- ── Modal ─────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="couponModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="modalTitle">New Coupon</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="couponForm" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control text-uppercase" name="code" id="f_code" placeholder="SAVE20" required>
              <div class="form-text" id="codeHint"></div>
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="title" id="f_title" placeholder="20% off your order" required>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea class="form-control" name="description" id="f_description" rows="2"></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
              <select class="form-select" name="type" id="f_type" required>
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Amount ($)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Value <span class="text-danger">*</span></label>
              <input type="number" class="form-control" name="value" id="f_value" step="0.01" min="0" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Max Discount ($)</label>
              <input type="number" class="form-control" name="max_discount" id="f_max_discount" step="0.01" min="0" placeholder="Optional cap">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">Min Order Amount ($)</label>
              <input type="number" class="form-control" name="min_order_amount" id="f_min_order_amount" step="0.01" min="0">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Total Usage Limit</label>
              <input type="number" class="form-control" name="usage_limit" id="f_usage_limit" min="1" placeholder="Unlimited">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Per-User Limit</label>
              <input type="number" class="form-control" name="usage_per_user" id="f_usage_per_user" min="1" placeholder="Unlimited">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Starts At</label>
              <input type="datetime-local" class="form-control" name="starts_at" id="f_starts_at">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Ends At</label>
              <input type="datetime-local" class="form-control" name="ends_at" id="f_ends_at">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Category Restriction</label>
              <div class="row row-cols-2 row-cols-md-4 g-2">
                @foreach($categories as $cat)
                  <div class="col">
                    <div class="form-check">
                      <input class="form-check-input cat-check" type="checkbox"
                             name="category_ids[]" value="{{ $cat->id }}"
                             id="cat_{{ $cat->id }}">
                      <label class="form-check-label small" for="cat_{{ $cat->id }}">{{ $cat->name }}</label>
                    </div>
                  </div>
                @endforeach
              </div>
              <div class="form-text">Leave all unchecked to apply to all categories.</div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="f_is_active" value="1" checked>
                <label class="form-check-label fw-semibold" for="f_is_active">Active</label>
              </div>
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-semibold" id="modalSubmit">Create Coupon</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
const modal = new bootstrap.Modal(document.getElementById('couponModal'));
const form  = document.getElementById('couponForm');
let editingId = null;

function openModal() {
  editingId = null;
  form.reset();
  document.getElementById('modalTitle').textContent = 'New Coupon';
  document.getElementById('modalSubmit').textContent = 'Create Coupon';
  document.getElementById('f_code').readOnly = false;
  document.getElementById('codeHint').textContent = '';
  document.getElementById('f_is_active').checked = true;
  form.action = '{{ route("admin.egrocery.coupon.store") }}';
  modal.show();
}

function editCoupon(c) {
  editingId = c.id;
  document.getElementById('modalTitle').textContent  = 'Edit Coupon';
  document.getElementById('modalSubmit').textContent = 'Save Changes';
  document.getElementById('f_code').value      = c.code;
  document.getElementById('f_code').readOnly   = true;
  document.getElementById('codeHint').textContent = 'Code cannot be changed after creation.';
  document.getElementById('f_title').value         = c.title ?? '';
  document.getElementById('f_description').value   = c.description ?? '';
  document.getElementById('f_type').value           = c.type ?? 'percentage';
  document.getElementById('f_value').value          = c.value ?? '';
  document.getElementById('f_max_discount').value   = c.max_discount ?? '';
  document.getElementById('f_min_order_amount').value = c.min_order_amount ?? '';
  document.getElementById('f_usage_limit').value    = c.usage_limit ?? '';
  document.getElementById('f_usage_per_user').value = c.usage_per_user ?? '';
  document.getElementById('f_starts_at').value      = c.starts_at ? c.starts_at.replace(' ','T').slice(0,16) : '';
  document.getElementById('f_ends_at').value        = c.ends_at   ? c.ends_at.replace(' ','T').slice(0,16)   : '';
  document.getElementById('f_is_active').checked    = !!c.is_active;

  // Categories
  document.querySelectorAll('.cat-check').forEach(cb => cb.checked = false);
  const catIds = c.category_ids ? (Array.isArray(c.category_ids) ? c.category_ids : JSON.parse(c.category_ids)) : [];
  catIds.forEach(id => {
    const el = document.getElementById('cat_' + id);
    if (el) el.checked = true;
  });

  form.action = '{{ url("admin/module-data/egrocery/coupons") }}/' + c.id;
  modal.show();
}

// Toggle active via AJAX
document.querySelectorAll('.toggle-coupon').forEach(el => {
  el.addEventListener('change', function () {
    fetch('{{ url("admin/module-data/egrocery/coupons") }}/' + this.value + '/toggle', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
    });
  });
});
</script>
@endpush
@endsection
