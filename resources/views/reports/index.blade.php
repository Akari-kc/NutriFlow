@extends('layouts.app')
@section('page-title', 'Reports')
@section('content')
@php
  $normal = $statusCounts['Normal'] ?? 0;
  $undernourished = $statusCounts['Undernourished'] ?? 0;
  $severe = $statusCounts['Severely Undernourished'] ?? 0;
  $noMeasurement = $statusCounts['No Measurement'] ?? 0;
  $statusMeta = [
      ['Normal', '#16a34a'],
      ['Undernourished', '#d97706'],
      ['Severely Undernourished', '#dc2626'],
      ['Overweight', '#2563eb'],
      ['Obese', '#7c3aed'],
      ['Needs Review', '#0f766e'],
      ['No Measurement', '#64748b'],
  ];
  $percent = fn($value) => $totalStudents > 0 ? round(($value / $totalStudents) * 100, 1) : 0;
  $exportQuery = request()->query();
@endphp
<style>
  .reports-page { color: #082858; }
  .reports-title { font-size: 19px; font-weight: 850; margin: 0; letter-spacing: 0; }
  .reports-subtitle { color: #7688ad; font-size: 12px; margin-top: 4px; }
  .filter-card { padding: 18px; margin: 20px 0; }
  .filter-grid { display: grid; grid-template-columns: 56px 100px 96px 104px minmax(220px, 1.25fr) minmax(150px, 1fr) auto auto auto; gap: 12px; align-items: end; }
  .filter-label { color: #42567e; font-size: 12px; margin-bottom: 5px; }
  .filter-chip { display: flex; align-items: center; gap: 7px; color: #42567e; font-size: 13px; padding-bottom: 8px; }
  .filter-control { height: 34px; border: 1px solid #d3dbea; border-radius: 7px; padding: 0 12px; color: #082858; background: #fff; font-size: 12px; min-width: 0; }
  .filter-control.wide { width: 100%; }
  .date-range-picker { position: relative; }
  .date-range-trigger { display: flex; align-items: center; justify-content: space-between; gap: 8px; cursor: pointer; text-align: left; white-space: nowrap; }
  .date-range-trigger .svg-icon { color: #7688ad; }
  .date-range-popover { position: absolute; z-index: 1080; top: calc(100% + 7px); left: 0; width: 322px; border: 1px solid #d3dbea; border-radius: 12px; background: #fff; box-shadow: 0 16px 38px rgba(8,40,88,.18); padding: 14px; }
  .date-range-popover[hidden] { display: none; }
  .date-range-calendar-head { display: grid; grid-template-columns: 32px 1fr 32px; gap: 8px; align-items: center; margin-bottom: 12px; }
  .date-range-month { color: #082858; font-size: 13px; font-weight: 850; text-align: center; }
  .date-range-nav { width: 32px; height: 32px; display: grid; place-items: center; border: 1px solid #dbe3ef; border-radius: 8px; background: #f8fafc; color: #42567e; }
  .date-range-weekdays, .date-range-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
  .date-range-weekdays { margin-bottom: 4px; color: #7688ad; font-size: 10px; font-weight: 800; text-align: center; }
  .date-range-weekdays span { padding: 4px 0; }
  .date-range-day { position: relative; height: 34px; border: 0; border-radius: 8px; background: transparent; color: #42567e; font-size: 12px; }
  .date-range-day:hover, .date-range-day:focus-visible { background: #eaf2ff; color: #0b3b82; outline: none; }
  .date-range-day.in-range { border-radius: 0; background: #eaf2ff; color: #0b3b82; }
  .date-range-day.range-start, .date-range-day.range-end { border-radius: 8px; background: #0b3b82; color: #fff; font-weight: 850; }
  .date-range-day.today:not(.range-start):not(.range-end) { box-shadow: inset 0 0 0 1px #ffb703; color: #9a6700; font-weight: 850; }
  .date-range-blank { height: 34px; }
  .date-range-summary { min-height: 18px; margin: 10px 0; color: #526894; font-size: 11px; }
  .date-range-actions { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #e8edf5; padding-top: 10px; }
  .date-range-actions .btn { font-size: 11px; font-weight: 800; }
  body.dark .date-range-popover { background: var(--nf-card); border-color: var(--nf-line); box-shadow: 0 16px 38px rgba(0,0,0,.38); }
  body.dark .date-range-month { color: var(--nf-text-primary); }
  body.dark .date-range-nav { background: var(--nf-surface-raised); border-color: var(--nf-line); color: var(--nf-text-secondary); }
  body.dark .date-range-day { color: var(--nf-text-secondary); }
  body.dark .date-range-day:hover, body.dark .date-range-day:focus-visible, body.dark .date-range-day.in-range { background: #223d5c; color: #dcebff; }
  body.dark .date-range-day.range-start, body.dark .date-range-day.range-end { background: #4d8fd1; color: #fff; }
  body.dark .date-range-day.today:not(.range-start):not(.range-end) { color: var(--nf-accent-text); }
  body.dark .date-range-summary, body.dark .date-range-weekdays { color: var(--nf-text-muted); }
  body.dark .date-range-actions { border-color: var(--nf-line); }
  .report-btn { height: 32px; border: 0; border-radius: 8px; padding: 0 16px; font-size: 12px; font-weight: 850; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; white-space: nowrap; }
  .report-btn.primary { background: #0b3b82; color: #fff; }
  .report-btn.pdf { background: #ffe8e8; color: #e11d2e; }
  .report-btn.excel { background: #e7f8ee; color: #009b4d; }
  .metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
  .report-metric { position: relative; padding: 17px 16px 15px; min-height: 112px; }
  .metric-dot { position: absolute; top: 19px; right: 16px; width: 10px; height: 10px; border-radius: 999px; }
  .metric-title { color: #42567e; font-size: 12px; margin-bottom: 8px; }
  .metric-value { font-size: 30px; line-height: 1; font-weight: 850; letter-spacing: 0; }
  .metric-note { color: #7688ad; font-size: 12px; margin-top: 7px; }
  .metric-blue { color: #0b3b82; }
  .metric-green { color: #16a34a; }
  .metric-orange { color: #d97706; }
  .metric-red { color: #dc2626; }
  .metric-muted { color: #64748b; }
  .reports-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(360px, 1.95fr); gap: 18px; margin-bottom: 20px; }
  .reports-grid.single { grid-template-columns: 1fr; }
  .chart-panel { padding: 20px; }
  .panel-title { font-size: 14px; font-weight: 850; margin: 0; color: #082858; }
  .panel-subtitle { color: #7688ad; font-size: 12px; margin-top: 4px; }
  .chart-box { position: relative; height: 250px; margin-top: 12px; }
  .chart-box.short { height: 220px; }
  .donut-wrap { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(210px, .9fr); gap: 16px; align-items: center; min-height: 250px; }
  .donut-canvas { height: 190px; }
  .status-legend { display: grid; gap: 10px; align-self: end; }
  .legend-row { display: grid; grid-template-columns: 12px minmax(0, 1fr) 44px 46px; gap: 8px; align-items: center; color: #42567e; font-size: 12px; }
  .legend-dot { width: 10px; height: 10px; border-radius: 999px; }
  .legend-count { color: #082858; font-weight: 850; text-align: right; }
  .legend-percent { color: #7688ad; text-align: right; }
  .chart-legend { display: flex; gap: 18px; flex-wrap: wrap; color: #7688ad; font-size: 12px; margin-top: 8px; }
  .legend-inline { display: inline-flex; align-items: center; gap: 7px; }
  .report-table-card { overflow: hidden; }
  .table-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px 14px; gap: 12px; }
  .table-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .student-table-filters { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(170px, 220px) auto; gap: 10px; align-items: center; padding: 0 20px 14px; }
  .student-search-wrap { position: relative; }
  .student-search-wrap .svg-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #7688ad; pointer-events: none; }
  .student-search { width: 100%; padding-left: 36px; }
  .student-result-count { color: #7688ad; font-size: 12px; white-space: nowrap; text-align: right; }
  .student-table-scroll { max-height: 430px; overflow: auto; border-top: 1px solid #e8edf5; }
  .report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .report-table th { position: sticky; top: 0; z-index: 2; background: #f8fafc; color: #42567e; font-size: 12px; font-weight: 850; padding: 12px 20px; border-bottom: 1px solid #e8edf5; text-align: left; }
  .report-table td { padding: 13px 20px; border-bottom: 1px solid #edf1f7; color: #42567e; vertical-align: middle; }
  .report-name { display: flex; align-items: center; gap: 12px; color: #082858; font-weight: 850; }
  .file-icon { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 8px; color: #00a65a; background: #e7f8ee; }
  .badge-soft { border-radius: 8px; padding: 5px 9px; font-size: 12px; font-weight: 850; display: inline-flex; }
  .badge-soft.normal { color: #16a34a; background: #e7f8ee; }
  .badge-soft.undernourished { color: #d97706; background: #fff0dc; }
  .badge-soft.severe { color: #dc2626; background: #ffe8e8; }
  .badge-soft.missing { color: #64748b; background: #eef2f8; }
  @media (max-width: 1180px) {
    .filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .metric-grid, .reports-grid { grid-template-columns: 1fr 1fr; }
    .donut-wrap { grid-template-columns: 1fr; }
  }
  @media (max-width: 760px) {
    .filter-grid, .metric-grid, .reports-grid { grid-template-columns: 1fr; }
    .table-head { flex-wrap: wrap; }
    .student-table-filters { grid-template-columns: 1fr; }
    .student-result-count { text-align: left; }
    .report-table { min-width: 940px; }
  }
</style>

<div class="reports-page">
  <div>
    <h1 class="reports-title">Nutrition Monitoring Reports</h1>
    <div class="reports-subtitle">{{ $schoolName }} &middot; SY {{ $schoolYear }} &middot; {{ $startDate->format('M j, Y') }} to {{ $endDate->format('M j, Y') }}</div>
  </div>

  <form method="GET" class="nl-card filter-card">
    <div class="filter-grid">
      <div class="filter-chip"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18"/><path d="M7 12h10"/><path d="M10 19h4"/></svg><span>Filters</span></div>
      <div>
        <div class="filter-label">School Year</div>
        <select name="school_year" class="filter-control wide">
          <option value="2025-2026" @selected($schoolYear === '2025-2026')>2025-2026</option>
          <option value="2026-2027" @selected($schoolYear === '2026-2027')>2026-2027</option>
        </select>
      </div>
      <div>
        <div class="filter-label">Grade</div>
        <select name="grade" class="filter-control wide">
          <option value="All" @selected($selectedGrade === 'All')>All Grades</option>
          @foreach($classes as $class)
            <option value="{{ $class }}" @selected($selectedGrade === $class)>{{ $class }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="filter-label">Section</div>
        <select name="section" class="filter-control wide">
          <option value="All" @selected($selectedSection === 'All')>All Sections</option>
          @foreach($sections as $section)
            <option value="{{ $section }}" @selected($selectedSection === $section)>{{ $section }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="filter-label">Date Range</div>
        <div class="date-range-picker" id="reportDateRangePicker" data-start="{{ $startDate->toDateString() }}" data-end="{{ $endDate->toDateString() }}">
          <input name="date_range" id="reportDateRangeValue" type="hidden" value="{{ $startDate->toDateString() }} to {{ $endDate->toDateString() }}">
          <button type="button" class="filter-control wide date-range-trigger" id="reportDateRangeTrigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="reportDateRangePopover">
            <span id="reportDateRangeText">{{ $startDate->format('M j, Y') }} – {{ $endDate->format('M j, Y') }}</span>
            <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
          </button>
          <div class="date-range-popover" id="reportDateRangePopover" role="dialog" aria-modal="false" aria-label="Choose report date range" hidden>
            <div class="date-range-calendar-head">
              <button type="button" class="date-range-nav" data-calendar-previous aria-label="Previous month">&lsaquo;</button>
              <div class="date-range-month" id="reportDateRangeMonth" aria-live="polite"></div>
              <button type="button" class="date-range-nav" data-calendar-next aria-label="Next month">&rsaquo;</button>
            </div>
            <div class="date-range-weekdays" aria-hidden="true">
              <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>
            <div class="date-range-days" id="reportDateRangeDays"></div>
            <div class="date-range-summary" id="reportDateRangeSummary" aria-live="polite"></div>
            <div class="date-range-actions">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-calendar-cancel>Cancel</button>
              <button type="button" class="btn btn-sm btn-primary" data-calendar-apply>Apply Range</button>
            </div>
          </div>
        </div>
      </div>
      <div>
        <div class="filter-label">Nutritional Status</div>
        <select name="status" class="filter-control wide">
          <option value="All" @selected($selectedStatus === 'All')>All</option>
          <option value="Normal" @selected($selectedStatus === 'Normal')>Normal</option>
          <option value="Undernourished" @selected($selectedStatus === 'Undernourished')>Undernourished</option>
          <option value="Severely Undernourished" @selected($selectedStatus === 'Severely Undernourished')>Severely Undernourished</option>
          <option value="Overweight" @selected($selectedStatus === 'Overweight')>Overweight</option>
          <option value="Obese" @selected($selectedStatus === 'Obese')>Obese</option>
          <option value="Needs Review" @selected($selectedStatus === 'Needs Review')>Needs Review</option>
          <option value="No Measurement" @selected($selectedStatus === 'No Measurement')>No Measurement</option>
        </select>
      </div>
      <button class="report-btn primary" type="submit">Apply</button>
      @if(auth()->user()?->isSchoolAdmin())
        <a class="report-btn pdf" href="{{ route('reports.export.pdf', $exportQuery) }}" target="_blank"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg> Export PDF</a>
        <a class="report-btn excel" href="{{ route('reports.export.csv', $exportQuery) }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h8"/><path d="M8 9h2"/></svg> Export CSV</a>
      @endif
    </div>
  </form>

  <div class="metric-grid">
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#0b3b82"></span>
      <div class="metric-title">Total Students</div>
      <div class="metric-value metric-blue">{{ number_format($totalStudents) }}</div>
      <div class="metric-note">{{ $selectedGrade === 'All' ? 'All grades' : $selectedGrade }} &middot; SY {{ $schoolYear }}</div>
    </div>
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#16a34a"></span>
      <div class="metric-title">Normal Nutrition</div>
      <div class="metric-value metric-green">{{ number_format($normal) }}</div>
      <div class="metric-note">{{ $percent($normal) }}% of students</div>
    </div>
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#d97706"></span>
      <div class="metric-title">Undernourished</div>
      <div class="metric-value metric-orange">{{ number_format($undernourished) }}</div>
      <div class="metric-note">{{ $percent($undernourished) }}% of students</div>
    </div>
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#dc2626"></span>
      <div class="metric-title">Severely Undernourished</div>
      <div class="metric-value metric-red">{{ number_format($severe) }}</div>
      <div class="metric-note">{{ $percent($severe) }}% of students</div>
    </div>
  </div>

  <div class="metric-grid">
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#2563eb"></span>
      <div class="metric-title">Screened This Period</div>
      <div class="metric-value metric-blue">{{ number_format($screenedStudents) }}</div>
      <div class="metric-note">{{ $percent($screenedStudents) }}% of filtered students</div>
    </div>
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#64748b"></span>
      <div class="metric-title">No Measurement</div>
      <div class="metric-value metric-muted">{{ number_format($noMeasurement) }}</div>
      <div class="metric-note">{{ $percent($noMeasurement) }}% need screening</div>
    </div>
    <div class="nl-card report-metric">
      <span class="metric-dot" style="background:#0b3b82"></span>
      <div class="metric-title">Meals Served</div>
      <div class="metric-value metric-blue">{{ number_format($mealTotals['meals_served']) }}</div>
      <div class="metric-note">Recorded during the selected period</div>
    </div>
  </div>

  <div class="reports-grid">
    <div class="nl-card chart-panel">
      <h2 class="panel-title">BMI Trend Monitoring</h2>
      <div class="panel-subtitle">Monthly average BMI for the selected students &middot; SY {{ $schoolYear }}</div>
      <div class="chart-box">
        <canvas id="bmiTrendChart"></canvas>
      </div>
      <div class="chart-legend">
        <span class="legend-inline"><span class="legend-dot" style="background:#2563eb"></span> Average BMI</span>
      </div>
    </div>

    <div class="nl-card chart-panel">
      <h2 class="panel-title">Nutritional Status Distribution</h2>
      <div class="panel-subtitle">Latest recorded status &middot; {{ $selectedGrade === 'All' ? 'all grades' : $selectedGrade }}</div>
      <div class="donut-wrap">
        <div class="donut-canvas"><canvas id="statusDonut"></canvas></div>
        <div class="status-legend">
          @foreach($statusMeta as [$label, $color])
            @php
              $count = $statusCounts[$label] ?? 0;
            @endphp
            <div class="legend-row"><span class="legend-dot" style="background:{{ $color }}"></span><span>{{ $label }}</span><span class="legend-count">{{ $count }}</span><span class="legend-percent">{{ $percent($count) }}%</span></div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="reports-grid single">
    <div class="nl-card chart-panel">
      <h2 class="panel-title">Monthly Screening Progress</h2>
      <div class="panel-subtitle">Distinct students measured each month in the selected period</div>
      <div class="chart-box short">
        <canvas id="screeningChart"></canvas>
      </div>
    </div>
  </div>

  <div class="nl-card report-table-card">
    <div class="table-head">
      <div>
        <h2 class="panel-title">Student Report Detail</h2>
        <div class="panel-subtitle">Latest nutrition status plus meals served during the selected period</div>
      </div>
      @if(auth()->user()?->isSchoolAdmin())
        <div class="table-actions">
          <a class="report-btn primary" href="{{ route('reports.export.csv', $exportQuery) }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg> Download CSV</a>
        </div>
      @endif
    </div>
    <div class="student-table-filters">
      <div class="student-search-wrap">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="studentReportSearch" class="filter-control student-search" type="search" placeholder="Search student, grade, section, or status..." autocomplete="off">
      </div>
      <select id="studentReportStatus" class="filter-control wide" aria-label="Filter student report by status">
        <option value="">All statuses</option>
        @foreach($statusMeta as [$statusLabel])
          <option value="{{ strtolower($statusLabel) }}">{{ $statusLabel }}</option>
        @endforeach
      </select>
      <div id="studentReportCount" class="student-result-count">{{ number_format($reportRows->count()) }} students</div>
    </div>
    <div class="table-responsive student-table-scroll">
      <table class="report-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Grade / Section</th>
            <th>Status</th>
            <th>Latest BMI</th>
            <th>Meals</th>
          </tr>
        </thead>
        <tbody id="studentReportRows">
          @foreach($reportRows as $row)
            @php
              $statusClass = match($row['status']) {
                'Normal' => 'normal',
                'Undernourished' => 'undernourished',
                'Severely Undernourished' => 'severe',
                default => 'missing',
              };
            @endphp
            <tr data-student-row data-status="{{ strtolower($row['status']) }}">
              <td>
                <div class="report-name">
                  <span class="file-icon"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                  {{ $row['student']->name }}
                </div>
              </td>
              <td>{{ $row['student']->class_name ?? 'Unassigned' }} / {{ $row['student']->section ?? 'No section' }}</td>
              <td><span class="badge-soft {{ $statusClass }}">{{ $row['status'] }}</span></td>
              <td>{{ $row['bmi'] ?? 'No BMI' }}<br><span class="reports-subtitle">{{ $row['measured_at'] ?? 'Not screened' }}</span></td>
              <td>{{ number_format($row['meals_served']) }}</td>
            </tr>
          @endforeach
          <tr id="studentReportEmpty" @if($reportRows->isNotEmpty()) hidden @endif>
            <td colspan="5">No students match your search and status filter.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const reportDateRangePicker = document.getElementById('reportDateRangePicker');

  if (reportDateRangePicker) {
    const rangeTrigger = document.getElementById('reportDateRangeTrigger');
    const rangePopover = document.getElementById('reportDateRangePopover');
    const rangeInput = document.getElementById('reportDateRangeValue');
    const rangeText = document.getElementById('reportDateRangeText');
    const rangeMonth = document.getElementById('reportDateRangeMonth');
    const rangeDays = document.getElementById('reportDateRangeDays');
    const rangeSummary = document.getElementById('reportDateRangeSummary');
    const rangeApply = rangePopover.querySelector('[data-calendar-apply]');
    const displayDate = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const displayMonth = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });

    const parseIsoDate = (value) => {
      const parts = value.split('-').map(Number);
      return new Date(parts[0], parts[1] - 1, parts[2], 12);
    };

    const toIsoDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return year + '-' + month + '-' + day;
    };

    const displayRange = (start, end) => displayDate.format(parseIsoDate(start)) + ' – ' + displayDate.format(parseIsoDate(end));

    let committedStart = reportDateRangePicker.dataset.start;
    let committedEnd = reportDateRangePicker.dataset.end;
    let pendingStart = committedStart;
    let pendingEnd = committedEnd;
    let viewDate = parseIsoDate(committedEnd);

    function renderDateRangeCalendar() {
      rangeMonth.textContent = displayMonth.format(viewDate);
      rangeDays.replaceChildren();

      const year = viewDate.getFullYear();
      const month = viewDate.getMonth();
      const firstWeekday = new Date(year, month, 1, 12).getDay();
      const daysInMonth = new Date(year, month + 1, 0, 12).getDate();
      const today = toIsoDate(new Date());

      for (let blank = 0; blank < firstWeekday; blank++) {
        const spacer = document.createElement('span');
        spacer.className = 'date-range-blank';
        spacer.setAttribute('aria-hidden', 'true');
        rangeDays.appendChild(spacer);
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day, 12);
        const value = toIsoDate(date);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'date-range-day';
        button.textContent = String(day);
        button.dataset.date = value;
        button.setAttribute('aria-label', displayDate.format(date));

        if (value === today) button.classList.add('today');
        if (value === pendingStart) button.classList.add('range-start');
        if (value === pendingEnd) button.classList.add('range-end');
        if (pendingStart && pendingEnd && value > pendingStart && value < pendingEnd) {
          button.classList.add('in-range');
        }

        button.addEventListener('click', function () {
          if (!pendingStart || pendingEnd) {
            pendingStart = value;
            pendingEnd = null;
          } else if (value < pendingStart) {
            pendingEnd = pendingStart;
            pendingStart = value;
          } else {
            pendingEnd = value;
          }

          renderDateRangeCalendar();
        });

        rangeDays.appendChild(button);
      }

      if (pendingStart && pendingEnd) {
        rangeSummary.textContent = displayRange(pendingStart, pendingEnd);
      } else if (pendingStart) {
        rangeSummary.textContent = 'Start: ' + displayDate.format(parseIsoDate(pendingStart)) + ' · Select an end date';
      } else {
        rangeSummary.textContent = 'Select a start date';
      }

      rangeApply.disabled = !(pendingStart && pendingEnd);
    }

    function openDateRangePicker() {
      pendingStart = committedStart;
      pendingEnd = committedEnd;

      const today = toIsoDate(new Date());
      viewDate = parseIsoDate(today >= committedStart && today <= committedEnd ? today : committedEnd);
      rangePopover.hidden = false;
      rangeTrigger.setAttribute('aria-expanded', 'true');
      renderDateRangeCalendar();
    }

    function closeDateRangePicker(returnFocus) {
      rangePopover.hidden = true;
      rangeTrigger.setAttribute('aria-expanded', 'false');
      if (returnFocus) rangeTrigger.focus();
    }

    rangeTrigger.addEventListener('click', function () {
      if (rangePopover.hidden) {
        openDateRangePicker();
      } else {
        closeDateRangePicker(false);
      }
    });

    rangePopover.querySelector('[data-calendar-previous]').addEventListener('click', function () {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1, 12);
      renderDateRangeCalendar();
    });

    rangePopover.querySelector('[data-calendar-next]').addEventListener('click', function () {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1, 12);
      renderDateRangeCalendar();
    });

    rangePopover.querySelector('[data-calendar-cancel]').addEventListener('click', function () {
      pendingStart = committedStart;
      pendingEnd = committedEnd;
      closeDateRangePicker(true);
    });

    rangeApply.addEventListener('click', function () {
      if (!(pendingStart && pendingEnd)) return;

      committedStart = pendingStart;
      committedEnd = pendingEnd;
      rangeInput.value = committedStart + ' to ' + committedEnd;
      rangeText.textContent = displayRange(committedStart, committedEnd);
      reportDateRangePicker.dataset.start = committedStart;
      reportDateRangePicker.dataset.end = committedEnd;
      closeDateRangePicker(true);
    });

    document.addEventListener('click', function (event) {
      if (!rangePopover.hidden && !event.composedPath().includes(reportDateRangePicker)) {
        pendingStart = committedStart;
        pendingEnd = committedEnd;
        closeDateRangePicker(false);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !rangePopover.hidden) {
        pendingStart = committedStart;
        pendingEnd = committedEnd;
        closeDateRangePicker(true);
      }
    });
  }

  const studentReportSearch = document.getElementById('studentReportSearch');
  const studentReportStatus = document.getElementById('studentReportStatus');
  const studentReportCount = document.getElementById('studentReportCount');
  const studentReportEmpty = document.getElementById('studentReportEmpty');
  const studentReportRows = Array.from(document.querySelectorAll('[data-student-row]'));

  function filterStudentReport() {
    const search = studentReportSearch.value.trim().toLowerCase();
    const status = studentReportStatus.value;
    let visible = 0;

    studentReportRows.forEach((row) => {
      const matchesSearch = search === '' || row.textContent.toLowerCase().includes(search);
      const matchesStatus = status === '' || row.dataset.status === status;
      const show = matchesSearch && matchesStatus;
      row.hidden = !show;
      if (show) visible++;
    });

    studentReportCount.textContent = `${visible} ${visible === 1 ? 'student' : 'students'}`;
    studentReportEmpty.hidden = visible !== 0;
  }

  studentReportSearch.addEventListener('input', filterStudentReport);
  studentReportStatus.addEventListener('change', filterStudentReport);

  const labels = @json($trendLabels);
  const bmiTrend = @json($bmiTrend);
  const screeningProgress = @json($screeningProgress);
  const statusLabels = @json(collect($statusMeta)->pluck(0)->values());
  const statusColors = @json(collect($statusMeta)->pluck(1)->values());
  const statusData = @json(collect($statusMeta)->map(fn($item) => $statusCounts[$item[0]] ?? 0)->values());
  const gridColor = '#edf1f7';
  const textColor = '#7688ad';

  const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: true } },
    scales: {
      x: { ticks: { color: textColor, font: { size: 11 } }, grid: { color: gridColor } },
      y: { ticks: { color: textColor, font: { size: 11 } }, grid: { color: gridColor } }
    }
  };

  new Chart(document.getElementById('bmiTrendChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Average BMI',
        data: bmiTrend,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,.08)',
        pointBackgroundColor: '#2563eb',
        pointBorderColor: '#2563eb',
        pointRadius: 3,
        borderWidth: 2,
        tension: .35
      }]
    },
    options: {
      ...lineOptions,
      scales: {
        x: lineOptions.scales.x,
        y: { ...lineOptions.scales.y, suggestedMin: 13, suggestedMax: 21 }
      }
    }
  });

  new Chart(document.getElementById('statusDonut'), {
    type: 'doughnut',
    data: {
      labels: statusLabels,
      datasets: [{
        data: statusData,
        backgroundColor: statusColors,
        borderColor: '#fff',
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: { legend: { display: false }, tooltip: { enabled: true } }
    }
  });

  new Chart(document.getElementById('screeningChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Screened',
        data: screeningProgress,
        borderColor: '#2563eb',
        pointBackgroundColor: '#2563eb',
        pointBorderColor: '#2563eb',
        pointRadius: 3,
        borderWidth: 2,
        tension: .35
      }]
    },
    options: lineOptions
  });
</script>
@endsection
