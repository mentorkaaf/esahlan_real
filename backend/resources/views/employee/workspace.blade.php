@extends('employee.layouts.app')
@section('title', $module->name . ' Workspace')

@section('content')
<div style="display:flex;flex-direction:column;gap:24px;">

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:48px;height:48px;border-radius:14px;background:{{ $module->color ?? '#1B1444' }};display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-layer-group" style="color:#fff;font-size:20px;"></i>
      </div>
      <div>
        <h1 style="font-size:20px;font-weight:800;color:#111827;">{{ $module->name }} Workspace</h1>
        <p style="font-size:13px;color:#6b7280;margin-top:2px;">
          {{ $assignment->modulePosition?->name ?? $assignment->moduleDepartment?->name ?? 'Employee' }}
          · <span class="badge badge-{{ match($assignment->assignment_type){ 'primary'=>'orange','secondary'=>'blue','temporary'=>'yellow',default=>'gray' } }}">{{ $assignment->assignment_type_label }}</span>
          @if($composite > 0)
            · Performance: <strong style="color:{{ $composite>=90?'#16a34a':($composite>=75?'#2563eb':($composite>=60?'#d97706':'#dc2626')) }}">{{ $composite }}%</strong>
          @endif
        </p>
      </div>
    </div>
    <a href="{{ route('employee.performance') }}" class="btn btn-outline btn-sm">
      <i class="fas fa-chart-line"></i> My Performance
    </a>
  </div>

  {{-- Stats row --}}
  @if(isset($workData['stats']) && count($workData['stats']))
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;">
    @foreach($workData['stats'] as $label => $count)
    <div class="card" style="padding:16px;text-align:center;">
      <div style="font-size:28px;font-weight:900;color:#111827;">{{ number_format($count) }}</div>
      <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-top:4px;">
        {{ ucfirst(str_replace('_',' ',$label)) }}
      </div>
    </div>
    @endforeach
  </div>
  @endif

  @if(isset($workData['notice']))
  <div class="flash flash-warning">
    <i class="fas fa-info-circle"></i>
    <div>
      <strong>Module data ma jiro weli.</strong>
      <div style="font-size:12px;margin-top:2px;opacity:.8;">{{ $workData['notice'] }}</div>
    </div>
  </div>
  @endif

  {{-- Work queue --}}
  <div class="card">
    <div class="card-header">
      <span class="card-title">
        <i class="fas fa-list-ul" style="color:var(--brand);margin-right:6px"></i>
        Shaqada Joogta ah
      </span>
      <span style="font-size:12px;color:#6b7280;">
        {{ is_countable($workData['orders']) ? count($workData['orders']) : 0 }} item
      </span>
    </div>

    @php $orders = $workData['orders'] ?? collect(); @endphp

    @if(count($orders) > 0)
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
            @php
              // Determine columns based on module
              $cols = match($module->slug) {
                'efood','egrocery','eshop','elaundry','ewholesale','edata'
                  => ['#', 'Xaalad', 'Lacag', 'Taariikhda'],
                'eparcel','emoving'
                  => ['#', 'Nooca', 'Xaalad', 'Meesha'],
                'erent','ehealth','elearning'
                  => ['#', 'Xaalad', 'Taariikhda', 'Macmiil'],
                'eticket'
                  => ['#', 'Cinwaanka', 'Xaalad', 'Muddo'],
                'eexchange'
                  => ['#', 'Nooca', 'Lacagta', 'Xaalad'],
                default => ['#', 'Xaalad', 'Taariikhda'],
              };
            @endphp
            @foreach($cols as $col)
            <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;">
              {{ $col }}
            </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($orders as $i => $order)
          @php
            $order = (array) $order;
            $id     = $order['id'] ?? ($i+1);
            $status = $order['status'] ?? '—';
            $statusColor = match(strtolower($status)) {
              'pending'    => 'badge-yellow',
              'confirmed','active','in_progress','processing','picked_up','washing','collecting'
                           => 'badge-blue',
              'ready','packed','completed','delivered'
                           => 'badge-green',
              'cancelled','failed','rejected'
                           => 'badge-red',
              default      => 'badge-gray',
            };
          @endphp
          <tr style="border-bottom:1px solid #f3f4f6;transition:background .1s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
            <td style="padding:12px 16px;color:#9ca3af;font-family:monospace;font-size:12px;">#{{ $id }}</td>
            <td style="padding:12px 16px;">
              <span class="badge {{ $statusColor }}">{{ $status }}</span>
            </td>
            @if(isset($order['total_amount']) || isset($order['amount']))
            <td style="padding:12px 16px;font-weight:600;">
              ${{ number_format($order['total_amount'] ?? $order['amount'] ?? 0, 2) }}
            </td>
            @elseif(isset($order['subject']) || isset($order['title']) || isset($order['name']))
            <td style="padding:12px 16px;color:#374151;">
              {{ Str::limit($order['subject'] ?? $order['title'] ?? $order['name'] ?? '—', 40) }}
            </td>
            @else
            <td style="padding:12px 16px;color:#6b7280;">—</td>
            @endif
            <td style="padding:12px 16px;color:#6b7280;font-size:12px;">
              @php
                $dateField = $order['created_at'] ?? $order['appointment_date'] ?? $order['booking_date'] ?? null;
              @endphp
              {{ $dateField ? \Carbon\Carbon::parse($dateField)->format('d M H:i') : '—' }}
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div style="padding:48px;text-align:center;color:#9ca3af;">
      <i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:12px;color:#86efac;"></i>
      <strong style="color:#6b7280;">Shaqo cusub ma jirto!</strong><br>
      <span style="font-size:13px;">Queue-ku waa banaan yahay.</span>
    </div>
    @endif
  </div>

  {{-- Assignment details --}}
  <div class="card">
    <div class="card-header">
      <span class="card-title">Xilka Diiwaangelinta</span>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;font-size:13px;">
        @php
          $details = [
            ['Qaybta Module', $assignment->moduleDepartment?->name ?? '—'],
            ['Xilka Module',  $assignment->modulePosition?->name ?? '—'],
            ['Doorka',        $assignment->moduleRole?->name ?? '—'],
            ['Nooca Xilka',   $assignment->assignment_type_label],
            ['Heerka Galadhiga', $assignment->access_level_label],
            ['Taariikhda Bilawga', $assignment->assigned_at?->format('d M Y') ?? '—'],
            ['Dhamaadka', $assignment->planned_end_date?->format('d M Y') ?? 'Permanent'],
          ];
        @endphp
        @foreach($details as [$label, $val])
        <div style="display:flex;flex-direction:column;gap:4px;">
          <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">{{ $label }}</span>
          <span style="font-weight:600;color:#111827;">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>

</div>
@endsection
