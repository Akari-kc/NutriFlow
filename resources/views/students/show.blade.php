@extends('layouts.app')
@section('page-title', 'Children')
@section('content')
<style>
  .profile-top { display: flex; align-items: center; gap: .8rem; margin-bottom: 1.25rem; }
  .back-square { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: #eef4fb; color: #0b3b82; text-decoration: none; font-weight: 900; }
  .profile-title { font-size: 1.12rem; font-weight: 800; color: #082858; margin: 0; }
  .profile-crumb { color: #7c8bad; font-size: .78rem; margin-top: .2rem; }
  .profile-hero { padding: 1.45rem; margin-bottom: 1.2rem; }
  .profile-main { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding-bottom: 1.4rem; border-bottom: 1px solid #e8edf5; }
  .profile-identity { display: flex; align-items: center; gap: 1.2rem; }
  .profile-avatar { width: 64px; height: 64px; border-radius: 14px; display: grid; place-items: center; background: #0b3b82; color: #fff; font-size: 1.35rem; font-weight: 900; }
  .profile-name { font-size: 1.2rem; font-weight: 900; color: #082858; margin-bottom: .25rem; }
  .status-pill { border-radius: 999px; padding: .28rem .65rem; font-size: .74rem; font-weight: 800; }
  .status-healthy { background: #e7f3ed; color: #057243; }
  .status-alert { background: #ffeceb; color: #d92d20; }
  .profile-facts { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1rem; padding-top: 1.25rem; }
  .fact-label { display: flex; align-items: center; gap: .4rem; color: #7c8bad; font-size: .78rem; }
  .fact-value { color: #082858; font-weight: 800; font-size: .88rem; margin-top: .45rem; }
  .metric-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.2rem; }
  .profile-metric { display: flex; align-items: center; gap: 1rem; padding: 1.45rem 1.25rem; min-height: 92px; }
  .profile-metric.bmi-risk { background: #fff5f4; border-color: #ffc9c4; }
  .metric-icon-large { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: #eef4fb; color: #0b3b82; font-weight: 900; }
  .metric-icon-large.amber { background: #fff6dd; color: #c78200; }
  .metric-icon-large.red { background: #fff; color: #d92d20; }
  .metric-label-profile { color: #7c8bad; font-size: .78rem; }
  .metric-value-profile { color: #0b3b82; font-size: 1.5rem; font-weight: 900; line-height: 1; margin-top: .35rem; }
  .profile-panel { padding: 1.25rem; }
  .panel-title-row { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
  .panel-title { color: #082858; font-weight: 900; margin-bottom: .25rem; }
  .panel-subtitle { color: #7c8bad; font-size: .78rem; }
  .bmi-risk-pill { color: #d92d20; background: #fff5f4; border: 1px solid #ffc9c4; border-radius: 9px; padding: .36rem .65rem; font-size: .76rem; font-weight: 800; }
  .bmi-period-tabs { display: inline-flex; gap: .15rem; padding: .18rem; background: #e9edf4; border-radius: 9px; }
  .bmi-period-tabs a { color: #526894; border-radius: 8px; padding: .36rem .65rem; font-size: .76rem; font-weight: 800; text-decoration: none; }
  .bmi-period-tabs a.active { background: #fff; color: #082858; box-shadow: 0 1px 4px rgba(8,40,88,.08); }
  .bmi-summary { border-top: 1px solid #e8edf5; display: flex; gap: 1.4rem; flex-wrap: wrap; padding-top: .9rem; margin-top: .9rem; }
  .bmi-summary-label { color: #7c8bad; font-size: .76rem; }
  .bmi-summary-value { color: #082858; font-weight: 900; font-size: .9rem; }
  .side-card { padding: 1.25rem; }
  .last-meal-box { background: #f5f7fb; border: 1px solid #e4e9f2; border-radius: 12px; padding: 1rem; }
  .meal-type-pill { display: inline-block; background: #dfe8f8; color: #0b3b82; border-radius: 7px; padding: .18rem .55rem; font-size: .76rem; font-weight: 800; }
  .allergy-row { display: flex; align-items: center; gap: .65rem; background: #fff5f4; border: 1px solid #ffc9c4; color: #d92d20; border-radius: 10px; padding: .75rem; font-weight: 800; font-size: .86rem; }
  .tiny-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
  .profile-actions { display: flex; gap: .55rem; flex-wrap: wrap; }
  .compact-form-card { padding: 1.25rem; margin-top: 1.2rem; }
  .history-filters { display: flex; align-items: end; gap: .7rem; flex-wrap: wrap; margin: .85rem 0 1rem; }
  .history-filters .form-control, .history-filters .form-select { min-height: 36px; font-size: .84rem; border-radius: 9px; }
  .unit-pair { display: grid; grid-template-columns: minmax(0, 1fr) 92px; gap: .45rem; }
  .growth-entry-card { border: 1px solid #cdddf2; background: #f8fbff; border-radius: 10px; padding: 1rem; box-shadow: inset 0 1px 0 rgba(255,255,255,.8); }
  .growth-entry-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: .85rem; }
  .growth-entry-title { color: #082858; font-weight: 900; margin-bottom: .18rem; }
  .growth-entry-copy { color: #7c8bad; font-size: .78rem; }
  .growth-entry-badge { background: #0b3b82; color: #fff; border-radius: 8px; padding: .32rem .55rem; font-size: .74rem; font-weight: 900; white-space: nowrap; }
  .growth-entry-card .form-label { color: #30466f; font-weight: 800; }
  .growth-record-tools { display: grid; grid-template-columns: minmax(170px, 1fr) 150px 150px 150px; gap: .55rem; align-items: end; margin: .85rem 0; }
  .growth-record-tools .form-control, .growth-record-tools .form-select { min-height: 36px; font-size: .82rem; border-radius: 9px; }
  .growth-table-scroll { border: 1px solid #e4e9f2; border-radius: 10px; max-height: 320px; overflow: auto; }
  .growth-source-table { min-width: 720px; }
  .growth-source-table thead th { position: sticky; top: 0; background: #fbfcfe; z-index: 1; border-bottom: 1px solid #dbe3ef; }
  .growth-source-table th { color: #7c8bad; font-size: .76rem; font-weight: 900; }
  .growth-source-table td { color: #082858; font-size: .82rem; }
  .growth-empty-row { color: #7c8bad; padding: .85rem; font-size: .84rem; display: none; }
  @media (max-width: 1000px) {
    .profile-facts, .metric-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .growth-record-tools { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 640px) {
    .profile-facts, .metric-strip { grid-template-columns: 1fr; }
    .profile-main { display: block; }
    .profile-actions { margin-top: 1rem; }
    .growth-entry-head { display: block; }
    .growth-entry-badge { display: inline-block; margin-top: .55rem; }
    .growth-record-tools { grid-template-columns: 1fr; }
  }
</style>

@php
  $initials = $student->initials;
  $flag = \App\Support\ChildBmiClassifier::classifyForStudent($student, $latest);
  $isRisk = \App\Support\ChildBmiClassifier::isUndernourished($flag);
  $isNormalStatus = $flag === \App\Support\ChildBmiClassifier::NORMAL;
  $latestMealItems = $latestMeal ? $latestMeal->items->map(fn($it) => ($it->food?->name ?? 'Meal').' x'.$it->quantity)->implode(', ') : null;
@endphp

<div class="profile-top">
  <a href="{{ route('students.index') }}" class="back-square"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg></a>
  <div>
    <h1 class="profile-title">Student Profile</h1>
    <div class="profile-crumb">Children / {{ $student->display_name }}</div>
  </div>
</div>

<div class="nl-card profile-hero">
  <div class="profile-main">
    <div class="profile-identity">
      <div class="profile-avatar">{{ $initials }}</div>
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <div class="profile-name">{{ $student->display_name }}</div>
          <span class="status-pill {{ $isNormalStatus ? 'status-healthy' : 'status-alert' }}">{{ $flag }}</span>
          @if($allergies->count())
            <span class="status-pill status-alert">Allergy Alert</span>
          @endif
        </div>
        <div class="text-muted">{{ $student->gender ?? '-' }} &middot; {{ $student->class_name ?? '-' }}, Section {{ $student->section ?? '-' }}</div>
      </div>
    </div>
    @if(auth()->user()?->isSchoolAdmin())
      <div class="profile-actions">
        <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-primary btn-sm nl-btn">Edit</a>
        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Remove this student? This will also delete their meals and measurements.');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-outline-danger btn-sm nl-btn">Remove</button>
        </form>
      </div>
    @endif
  </div>

  <div class="profile-facts">
    <div>
      <div class="fact-label">Learner ID</div>
      <div class="fact-value">{{ $student->learner_uid }}</div>
    </div>
    <div>
      <div class="fact-label">Date of Birth</div>
      <div class="fact-value">{{ $student->birthdate?->format('F j, Y') ?? '-' }}</div>
    </div>
    <div>
      <div class="fact-label">LRN</div>
      <div class="fact-value">{{ $student->lrn ?: 'Not recorded' }}</div>
    </div>
    <div>
      <div class="fact-label">Age</div>
      <div class="fact-value">{{ $student->birthdate ? $student->birthdate->age.' years old' : '-' }}</div>
    </div>
    <div>
      <div class="fact-label">Grade &amp; Section</div>
      <div class="fact-value">{{ $student->class_name ?? '-' }} &mdash; Section {{ $student->section ?? '-' }}</div>
    </div>
    <div>
      <div class="fact-label">Gender</div>
      <div class="fact-value">{{ $student->gender ?? '-' }}</div>
    </div>
  </div>
</div>

<div class="metric-strip">
  <div class="nl-card profile-metric">
    <div class="metric-icon-large">
      <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16"/><path d="M6 19V5"/><path d="M10 9H6"/><path d="M10 13H6"/></svg>
    </div>
    <div>
      <div class="metric-label-profile">Height</div>
      <div class="metric-value-profile">{{ $latest ? number_format($latest->height_cm, 0) : '-' }} <span class="fs-6 fw-normal text-muted">cm</span></div>
    </div>
  </div>
  <div class="nl-card profile-metric">
    <div class="metric-icon-large amber">
      <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18"/><path d="M5 7h14"/><path d="M7 7l-3 6h6L7 7Z"/><path d="M17 7l-3 6h6l-3-6Z"/></svg>
    </div>
    <div>
      <div class="metric-label-profile">Weight</div>
      <div class="metric-value-profile">{{ $latest ? number_format($latest->weight_kg, 0) : '-' }} <span class="fs-6 fw-normal text-muted">kg</span></div>
    </div>
  </div>
  <div class="nl-card profile-metric {{ $isRisk ? 'bmi-risk' : '' }}">
    <div class="metric-icon-large {{ $isRisk ? 'red' : '' }}">BMI</div>
    <div>
      <div class="metric-label-profile {{ $isRisk ? 'text-danger' : '' }}">{{ $flag ?? 'Current BMI' }}</div>
      <div class="metric-value-profile {{ $isRisk ? 'text-danger' : '' }}">{{ $latest?->bmi ?? '-' }}</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="nl-card profile-panel">
      <div class="panel-title-row">
        <div>
          <div class="panel-title">BMI Trend</div>
          <div class="panel-subtitle">All records &middot; {{ number_format($bmiTotalRecords ?? 0) }} BMI entries &middot; Averaged by {{ $bmiPeriod ?? 'month' }}</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <div class="bmi-period-tabs">
            @foreach(['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'] as $periodValue => $periodLabel)
              <a class="{{ ($bmiPeriod ?? 'month') === $periodValue ? 'active' : '' }}" href="{{ route('students.show', array_merge(['student' => $student], request()->except('bmi_period'), ['bmi_period' => $periodValue])) }}">{{ $periodLabel }}</a>
            @endforeach
          </div>
          @if($isRisk)
            <div class="bmi-risk-pill">{{ $flag }}</div>
          @endif
        </div>
      </div>
      <div style="height: 240px;">
        <canvas id="studentBmiChart"></canvas>
      </div>
      <div class="bmi-summary">
        <div><div class="bmi-summary-label">Record low</div><div class="bmi-summary-value">{{ $bmiLow ?? '-' }}</div></div>
        <div><div class="bmi-summary-label">Record high</div><div class="bmi-summary-value">{{ $bmiHigh ?? '-' }}</div></div>
        <div><div class="bmi-summary-label">Change</div><div class="bmi-summary-value {{ $bmiChange >= 0 ? 'text-success' : 'text-danger' }}">{{ $bmiChange >= 0 ? '+' : '' }}{{ $bmiChange }}</div></div>
        <div><div class="bmi-summary-label">Current BMI</div><div class="bmi-summary-value {{ $isRisk ? 'text-danger' : '' }}">{{ $latest?->bmi ?? '-' }}</div></div>
      </div>
    </div>

    <div class="nl-card compact-form-card">
      <div class="growth-entry-card">
        <div class="growth-entry-head">
          <div>
            <div class="growth-entry-title">Add Growth Record</div>
            <div class="growth-entry-copy">Enter a new measurement here. Saving adds it to the records table and updates the BMI graph.</div>
          </div>
          <div class="growth-entry-badge">New record slot</div>
        </div>
        <form method="POST" action="{{ route('students.measurements.store', $student) }}" class="row g-2">
          @csrf
          <div class="col-md-3">
            <label class="form-label small mb-1">Measurement Date</label>
            <input type="date" name="measured_at" value="{{ old('measured_at', now()->format('Y-m-d')) }}" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Assessment Phase</label>
            <select name="assessment_phase" class="form-select form-select-sm" required>
              @foreach(['Baseline', 'Midline', 'Endline', 'Additional Monitoring'] as $phase)
                <option value="{{ $phase }}" @selected(old('assessment_phase', 'Additional Monitoring') === $phase)>{{ $phase }}</option>
              @endforeach
            </select>
            @error('assessment_phase')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Weight</label>
            <div class="unit-pair">
              <input type="number" step="0.01" min="0" name="weight_value" value="{{ old('weight_value', optional($latest)->weight_kg) }}" class="form-control form-control-sm" placeholder="Enter weight" required>
              <select name="weight_unit" class="form-select form-select-sm">
                <option value="kg" @selected(old('weight_unit', 'kg') === 'kg')>kg</option>
                <option value="g" @selected(old('weight_unit') === 'g')>g</option>
                <option value="lb" @selected(old('weight_unit') === 'lb')>lb</option>
              </select>
            </div>
            @error('weight_value')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Height</label>
            <div class="unit-pair">
              <input type="number" step="0.01" min="0" name="height_value" value="{{ old('height_value', optional($latest)->height_cm) }}" class="form-control form-control-sm" placeholder="Enter height" required>
              <select name="height_unit" class="form-select form-select-sm">
                <option value="cm" @selected(old('height_unit', 'cm') === 'cm')>cm</option>
                <option value="m" @selected(old('height_unit') === 'm')>m</option>
                <option value="in" @selected(old('height_unit') === 'in')>in</option>
                <option value="ft" @selected(old('height_unit') === 'ft')>ft</option>
              </select>
            </div>
            @error('height_value')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
          <div class="col-12 d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="small text-muted">Saved measurements are normalized to kg and cm for BMI calculations.</div>
            <button class="btn btn-primary btn-sm nl-btn">Save Measurement</button>
          </div>
        </form>
      </div>
      <div class="mt-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
          <div>
            <div class="panel-title mb-0">Growth Records</div>
            <div class="panel-subtitle">These saved rows feed the BMI trend graph.</div>
          </div>
        </div>
        @if(($growthTableRows ?? collect())->count())
          <div class="growth-record-tools" id="growthRecordFilters">
            <div>
              <label class="form-label small mb-1">Search</label>
              <input type="search" class="form-control" data-growth-search placeholder="Date, BMI, status...">
            </div>
            <div>
              <label class="form-label small mb-1">Status</label>
              <select class="form-select" data-growth-status>
                <option value="">All statuses</option>
                @foreach(($growthTableRows ?? collect())->pluck('bmi_flag')->filter()->unique()->sort()->values() as $statusOption)
                  <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">From</label>
              <input type="date" class="form-control" data-growth-from>
            </div>
            <div>
              <label class="form-label small mb-1">To</label>
              <input type="date" class="form-control" data-growth-to>
            </div>
          </div>
          <div class="table-responsive growth-table-scroll">
            <table class="table table-sm align-middle mb-0 growth-source-table">
              <thead><tr><th>Date</th><th>Phase</th><th>Weight</th><th>Height</th><th>BMI</th><th>Classification</th><th>Height-for-age</th></tr></thead>
              <tbody>
                @foreach($growthTableRows as $row)
                  @php
                    $growthDate = $row->measured_at?->format('Y-m-d') ?? (string) $row->measured_at;
                    $growthStatus = $row->bmi_flag ?: '-';
                  @endphp
                  <tr data-growth-row data-date="{{ $growthDate }}" data-status="{{ $growthStatus }}" data-search="{{ strtolower($growthDate.' '.number_format($row->weight_kg, 2).' '.number_format($row->height_cm, 1).' '.$row->bmi.' '.$growthStatus) }}">
                    <td>{{ $growthDate }}</td>
                    <td>{{ $row->assessment_phase ?: 'Additional Monitoring' }}</td>
                    <td>{{ number_format($row->weight_kg, 2) }} kg</td>
                    <td>{{ number_format($row->height_cm, 1) }} cm</td>
                    <td>{{ $row->bmi ?? '-' }}</td>
                    <td>{{ $growthStatus }}</td>
                    <td>{{ $row->height_for_age_status ?: 'Not recorded' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="growth-empty-row" data-growth-empty>No growth records match the filters.</div>
          </div>
        @else
          <div class="text-muted">No growth measurements yet.</div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    @if($latestEnrollment)
      <div class="nl-card side-card mb-3">
        <div class="panel-title mb-2">Feeding Program</div>
        <div class="small text-muted">{{ $latestEnrollment->program_name }}</div>
        <div class="d-grid gap-2 mt-3 small">
          <div><span class="text-muted">School year:</span> <strong>{{ $latestEnrollment->school_year }}</strong></div>
          <div><span class="text-muted">Milk consent:</span> <strong>{{ $latestEnrollment->milk_consent }}</strong></div>
          <div><span class="text-muted">Previous beneficiary:</span> <strong>{{ $latestEnrollment->previous_sbfp_beneficiary }}</strong></div>
          @if(auth()->user()?->isSchoolAdmin())
            <div><span class="text-muted">4Ps status:</span> <strong>{{ $latestEnrollment->four_ps_status }}</strong></div>
          @endif
        </div>
      </div>
    @endif

    <div class="nl-card side-card mb-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="metric-icon-large" style="width: 30px; height: 30px;"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v18"/><path d="M10 3v6a4 4 0 0 1-8 0V3"/><path d="M18 3v18"/></svg></div>
        <div class="panel-title mb-0">Last Meal Logged</div>
      </div>
      @if($latestMeal)
        <div class="last-meal-box">
          <div class="d-flex justify-content-between gap-2 flex-wrap">
            <span class="meal-type-pill">{{ $latestMeal->meal_type }}</span>
            <span class="small text-muted">{{ $latestMeal->served_at?->format('Y-m-d') }} &middot; {{ $latestMeal->served_at?->format('h:i A') }}</span>
          </div>
          <div class="fw-bold mt-3">{{ $latestMealItems }}</div>
        </div>
        <a href="#mealHistory" class="small fw-bold text-decoration-none d-inline-flex align-items-center gap-1 mt-3">View full meal history <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
      @else
        <div class="text-muted">No meals recorded yet.</div>
      @endif
    </div>

    <div class="nl-card side-card">
      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="metric-icon-large red" style="width: 30px; height: 30px;">!</div>
        <div class="panel-title mb-0">Allergies</div>
      </div>
      <div class="d-grid gap-2">
        @forelse($allergies as $allergy)
          <div class="allergy-row"><span class="tiny-dot"></span>{{ $allergy }}</div>
        @empty
          <div class="text-muted">Not recorded</div>
        @endforelse
      </div>
      @if(auth()->user()?->isSchoolAdmin())
        <div class="small text-muted mt-3">Edit allergy records from the child edit page.</div>
        <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-primary btn-sm nl-btn mt-2">Edit Child</a>
      @endif
    </div>
  </div>
</div>

@include('students.partials.nutrition-plan')

<div class="nl-card compact-form-card" id="mealHistory">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <div class="panel-title">Meal History</div>
      <div class="panel-subtitle">Filter by meal type, date range, or food name.</div>
    </div>
    <a href="{{ route('students.show', $student) }}#mealHistory" class="small fw-bold text-decoration-none">Clear filters</a>
  </div>
  <form method="GET" action="{{ route('students.show', $student) }}#mealHistory" class="history-filters">
    <div>
      <label class="form-label small mb-1">Meal Type</label>
      <select name="meal_type" class="form-select">
        @foreach(['All','Breakfast','Lunch','Snack','Dinner'] as $type)
          <option value="{{ $type }}" @selected(($mealFilters['meal_type'] ?? 'All') === $type)>{{ $type }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="form-label small mb-1">From</label>
      <input type="date" name="meal_from" value="{{ $mealFilters['meal_from'] ?? '' }}" class="form-control">
    </div>
    <div>
      <label class="form-label small mb-1">To</label>
      <input type="date" name="meal_to" value="{{ $mealFilters['meal_to'] ?? '' }}" class="form-control">
    </div>
    <div class="flex-grow-1">
      <label class="form-label small mb-1">Food</label>
      <input type="search" name="meal_q" value="{{ $mealFilters['meal_q'] ?? '' }}" class="form-control" placeholder="Search meal items..." list="mealHistorySuggestions">
      <datalist id="mealHistorySuggestions">
        @foreach(($mealSearchSuggestions ?? collect()) as $foodName)
          <option value="{{ $foodName }}"></option>
        @endforeach
      </datalist>
    </div>
    <button class="btn btn-primary btn-sm nl-btn">Apply</button>
  </form>

  @if(isset($mealHistory) && $mealHistory->count())
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Date/Time</th><th>Type</th><th>Food Meal</th></tr></thead>
        <tbody>
          @foreach($mealHistory as $rm)
            <tr>
              <td>{{ $rm->served_at?->format('Y-m-d H:i') }}</td>
              <td>{{ $rm->meal_type }}</td>
              <td>
                @foreach($rm->items as $it)
                  <span class="badge bg-light text-dark me-1">{{ $it->food?->name }} x{{ $it->quantity }}</span>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-3">
      {{ $mealHistory->fragment('mealHistory')->links() }}
    </div>
  @else
    <div class="text-muted">No meals match the current filters.</div>
  @endif
</div>

<script>
  (function(){
    const labels = @json($chartLabels ?? []);
    const bmi = @json($chartBmi ?? []);
    const recordCounts = @json($chartRecordCounts ?? []);
    const risk = @json($isRisk);
    const ctx = document.getElementById('studentBmiChart');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'BMI',
          data: bmi,
          borderColor: risk ? '#ef2b24' : '#0b3b82',
          backgroundColor: risk ? 'rgba(239,43,36,.08)' : 'rgba(11,59,130,.08)',
          tension: .28,
          borderWidth: 2.5,
          pointRadius: 3,
          pointHoverRadius: 5,
          fill: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
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
          x: { ticks: { color: '#7c8bad' }, grid: { color: 'rgba(8,40,88,.06)' } },
          y: { ticks: { color: '#7c8bad' }, grid: { color: 'rgba(8,40,88,.06)' }, suggestedMin: 12, suggestedMax: 22 }
        }
      }
    });
  })();

  (function(){
    const filters = document.getElementById('growthRecordFilters');
    if (!filters) return;

    const search = filters.querySelector('[data-growth-search]');
    const status = filters.querySelector('[data-growth-status]');
    const from = filters.querySelector('[data-growth-from]');
    const to = filters.querySelector('[data-growth-to]');
    const rows = Array.from(document.querySelectorAll('[data-growth-row]'));
    const empty = document.querySelector('[data-growth-empty]');

    function applyFilters() {
      const term = (search?.value || '').trim().toLowerCase();
      const selectedStatus = status?.value || '';
      const dateFrom = from?.value || '';
      const dateTo = to?.value || '';
      let visible = 0;

      rows.forEach(function(row) {
        const rowDate = row.dataset.date || '';
        const matchesSearch = term === '' || (row.dataset.search || '').includes(term);
        const matchesStatus = selectedStatus === '' || row.dataset.status === selectedStatus;
        const matchesFrom = dateFrom === '' || rowDate >= dateFrom;
        const matchesTo = dateTo === '' || rowDate <= dateTo;
        const show = matchesSearch && matchesStatus && matchesFrom && matchesTo;

        row.hidden = !show;
        if (show) visible++;
      });

      if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
    }

    [search, status, from, to].forEach(function(control) {
      control?.addEventListener('input', applyFilters);
      control?.addEventListener('change', applyFilters);
    });

    applyFilters();
  })();
</script>
@endsection
