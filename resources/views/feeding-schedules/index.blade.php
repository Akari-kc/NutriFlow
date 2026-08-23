@extends('layouts.app')
@section('page-title', 'Feeding Schedule')
@section('content')
@php
  $rangeLabel = $mode === 'month'
      ? $anchor->format('F Y')
      : $start->format('M j').' - '.$end->format('M j, Y');
  $statusClass = fn($status) => match($status) {
      'Completed' => 'completed',
      'Ongoing' => 'ongoing',
      'Cancelled' => 'cancelled',
      default => 'scheduled',
  };
  $mealClass = fn($meal) => strtolower($meal);
@endphp
<style>
  .schedule-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.1rem; }
  .schedule-title { margin:0; color:#082858; font-weight:900; font-size:1.2rem; }
  .schedule-subtitle { color:#7688ad; font-size:.82rem; margin-top:.28rem; }
  .schedule-toolbar { display:grid; grid-template-columns:auto 1fr auto auto auto; gap:.75rem; align-items:center; padding:1rem; margin-bottom:1.1rem; }
  .range-switch { display:inline-flex; background:#e9edf4; border-radius:10px; padding:.18rem; gap:.15rem; }
  .range-switch a { border-radius:8px; padding:.42rem .75rem; color:#526894; font-size:.82rem; font-weight:800; text-decoration:none; }
  .range-switch a.active { background:#fff; color:#082858; box-shadow:0 1px 4px rgba(8,40,88,.08); }
  .nav-range { display:flex; align-items:center; justify-content:center; gap:1.2rem; font-weight:900; color:#082858; }
  .icon-button { width:34px; height:34px; border:0; border-radius:10px; display:grid; place-items:center; background:#eef4fb; color:#0b3b82; text-decoration:none; }
  .schedule-filters { display:flex; justify-content:flex-end; gap:.75rem; align-items:center; }
  .schedule-search { width:160px; height:38px; border:1px solid #dbe3ef; border-radius:9px; padding:0 .8rem; color:#082858; background:#fff; font-size:.84rem; }
  .schedule-select { height:38px; border:1px solid #dbe3ef; border-radius:9px; padding:0 .75rem; color:#082858; background:#fff; font-size:.84rem; }
  .day-group { margin-bottom:1.15rem; }
  .day-divider { display:flex; align-items:center; gap:1rem; color:#7c8bad; font-size:.82rem; margin-bottom:.6rem; }
  .day-pill { border-radius:999px; background:#eef4fb; color:#0b3b82; font-weight:900; padding:.28rem .7rem; }
  .day-line { height:1px; background:#dfe6f1; flex:1; }
  .session-count { color:#7c8bad; font-size:.8rem; }
  .session-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:.85rem; }
  .session-card { border:1px solid #e4e9f2; border-top:3px solid #0b3b82; border-radius:10px; background:#fff; padding:1rem; box-shadow:0 3px 10px rgba(8,40,88,.06); min-height:205px; display:flex; flex-direction:column; }
  .session-card.breakfast { border-top-color:#d89000; }
  .session-card.lunch { border-top-color:#0b3b82; }
  .session-card.snack { border-top-color:#16a34a; }
  .session-card.dinner { border-top-color:#6d5bd0; }
  .session-top { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.65rem; }
  .meal-pill, .status-pill { border-radius:999px; padding:.25rem .6rem; font-size:.75rem; font-weight:900; }
  .meal-pill.breakfast { background:#fff4d9; color:#c78200; }
  .meal-pill.lunch { background:#e7f0ff; color:#0b3b82; }
  .meal-pill.snack { background:#e7f8ee; color:#057243; }
  .meal-pill.dinner { background:#efecff; color:#5140b8; }
  .status-pill.completed { background:#e7f8ee; color:#009b4d; }
  .status-pill.ongoing { background:#fff4d9; color:#b77900; }
  .status-pill.scheduled { background:#eef4fb; color:#0b3b82; }
  .status-pill.cancelled { background:#ffe8e8; color:#dc2626; }
  .session-name { color:#082858; font-weight:900; font-size:1rem; }
  .session-grade { color:#7688ad; font-size:.82rem; margin:.2rem 0 .65rem; }
  .session-meta { display:grid; gap:.35rem; color:#42567e; font-size:.82rem; }
  .session-meta div { display:flex; align-items:center; gap:.45rem; }
  .allergy-notice { margin-top:.75rem; border:1px solid #ffc9c4; background:#fff5f4; color:#b42318; border-radius:9px; padding:.65rem .75rem; font-size:.8rem; font-weight:800; }
  .allergy-notice ul { margin:.35rem 0 0; padding-left:1rem; font-weight:700; }
  .session-actions { margin-top:auto; display:flex; gap:.55rem; padding-top:.8rem; }
  .link-action { border:0; background:transparent; color:#0b3b82; font-weight:900; font-size:.82rem; padding:0; }
  .link-action.warn { color:#c78200; }
  .empty-schedule { padding:2rem; text-align:center; color:#7688ad; }
  .schedule-modal-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.85rem; }
  .schedule-modal-grid .full { grid-column:1 / -1; }
  .schedule-modal .modal-dialog { height:calc(100vh - 1.5rem); margin-top:.75rem; margin-bottom:.75rem; }
  .schedule-modal .modal-content { max-height:100%; height:100%; overflow:hidden; }
  .schedule-modal form { display:flex; flex-direction:column; min-height:0; height:100%; }
  .schedule-modal .modal-header, .schedule-modal .modal-footer { flex:0 0 auto; }
  .schedule-modal .modal-body { flex:1 1 auto; min-height:0; overflow-y:auto; padding-bottom:1.25rem; }
  .student-picker-toolbar { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:.55rem; margin-bottom:.65rem; }
  .student-picker-actions { display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; margin-bottom:.55rem; }
  .student-picker-list { border:1px solid #dbe3ef; border-radius:10px; max-height:180px; overflow:auto; background:#fff; }
  .student-picker-row { display:flex; justify-content:space-between; align-items:center; gap:.75rem; padding:.65rem .75rem; border-bottom:1px solid #eef2f7; }
  .student-picker-row:last-child { border-bottom:0; }
  .student-picker-name { color:#082858; font-weight:800; font-size:.9rem; }
  .student-picker-meta { color:#7688ad; font-size:.78rem; }
  .student-picker-count { color:#526894; font-size:.8rem; margin-top:.45rem; }
  .menu-picker-list { border:1px solid #dbe3ef; border-radius:10px; max-height:145px; overflow:auto; background:#fff; }
  .menu-picker-row { display:flex; justify-content:space-between; align-items:center; gap:.75rem; padding:.62rem .75rem; border-bottom:1px solid #eef2f7; color:#082858; font-weight:800; }
  .menu-picker-row:last-child { border-bottom:0; }
  .cohort-recommendation-panel { border:1px solid #cad9ec; background:#f8fbff; border-radius:12px; padding:1rem; }
  .cohort-recommendation-head { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; flex-wrap:wrap; }
  .cohort-recommendation-title { color:#082858; font-weight:900; font-size:.95rem; }
  .cohort-recommendation-copy { color:#657795; font-size:.78rem; margin-top:.25rem; max-width:620px; }
  .cohort-prototype-badge { border:1px solid #e3b341; background:#fff8df; color:#8b6100; border-radius:999px; padding:.2rem .55rem; font-size:.68rem; font-weight:900; }
  .cohort-recommendation-state { margin-top:.8rem; border:1px dashed #c8d5e6; border-radius:9px; padding:.7rem; color:#526894; font-size:.8rem; }
  .cohort-recommendation-state.error { border-style:solid; border-color:#efbbb3; background:#fff5f4; color:#a83a2a; }
  .cohort-summary-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
  .cohort-summary-item { border:1px solid #dce5f0; border-radius:9px; background:#fff; padding:.65rem; }
  .cohort-summary-label { color:#7688ad; font-size:.68rem; }
  .cohort-summary-value { color:#082858; font-size:1rem; font-weight:900; margin-top:.15rem; }
  .cohort-section-label { color:#082858; font-size:.8rem; font-weight:900; margin-bottom:.45rem; }
  .cohort-priority-list { display:flex; flex-wrap:wrap; gap:.4rem; }
  .cohort-priority-chip { background:#edf3fb; color:#31527c; border-radius:7px; padding:.3rem .5rem; font-size:.72rem; font-weight:800; }
  .cohort-meal-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.55rem; }
  .cohort-meal-card { border:1px solid #dce5f0; border-radius:10px; background:#fff; padding:.75rem; }
  .cohort-meal-name { color:#082858; font-weight:900; }
  .cohort-meal-meta { color:#687b9a; font-size:.72rem; margin:.25rem 0 .55rem; }
  .cohort-match { display:inline-block; background:#e8f6ef; color:#0b6b47; border-radius:999px; padding:.18rem .45rem; font-size:.67rem; font-weight:900; }
  .cohort-check-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.35rem .8rem; }
  .cohort-check { display:flex; justify-content:space-between; gap:.5rem; border-bottom:1px solid #e4eaf2; padding:.35rem 0; font-size:.72rem; }
  .cohort-check strong { text-align:right; }
  .cohort-prototype-notice { border-left:3px solid #e0a21c; background:#fff9ea; color:#6f5a25; padding:.55rem .7rem; font-size:.72rem; }
  .cohort-budget { border-top:1px solid #dbe4f0; padding-top:.85rem; }
  .cohort-budget-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; }
  .cohort-budget-result { min-height:31px; display:flex; align-items:center; border:1px solid #dbe3ef; border-radius:7px; background:#fff; padding:.35rem .55rem; color:#526894; font-size:.75rem; font-weight:800; }
  body.dark .cohort-recommendation-panel { background:var(--nf-surface-raised); border-color:var(--nf-line); }
  body.dark .cohort-recommendation-panel :where(.cohort-summary-item,.cohort-meal-card,.cohort-budget-result) { background:var(--nf-control-bg); border-color:var(--nf-line); }
  body.dark .cohort-prototype-badge, body.dark .cohort-prototype-notice { background:#342a18; color:#ffd166; border-color:#7d6223; }
  body.dark .cohort-priority-chip { background:#1c3048; color:#b9d7f7; }
  @media (max-width:1180px){ .session-grid{grid-template-columns:repeat(2,minmax(0,1fr));} .schedule-toolbar{grid-template-columns:1fr;} .schedule-filters{justify-content:flex-start; flex-wrap:wrap;} }
  @media (max-width:900px){ .cohort-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));} .cohort-budget-grid{grid-template-columns:1fr;} }
  @media (max-width:720px){ .schedule-head{display:block;} .schedule-head .btn{width:100%; margin-top:.8rem;} .session-grid,.cohort-meal-grid,.cohort-check-list,.cohort-summary-grid{grid-template-columns:1fr;} .schedule-modal-grid{grid-template-columns:1fr;} .schedule-modal-grid .full{grid-column:auto;} .student-picker-toolbar{grid-template-columns:1fr;} }
</style>

<div class="schedule-head">
  <div>
    <h1 class="schedule-title">Feeding Schedule</h1>
    <div class="schedule-subtitle">Click any session to view details</div>
  </div>
  <button class="btn btn-primary nl-btn d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
    <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
    Add Session
  </button>
</div>

@if($errors->any())
  <div class="alert alert-danger" role="alert">
    <div class="fw-bold">The feeding session was not added.</div>
    <ul class="mb-0 mt-1">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form class="nl-card schedule-toolbar" method="GET" id="scheduleFilters">
  <div class="range-switch">
    <a class="{{ $mode === 'week' ? 'active' : '' }}" href="{{ route('feeding-schedules.index', array_merge(request()->except(['mode']), ['mode' => 'week'])) }}">Week</a>
    <a class="{{ $mode === 'month' ? 'active' : '' }}" href="{{ route('feeding-schedules.index', array_merge(request()->except(['mode']), ['mode' => 'month'])) }}">Month</a>
  </div>
  <div class="nav-range">
    <a class="icon-button" href="{{ route('feeding-schedules.index', array_merge(request()->except(['date']), ['mode' => $mode, 'date' => $previousDate])) }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
    <span>{{ $rangeLabel }}</span>
    <a class="icon-button" href="{{ route('feeding-schedules.index', array_merge(request()->except(['date']), ['mode' => $mode, 'date' => $nextDate])) }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
  </div>
  <input type="hidden" name="mode" value="{{ $mode }}">
  <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">
  <div class="schedule-filters">
    <input class="schedule-search" name="q" value="{{ $filters['q'] }}" placeholder="Search...">
    <select class="schedule-select" name="meal_type" onchange="this.form.submit()">
      <option value="All">All meals</option>
      @foreach($mealTypes as $type)
        <option value="{{ $type }}" @selected($filters['meal_type'] === $type)>{{ $type }}</option>
      @endforeach
    </select>
    <select class="schedule-select" name="status" onchange="this.form.submit()">
      <option value="All">All statuses</option>
      @foreach($statuses as $status)
        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
      @endforeach
    </select>
    <button class="btn btn-outline-primary nl-btn" type="submit">Apply</button>
  </div>
</form>

@forelse($groupedSessions as $date => $daySessions)
  @php
    $day = \Carbon\Carbon::parse($date, 'Asia/Manila');
  @endphp
  <section class="day-group">
    <div class="day-divider">
      <span class="day-pill">{{ $day->isToday() ? 'Today' : $day->format('D, M j') }}</span>
      <span class="day-line"></span>
      <span class="session-count">{{ $daySessions->count() }} {{ Str::plural('session', $daySessions->count()) }}</span>
    </div>
    <div class="session-grid">
      @foreach($daySessions as $session)
        @php
          $participantNames = $session->participantNames();
          $foodNames = $session->selectedFoodNames();
          $participantPreview = count($participantNames)
              ? implode(', ', array_slice($participantNames, 0, 3)).(count($participantNames) > 3 ? ' +'.(count($participantNames) - 3).' more' : '')
              : ($session->batch_name ?: 'No students selected');
          $allergyWarnings = ($sessionAllergyWarnings ?? collect())->get($session->id, collect());
        @endphp
        <article class="session-card {{ $mealClass($session->meal_type) }}">
          <div class="session-top">
            <span class="meal-pill {{ $mealClass($session->meal_type) }}">{{ $session->meal_type }}</span>
            <span class="status-pill {{ $statusClass($session->status) }}">{{ $session->status }}</span>
          </div>
          <div class="session-name">{{ Str::limit($session->batch_name ?: $participantPreview, 64) }}</div>
          <div class="session-grade">{{ $session->student_count }} participating {{ Str::plural('student', $session->student_count) }}</div>
          <div class="session-meta">
            <div><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ \Carbon\Carbon::parse($session->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($session->end_time)->format('h:i A') }}</div>
            <div><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/></svg>{{ $session->assigned_aide ?: 'Unassigned' }}</div>
            <div><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v18"/><path d="M10 3v6a4 4 0 0 1-8 0V3"/><path d="M18 3v18"/></svg>{{ count($foodNames) ? implode(', ', $foodNames) : ($session->menu_items ?: 'No menu yet') }}</div>
          </div>
          @if($allergyWarnings->count())
            <div class="allergy-notice">
              Allergy warning
              <ul>
                @foreach($allergyWarnings->take(2) as $warning)
                  <li>{{ $warning['student'] }}: {{ $warning['food'] }} may contain {{ $warning['allergies'] }}</li>
                @endforeach
                @if($allergyWarnings->count() > 2)
                  <li>{{ $allergyWarnings->count() - 2 }} more warning{{ $allergyWarnings->count() - 2 === 1 ? '' : 's' }}</li>
                @endif
              </ul>
            </div>
          @endif
          <div class="session-actions">
            <button class="link-action {{ $session->meal_type === 'Breakfast' ? 'warn' : '' }}" type="button" data-bs-toggle="modal" data-bs-target="#sessionModal{{ $session->id }}">View details -></button>
          </div>
        </article>
      @endforeach
    </div>
  </section>
@empty
  <div class="nl-card empty-schedule">No feeding sessions match the current filters.</div>
@endforelse

@include('feeding-schedules.partials.form-modal', ['modalId' => 'addScheduleModal', 'title' => 'Add Session', 'action' => route('feeding-schedules.store'), 'method' => 'POST', 'session' => null])

@foreach($sessions as $session)
  @include('feeding-schedules.partials.form-modal', ['modalId' => 'sessionModal'.$session->id, 'title' => 'Session Details', 'action' => route('feeding-schedules.update', $session), 'method' => 'PATCH', 'session' => $session])
@endforeach

@if($errors->any())
@php
  $failedScheduleContext = (string) old('_schedule_form_mode', 'add');
  $failedScheduleModalId = Str::startsWith($failedScheduleContext, 'edit:')
      ? 'sessionModal'.(int) Str::after($failedScheduleContext, 'edit:')
      : 'addScheduleModal';
@endphp
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById(@json($failedScheduleModalId));
    if (modalElement && window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
  });
</script>
@endif

<script>
  document.getElementById('scheduleFilters')?.addEventListener('submit', function(){
    this.querySelectorAll('input, select').forEach(function(control){
      if (!control.value || control.value === 'All') control.disabled = true;
    });
  });
</script>
@endsection
