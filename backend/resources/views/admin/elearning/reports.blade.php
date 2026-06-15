@extends('admin.layouts.app')
@section('title', 'eLearning Reports')

@section('content')
<div class="container-fluid py-4">

  <div class="mb-4">
    <h2 class="fw-bold" style="font-size:22px">eLearning Reports</h2>
    <p class="text-muted mb-0">Platform performance and analytics</p>
  </div>

  {{-- Monthly Enrollments Table --}}
  <div class="card p-4 mb-4">
    <h3 class="fw-bold mb-3" style="font-size:15px">Monthly Enrollments & Revenue (Last 12 Months)</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Month</th>
            <th>Enrollments</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody>
          @forelse($monthlyEnrollments as $row)
          <tr>
            <td>{{ DateTime::createFromFormat('!m', $row->month)->format('F') }} {{ $row->year }}</td>
            <td>{{ number_format($row->count) }}</td>
            <td>${{ number_format($row->revenue, 2) }}</td>
          </tr>
          @empty
          <tr><td colspan="3" class="text-center text-muted py-4">No data yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid-2 mb-4">
    {{-- Top Courses --}}
    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Top 10 Courses by Students</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Course</th>
              <th>Instructor</th>
              <th>Students</th>
              <th>Rating</th>
            </tr>
          </thead>
          <tbody>
            @foreach($topCourses as $i => $c)
            <tr>
              <td class="fw-bold text-muted">{{ $i + 1 }}</td>
              <td class="text-sm fw-bold">{{ Str::limit($c->title, 35) }}</td>
              <td class="text-sm text-muted">{{ $c->instructor?->user?->name ?? '—' }}</td>
              <td>{{ number_format($c->total_students) }}</td>
              <td><i class="fas fa-star text-warning"></i> {{ number_format($c->rating, 1) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Top Instructors --}}
    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Top 10 Instructors by Students</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Instructor</th>
              <th>Students</th>
              <th>Courses</th>
              <th>Rating</th>
            </tr>
          </thead>
          <tbody>
            @foreach($topInstructors as $i => $inst)
            <tr>
              <td class="fw-bold text-muted">{{ $i + 1 }}</td>
              <td class="text-sm fw-bold">{{ $inst->user?->name ?? '—' }}</td>
              <td>{{ number_format($inst->total_students) }}</td>
              <td>{{ $inst->total_courses }}</td>
              <td><i class="fas fa-star text-warning"></i> {{ number_format($inst->rating, 1) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Category Breakdown --}}
  <div class="card p-4">
    <h3 class="fw-bold mb-3" style="font-size:15px">Courses by Category</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th>Published Courses</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categoryBreakdown as $cat)
          <tr>
            <td>{{ $cat->name }}</td>
            <td>{{ $cat->courses_count }}</td>
          </tr>
          @empty
          <tr><td colspan="2" class="text-center text-muted py-4">No data yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
