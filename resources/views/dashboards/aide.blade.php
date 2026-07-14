@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')
<style>
  .dash-hello h1 { font-size: 1.18rem; font-weight: 800; margin: 0; color: #082858; }
  .dash-hello p { color: #526894; margin: .35rem 0 1.35rem; font-size: .92rem; }
  .metric-card { padding: 1.25rem; min-height: 124px; display: flex; justify-content: space-between; gap: 1rem; }
  .metric-label { font-size: .82rem; color: #3d5078; }
  .metric-value { font-size: 1.72rem; font-weight: 800; color: #082858; margin-top: 1.2rem; line-height: 1; }
  .metric-sub { font-size: .78rem; color: #7687aa; margin-top: .55rem; }
  .metric-icon { width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; flex: 0 0 auto; }
  .icon-blue { background: #edf4ff; color: #0b3b82; }
  .icon-amber { background: #fff6dd; color: #d89000; }
  .icon-green { background: #e8f6ef; color: #138a55; }
  .panel { padding: 1.25rem; }
  .panel-title { font-weight: 800; color: #082858; margin-bottom: .25rem; }
  .panel-subtitle { color: #7687aa; font-size: .78rem; margin-bottom: 1rem; }
  .bmi-badge { background: #fbf1dc; color: #c78200; border-radius: 9px; padding: .35rem .65rem; font-size: .78rem; font-weight: 800; }
  .bmi-period-tabs { display: inline-flex; gap: .15rem; padding: .18rem; background: #e9edf4; border-radius: 9px; }
  .bmi-period-tabs a { color: #526894; border-radius: 8px; padding: .36rem .65rem; font-size: .76rem; font-weight: 800; text-decoration: none; }
  .bmi-period-tabs a.active { background: #fff; color: #082858; box-shadow: 0 1px 4px rgba(8,40,88,.08); }
  .risk-card { border-radius: 10px; padding: 1.1rem; display: flex; align-items: center; gap: .85rem; border: 1px solid; }
  .risk-severe { background: #fff5f4; border-color: #ffc9c4; }
  .risk-moderate { background: #fff8e7; border-color: #f2d391; }
  .risk-dot { width: 11px; height: 11px; border-radius: 50%; flex: 0 0 auto; }
  .risk-number { font-size: 1.35rem; font-weight: 800; color: #082858; line-height: 1; }
  .schedule-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; max-height: 250px; overflow: hidden; }
  .schedule-item { background: #f7f8fb; border: 1px solid #e3e8f2; border-radius: 10px; padding: .95rem; min-height: 96px; }
  .slot-icon { color: #d89000; font-weight: 800; font-size: .78rem; }
  .dash-arrow { color: #98a6bf; font-weight: 800; }
  .suggestion-table th { color: #7c8bad; font-size: .78rem; font-weight: 600; }
  .suggestion-table td { color: #082858; font-size: .88rem; padding: .78rem .35rem; }
  @media (max-width: 1100px) {
    .schedule-grid { grid-template-columns: 1fr; max-height: none; }
  }
</style>

<div class="dash-hello">
  <h1>Good morning, {{ strtok(auth()->user()?->name ?? 'Isabel', ' ') }}</h1>
  <p>{{ $schoolName ?? 'School Nutrition Program' }} &middot; Nutrition overview for today.</p>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="nl-card metric-card">
      <div>
        <div class="metric-label">Total Students</div>
        <div class="metric-value">{{ number_format($kpis['total_students'] ?? 0) }}</div>
        <div class="metric-sub">Enrolled in program</div>
      </div>
      <div class="metric-icon icon-blue"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="nl-card metric-card">
      <div>
        <div class="metric-label">At Risk</div>
        <div class="metric-value">{{ number_format($kpis['at_risk_count'] ?? 0) }}</div>
        <div class="metric-sub">{{ $kpis['severe_count'] ?? 0 }} severe &middot; {{ $kpis['moderate_count'] ?? 0 }} moderate</div>
      </div>
      <div class="metric-icon icon-amber"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 4.3 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="nl-card metric-card">
      <div>
        <div class="metric-label">Meals Today</div>
        <div class="metric-value">{{ number_format($kpis['meals_today'] ?? 0) }}</div>
        <div class="metric-sub">Served across all batches</div>
      </div>
      <div class="metric-icon icon-green"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v18"/><path d="M10 3v6a4 4 0 0 1-8 0V3"/><path d="M18 3v18"/><path d="M18 3c3 2 4 5 4 8h-4"/></svg></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <div class="nl-card panel h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="panel-title">BMI Trend Analysis</div>
          <div class="panel-subtitle">All records &middot; {{ number_format($bmiTotalRecords ?? 0) }} BMI entries &middot; Averaged by {{ $bmiPeriod ?? 'month' }}</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <div class="bmi-period-tabs">
            @foreach(['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'] as $periodValue => $periodLabel)
              <a class="{{ ($bmiPeriod ?? 'month') === $periodValue ? 'active' : '' }}" href="{{ route('dashboard', array_merge(request()->except('bmi_period'), ['bmi_period' => $periodValue])) }}">{{ $periodLabel }}</a>
            @endforeach
          </div>
          <div class="bmi-badge">{{ $bmiDelta >= 0 ? '+' : '' }}{{ number_format($bmiDelta, 1) }} BMI</div>
        </div>
      </div>
      <div style="height: 260px;">
        <canvas id="bmiChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="nl-card panel h-100">
      <div class="panel-title mb-3">Student Risk Alerts</div>
      <div class="d-grid gap-3">
        <a href="{{ route('students.index', ['risk' => 'Severe']) }}" class="risk-card risk-severe text-decoration-none">
          <span class="risk-dot bg-danger"></span>
          <span class="flex-grow-1">
            <span class="risk-number">{{ $kpis['severe_count'] ?? 0 }}</span>
            <span class="d-block fw-bold small text-danger-emphasis mt-1">Severely Undernourished</span>
            <span class="d-block small text-muted">Immediate attention needed</span>
          </span>
          <span class="dash-arrow"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
        </a>
        <a href="{{ route('students.index', ['risk' => 'Moderate']) }}" class="risk-card risk-moderate text-decoration-none">
          <span class="risk-dot bg-warning"></span>
          <span class="flex-grow-1">
            <span class="risk-number">{{ $kpis['moderate_count'] ?? 0 }}</span>
            <span class="d-block fw-bold small text-warning-emphasis mt-1">Undernourished</span>
            <span class="d-block small text-muted">Requires monitoring</span>
          </span>
          <span class="dash-arrow"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
        </a>
        <a href="{{ route('students.index') }}" class="small fw-bold text-decoration-none d-inline-flex align-items-center gap-1">View all students <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-5">
    <div class="nl-card panel h-100">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="panel-title mb-0">Feeding Schedule</div>
        <div class="d-flex align-items-center gap-2 small fw-bold">
          <button class="btn btn-light btn-sm nl-btn" type="button">&lsaquo;</button>
          <span>{{ now('Asia/Manila')->format('D, M j') }}</span>
          <button class="btn btn-light btn-sm nl-btn" type="button">&rsaquo;</button>
          <a href="{{ route('feeding-schedules.index', ['mode' => 'week', 'date' => now('Asia/Manila')->toDateString()]) }}" class="btn btn-primary btn-sm nl-btn">Today</a>
        </div>
      </div>
      <div class="schedule-grid">
        @foreach([
          ['07:00 AM', 'Batch A', 'Grades 1-2', 'AM'],
          ['07:30 AM', 'Batch B', 'Grades 3-4', 'AM'],
          ['12:00 PM', 'Batch A', 'Grades 1-2', 'PM'],
          ['12:30 PM', 'Batch B', 'Grades 3-4', 'PM'],
        ] as $slot)
          <div class="schedule-item">
            <div class="d-flex justify-content-between">
              <div class="fw-bold small">{{ $slot[0] }}</div>
              <span class="slot-icon">{{ $slot[3] }}</span>
            </div>
            <div class="fw-bold small mt-1">{{ $slot[1] }}</div>
            <div class="small text-muted">{{ $slot[2] }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
  <div class="col-xl-7">
    <div class="nl-card panel h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <div class="panel-title">Meal Suggestions</div>
          <div class="panel-subtitle mb-0">For undernourished students</div>
        </div>
        <a href="{{ route('menu-items.index') }}" class="small fw-bold text-decoration-none">View all</a>
      </div>
      @if(!empty($suggestion['items']) && count($suggestion['items']))
        <div class="table-responsive">
          <table class="table suggestion-table align-middle mb-0">
            <thead><tr><th>Food</th><th>Portion</th><th class="text-end">kcal</th><th class="text-end">Protein</th></tr></thead>
            <tbody>
              @foreach($suggestion['items'] as $f)
                <tr>
                  <td class="fw-semibold">{{ $f->name }}</td>
                  <td>{{ $f->portion }}</td>
                  <td class="text-end">{{ (int)($f->kcal ?? 0) }}</td>
                  <td class="text-end">{{ number_format((float)($f->protein_g ?? 0),1) }}g</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-muted">No suggestions available yet. Add menu items to see suggestions.</div>
      @endif
    </div>
  </div>
</div>

<script>
  (function(){
    const labels = @json($bmiLabels);
    const values = @json($bmiSeries);
    const recordCounts = @json($bmiRecordCounts ?? []);
    const dark = document.body.classList.contains('dark');
    const gridColor = dark ? 'rgba(255,255,255,0.12)' : 'rgba(8,40,88,0.08)';
    const textColor = dark ? '#e9ecef' : '#7c8bad';
    const ctx = document.getElementById('bmiChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Average BMI', data: values, borderColor: '#0b3b82', backgroundColor: '#0b3b82', tension: 0.22, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', align: 'start', labels: { color: textColor, boxWidth: 24, usePointStyle: false } },
          tooltip: {
            callbacks: {
              afterLabel: function(context) {
                const count = recordCounts[context.dataIndex] || 0;
                return count + ' BMI ' + (count === 1 ? 'record' : 'records');
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: textColor }, grid: { color: gridColor } },
          y: { min: 15, suggestedMax: 20, ticks: { color: textColor }, grid: { color: gridColor } }
        }
      }
    });
  })();
</script>
@endsection
