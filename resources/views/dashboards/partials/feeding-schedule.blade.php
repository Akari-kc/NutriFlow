<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="panel-title mb-0">Feeding Schedule</div>
  <div class="d-flex align-items-center gap-2 small fw-bold">
    <a class="btn btn-light btn-sm nl-btn" data-schedule-nav aria-label="Previous feeding schedule day" href="{{ route('dashboard', array_merge(request()->except('schedule_date'), ['schedule_date' => $previousScheduleDate])) }}">&lsaquo;</a>
    <span class="schedule-date-label" data-testid="dashboard-schedule-date">{{ $scheduleDate->format('D, M j') }}</span>
    <a class="btn btn-light btn-sm nl-btn" data-schedule-nav aria-label="Next feeding schedule day" href="{{ route('dashboard', array_merge(request()->except('schedule_date'), ['schedule_date' => $nextScheduleDate])) }}">&rsaquo;</a>
    <a href="{{ route('feeding-schedules.index', ['mode' => 'week', 'date' => $todayScheduleDate]) }}" class="btn btn-primary btn-sm nl-btn">Today</a>
  </div>
</div>
<div class="schedule-grid">
  @forelse($dashboardSchedules as $session)
    <div class="schedule-item">
      <div class="d-flex justify-content-between">
        <div class="fw-bold small">{{ \Carbon\Carbon::parse($session->start_time)->format('h:i A') }}</div>
        <span class="slot-icon">{{ $session->meal_type }}</span>
      </div>
      <div class="fw-bold small mt-1">{{ $session->batch_name }}</div>
      <div class="small text-muted">{{ $session->student_count }} {{ Str::plural('student', $session->student_count) }} &middot; {{ $session->status }}</div>
      @if($session->assigned_aide)
        <div class="small text-muted mt-1">{{ $session->assigned_aide }}</div>
      @endif
    </div>
  @empty
    <div class="schedule-empty" data-testid="dashboard-schedule-empty">No feeding session scheduled for this date.</div>
  @endforelse
</div>
