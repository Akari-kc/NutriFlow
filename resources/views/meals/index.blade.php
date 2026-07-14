@extends('layouts.app')
@section('page-title', 'Meal Logs')
@section('content')
<style>
  .meals-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
  .meals-title { font-size: 1.25rem; font-weight: 900; color: #082858; margin: 0; }
  .meals-subtitle { color: #7c8bad; font-size: .82rem; margin-top: .25rem; }
  .meal-filter-card { padding: 1rem; margin-bottom: 1rem; }
  .meal-filters { display: grid; grid-template-columns: repeat(6, minmax(120px, 1fr)); gap: .75rem; align-items: end; }
  .meal-filters .wide { grid-column: span 2; }
  .meal-table { margin: 0; }
  .meal-table thead th { background: #fbfcfe; color: #465a83; font-size: .78rem; font-weight: 800; padding: .9rem 1rem; border-bottom: 1px solid #e4e9f2; }
  .meal-table tbody td { color: #30466f; font-size: .86rem; padding: .85rem 1rem; border-bottom: 1px solid #edf1f6; vertical-align: middle; }
  .meal-student { font-weight: 900; color: #082858; }
  .meal-meta { display: block; color: #7c8bad; font-size: .76rem; margin-top: .15rem; }
  .meal-type-pill { border-radius: 8px; padding: .25rem .55rem; background: #eef4fb; color: #0b3b82; font-weight: 800; font-size: .76rem; }
  .food-chip { display: inline-flex; align-items: center; border-radius: 8px; background: #f4f6fa; color: #30466f; padding: .28rem .5rem; margin: .12rem; font-size: .76rem; font-weight: 700; }
  .meal-footer { padding: .9rem 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; color: #7c8bad; font-size: .8rem; }
  @media (max-width: 1100px) {
    .meal-filters { grid-template-columns: repeat(3, minmax(160px, 1fr)); }
    .meal-filters .wide { grid-column: span 1; }
  }
  @media (max-width: 720px) {
    .meals-head { display: block; }
    .meals-head .btn { margin-top: .8rem; width: 100%; }
    .meal-filters { grid-template-columns: 1fr; }
    .meal-footer { display: block; }
  }
</style>

<div class="meals-head">
  <div>
    <h1 class="meals-title">Meal Logs</h1>
    <div class="meals-subtitle">{{ number_format($meals->total() ?? 0) }} completed schedule logs found</div>
  </div>
</div>

<div class="nl-card meal-filter-card">
  <form method="GET" class="meal-filters" id="mealFilters">
    <div>
      <label class="form-label">From Date</label>
      <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
    </div>
    <div>
      <label class="form-label">To Date</label>
      <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
    </div>
    <div>
      <label class="form-label">From Time</label>
      <input type="time" name="time_from" value="{{ $filters['time_from'] ?? '' }}" class="form-control">
    </div>
    <div>
      <label class="form-label">To Time</label>
      <input type="time" name="time_to" value="{{ $filters['time_to'] ?? '' }}" class="form-control">
    </div>
    <div class="wide">
      <label class="form-label">Search Student</label>
      <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" list="mealStudentSuggestions" placeholder="Type a student name...">
      <datalist id="mealStudentSuggestions">
        @foreach(($studentSuggestions ?? collect()) as $studentName)
          <option value="{{ $studentName }}"></option>
        @endforeach
      </datalist>
    </div>
    <div>
      <label class="form-label">Grade</label>
      <select name="class_name" class="form-select" id="mealGradeSelect">
        <option value="">All Grades</option>
        @foreach(($classes ?? collect()) as $class)
          <option value="{{ $class }}" @selected(($filters['class_name'] ?? '') === $class)>{{ $class }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="form-label">Section</label>
      <select name="section" class="form-select" id="mealSectionSelect" data-current-section="{{ $filters['section'] ?? '' }}">
        <option value="">All Sections</option>
        @foreach(($sections ?? collect()) as $section)
          <option value="{{ $section }}" @selected(($filters['section'] ?? '') === $section)>{{ $section }}</option>
        @endforeach
      </select>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary nl-btn d-inline-flex align-items-center gap-2" type="submit">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16"/><path d="M7 12h10"/><path d="M10 19h4"/></svg>
        Filter
      </button>
      <a href="{{ route('meals.index') }}" class="btn btn-outline-secondary nl-btn d-inline-flex align-items-center gap-2">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        Clear
      </a>
    </div>
  </form>
</div>

<div class="nl-card">
  @if(isset($meals) && $meals->count())
    <div class="table-responsive">
      <table class="table meal-table align-middle">
        <thead>
          <tr>
            <th>Date/Time</th>
            <th>Student</th>
            <th>Grade</th>
            <th>Section</th>
            <th>Meal</th>
            <th>Foods</th>
          </tr>
        </thead>
        <tbody>
          @foreach($meals as $m)
            <tr>
              <td>
                <strong>{{ $m->served_at?->format('M d, Y') }}</strong>
                <span class="meal-meta">{{ $m->served_at?->format('h:i A') }}</span>
              </td>
              <td>
                <span class="meal-student">{{ $m->student?->name }}</span>
              </td>
              <td>{{ $m->student?->class_name }}</td>
              <td>{{ $m->student?->section }}</td>
              <td><span class="meal-type-pill">{{ $m->meal_type }}</span></td>
              <td>
                @foreach($m->items as $it)
                  <span class="food-chip">{{ $it->food?->name }} x{{ rtrim(rtrim(number_format((float)$it->quantity, 2), '0'), '.') }}</span>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="meal-footer">
      <div>Showing {{ $meals->firstItem() }} to {{ $meals->lastItem() }} of {{ $meals->total() }} meals</div>
      <div>{{ $meals->links() }}</div>
    </div>
  @else
    <div class="p-4 text-muted">No completed schedule meal logs match the current filters.</div>
  @endif
</div>

<script>
  localStorage.removeItem('nutriflow.batchMeal.selectedStudents');
  const mealGradeSections = @json(($gradeSections ?? collect())->map(fn($sections) => $sections->values()));
  const mealGradeSelect = document.getElementById('mealGradeSelect');
  const mealSectionSelect = document.getElementById('mealSectionSelect');

  function syncMealSections() {
    if (!mealGradeSelect || !mealSectionSelect) return;
    const selected = mealSectionSelect.value || mealSectionSelect.dataset.currentSection || '';
    const sections = mealGradeSelect.value ? (mealGradeSections[mealGradeSelect.value] || []) : Object.values(mealGradeSections).flat();
    const uniqueSections = Array.from(new Set(sections)).sort();

    mealSectionSelect.innerHTML = '<option value="">All Sections</option>';
    uniqueSections.forEach(function(section) {
      const option = document.createElement('option');
      option.value = section;
      option.textContent = section;
      mealSectionSelect.appendChild(option);
    });

    if (selected && uniqueSections.includes(selected)) {
      mealSectionSelect.value = selected;
    }
  }
  mealGradeSelect?.addEventListener('change', function(){
    mealSectionSelect.dataset.currentSection = '';
    syncMealSections();
  });
  syncMealSections();

  document.getElementById('mealFilters')?.addEventListener('submit', function(){
    this.querySelectorAll('input, select').forEach(function(control){
      if (!control.value) control.disabled = true;
    });
  });
</script>
@endsection
