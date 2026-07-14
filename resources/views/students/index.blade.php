@extends('layouts.app')
@section('page-title', 'Children')
@section('content')
<style>
  .children-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.2rem; }
  .children-title { font-size: 1.25rem; font-weight: 800; margin: 0; color: #082858; }
  .children-subtitle { color: #7c8bad; font-size: .82rem; margin-top: .28rem; }
  .add-child-btn { min-width: 114px; height: 38px; box-shadow: 0 6px 14px rgba(8,40,88,.24); }
  .children-toolbar { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; max-width: 900px; }
  .mode-tabs { background: #e9edf4; border-radius: 9px; padding: .2rem; display: inline-flex; gap: .15rem; }
  .mode-tab { border: 0; background: transparent; color: #526894; border-radius: 8px; padding: .48rem .85rem; font-size: .84rem; font-weight: 700; text-decoration: none; }
  .mode-tab.active { background: #fff; color: #082858; box-shadow: 0 1px 4px rgba(8,40,88,.08); }
  .student-search { width: 320px; max-width: 100%; height: 40px; border: 1px solid #dbe3ef; border-radius: 9px; background: #fff; color: #082858; padding: 0 .9rem; font-size: .86rem; flex: 0 1 320px; }
  .children-select { width: 126px; height: 40px; border: 1px solid #dbe3ef; border-radius: 9px; color: #082858; font-size: .84rem; flex: 0 0 126px; }
  .children-select.risk-select { width: 140px; flex-basis: 140px; }
  .children-card { overflow: hidden; }
  .children-table { margin: 0; }
  .children-table thead th { background: #fbfcfe; color: #465a83; font-size: .78rem; font-weight: 700; padding: .9rem 1.2rem; border-bottom: 1px solid #e4e9f2; }
  .children-table tbody td { color: #30466f; font-size: .86rem; padding: .76rem 1.2rem; border-bottom: 1px solid #edf1f6; vertical-align: middle; }
  .student-cell { display: flex; align-items: center; gap: .7rem; font-weight: 800; color: #082858; }
  .student-avatar { width: 29px; height: 29px; border-radius: 50%; display: grid; place-items: center; background: #0b3b82; color: #fff; font-size: .78rem; font-weight: 800; flex: 0 0 auto; }
  .risk-pill { border-radius: 7px; padding: .24rem .5rem; font-size: .72rem; font-weight: 800; }
  .risk-ok { color: #057243; background: #e3f0ea; }
  .risk-warn { color: #b77900; background: #fff4d9; }
  .risk-severe-label { color: #d92d20; background: #fff0ef; }
  .view-pill { border: 0; border-radius: 10px; background: #eef2f8; color: #082858; font-weight: 800; font-size: .78rem; padding: .38rem .85rem; text-decoration: none; }
  .table-footer { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .9rem 1.2rem; color: #7c8bad; font-size: .8rem; }
  .mini-pagination { display: flex; align-items: center; gap: .4rem; }
  .mini-pagination a, .mini-pagination span { min-width: 28px; height: 28px; display: grid; place-items: center; border-radius: 9px; text-decoration: none; color: #526894; font-weight: 700; }
  .mini-pagination .active { background: #0b3b82; color: #fff; }
  .mini-pagination .pagination-gap { color: #8a9abc; min-width: 18px; }
  .grade-list { display: grid; gap: .65rem; }
  .grade-card { border: 1px solid #e4e9f2; border-radius: 10px; background: #fff; box-shadow: 0 3px 10px rgba(8,40,88,.05); overflow: hidden; }
  .grade-card summary, .section-card summary { cursor: pointer; list-style: none; }
  .grade-card summary::-webkit-details-marker, .section-card summary::-webkit-details-marker { display: none; }
  .grade-summary { display: flex; align-items: center; gap: .8rem; padding: 1rem 1.25rem; font-weight: 800; color: #082858; }
  .chev { color: #c78200; font-size: 1rem; width: 14px; text-align: center; }
  details[open] > summary .chev { transform: rotate(90deg); }
  .count-chip { background: #eef4fb; color: #7082a6; border-radius: 8px; font-size: .76rem; font-weight: 700; padding: .24rem .55rem; }
  .section-stack { border-top: 1px solid #edf1f6; padding: .75rem 1.25rem 1rem; display: grid; gap: .45rem; }
  .add-section-row { display: flex; align-items: center; gap: .5rem; padding: .45rem .15rem .8rem; border-bottom: 1px solid #edf1f6; margin-bottom: .35rem; }
  .add-section-input { max-width: 180px; height: 36px; border: 1px solid #dbe3ef; border-radius: 9px; color: #082858; font-size: .84rem; padding: 0 .75rem; }
  .add-section-row .btn { height: 36px; }
  .section-card { border-radius: 9px; }
  .section-summary { display: flex; align-items: center; gap: .65rem; padding: .55rem .15rem; color: #082858; font-weight: 800; font-size: .88rem; }
  .section-students { display: grid; gap: .45rem; padding: .35rem 0 .65rem 2rem; }
  .section-student { display: flex; align-items: center; gap: .85rem; border: 1px solid #e8edf5; border-radius: 10px; padding: .8rem 1rem; color: #082858; text-decoration: none; }
  .section-student:hover { background: #fbfcfe; }
  .student-meta { color: #7c8bad; font-size: .78rem; margin-top: .18rem; }
  @media (max-width: 760px) {
    .children-head { display: block; }
    .add-child-btn { margin-top: .9rem; width: 100%; }
    .children-toolbar { max-width: none; }
    .student-search { width: 100%; flex-basis: 100%; }
    .children-select, .children-select.risk-select { flex: 1 1 150px; width: auto; min-width: 150px; }
    .table-footer { display: block; }
    .mini-pagination { margin-top: .75rem; flex-wrap: wrap; }
  }
</style>

@php
  $riskOptions = ['All' => 'All Risk Levels', 'Low' => 'Low Risk', 'Moderate' => 'Moderate Risk', 'Severe' => 'Severe Risk'];
  $activeMode = $mode ?? 'all';
  $baseParams = request()->except(['mode', 'page']);
  $initials = fn($name) => collect(explode(' ', trim($name)))->filter()->map(fn($p) => strtoupper(substr($p, 0, 1)))->take(2)->implode('');
  $riskInfo = function ($student) {
      $status = \App\Support\ChildBmiClassifier::classifyForStudent($student, $student->latestMeasurement);
      if ($status === \App\Support\ChildBmiClassifier::SEVERELY_UNDERNOURISHED) {
          return ['Severe Risk', 'risk-severe-label'];
      }
      if ($status === \App\Support\ChildBmiClassifier::UNDERNOURISHED) {
          return ['Moderate Risk', 'risk-warn'];
      }
      if (in_array($status, [\App\Support\ChildBmiClassifier::NO_MEASUREMENT, \App\Support\ChildBmiClassifier::NEEDS_REVIEW], true)) {
          return ['Needs Review', 'risk-warn'];
      }

      return ['Low Risk', 'risk-ok'];
  };
@endphp

<div class="children-head">
  <div>
    <h1 class="children-title">Children</h1>
    <div class="children-subtitle">{{ number_format($totalStudents ?? 0) }} students enrolled</div>
  </div>
  <a href="{{ route('students.create') }}" class="btn btn-primary nl-btn add-child-btn">+ Add Child</a>
</div>

<form method="GET" class="children-toolbar" id="childrenFilters">
  <div class="mode-tabs">
    <a class="mode-tab {{ $activeMode === 'all' ? 'active' : '' }}" href="{{ route('students.index', array_merge($baseParams, ['mode' => 'all'])) }}">All Students</a>
    <a class="mode-tab {{ $activeMode === 'grade' ? 'active' : '' }}" href="{{ route('students.index', array_merge($baseParams, ['mode' => 'grade'])) }}">By Grade</a>
  </div>
  <input type="hidden" name="mode" value="{{ $activeMode }}">
  <input class="student-search" type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search students..." list="studentSearchSuggestions">
  <datalist id="studentSearchSuggestions">
    @foreach(($searchSuggestions ?? collect()) as $suggestion)
      <option value="{{ $suggestion }}"></option>
    @endforeach
  </datalist>
  <select class="form-select children-select" name="grade" onchange="this.form.submit()">
    <option value="">All Grades</option>
    @foreach(($classes ?? []) as $grade)
      <option value="{{ $grade }}" @selected(($selectedGrade ?? '') === $grade)>{{ $grade }}</option>
    @endforeach
  </select>
  <select class="form-select children-select risk-select" name="risk" onchange="this.form.submit()">
    @foreach($riskOptions as $value => $label)
      <option value="{{ $value }}" @selected(($selectedRisk ?? 'All') === $value)>{{ $label }}</option>
    @endforeach
  </select>
</form>

@if($activeMode === 'grade')
  <div class="grade-list">
    @forelse(($classes ?? collect()) as $grade)
      @php
        $gradeStudents = ($groupedStudents ?? collect())->get($grade, collect());
        $sectionStudentMap = ($studentsByGradeSection ?? collect())->get($grade, collect());
        $sectionsByGrade = collect(($gradeSections ?? collect())->get($grade, []));
        if ($sectionsByGrade->isEmpty()) {
            $sectionsByGrade = $sectionStudentMap->keys()->filter()->values();
        }
      @endphp
      <details class="grade-card">
        <summary class="grade-summary">
          <span class="chev">&rsaquo;</span>
          <span>{{ $grade }}</span>
          <span class="count-chip">{{ $gradeStudents->count() }} {{ Str::plural('student', $gradeStudents->count()) }}</span>
        </summary>
        <div class="section-stack">
          <form method="POST" action="{{ route('students.sections.store') }}" class="add-section-row">
            @csrf
            <input type="hidden" name="class_name" value="{{ $grade }}">
            <input class="add-section-input" name="section" placeholder="Section E" required>
            <button class="btn btn-outline-primary btn-sm nl-btn" type="submit">+ Add Section</button>
          </form>
          @foreach($sectionsByGrade as $sectionName)
            @php
              $sectionStudents = $sectionStudentMap->get($sectionName, collect());
            @endphp
            <details class="section-card">
              <summary class="section-summary">
                <span class="chev">&rsaquo;</span>
                <span>Section {{ $sectionName }}</span>
                <span class="text-muted fw-normal">({{ $sectionStudents->count() }})</span>
              </summary>
              <div class="section-students">
                @forelse($sectionStudents as $s)
                  @php
                    [$riskLabel, $riskClass] = $riskInfo($s);
                  @endphp
                  <a href="{{ route('students.show', $s) }}" class="section-student">
                    <span class="student-avatar">{{ $initials($s->name) }}</span>
                    <span class="flex-grow-1">
                      <span class="fw-bold">{{ $s->name }}</span>
                      <span class="student-meta d-block">{{ $s->gender ?? '-' }} &middot; {{ $s->birthdate?->format('Y-m-d') ?? '-' }}</span>
                    </span>
                    <span class="risk-pill {{ $riskClass }}">{{ $riskLabel }}</span>
                    <span class="view-pill">View</span>
                  </a>
                @empty
                  <div class="text-muted small ps-1">No students in this section yet.</div>
                @endforelse
              </div>
            </details>
          @endforeach
        </div>
      </details>
    @empty
      <div class="nl-card p-4 text-muted">No students match the current filters.</div>
    @endforelse
  </div>
@else
  <div class="nl-card children-card">
    @if(isset($students) && $students->count())
      <div class="table-responsive">
        <table class="table children-table align-middle">
          <thead>
            <tr>
              <th>Student</th>
              <th>Section</th>
              <th>Class</th>
              <th>Risk</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($students as $s)
              @php
                [$riskLabel, $riskClass] = $riskInfo($s);
              @endphp
              <tr>
                <td>
                  <div class="student-cell">
                    <span class="student-avatar">{{ $initials($s->name) }}</span>
                    <span>{{ $s->name }}</span>
                  </div>
                </td>
                <td>{{ $s->section }}</td>
                <td>{{ $s->class_name }}</td>
                <td><span class="risk-pill {{ $riskClass }}">{{ $riskLabel }}</span></td>
                <td><a href="{{ route('students.show', $s) }}" class="view-pill">View</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="table-footer">
        <div>Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} students</div>
        <div class="mini-pagination">
          @php
            $currentPage = $students->currentPage();
            $lastPage = $students->lastPage();
            $pages = collect([1, $lastPage, $currentPage - 1, $currentPage, $currentPage + 1])
                ->filter(fn($page) => $page >= 1 && $page <= $lastPage)
                ->unique()
                ->sort()
                ->values();
            $previousPage = null;
          @endphp
          @if($students->onFirstPage())
            <span aria-hidden="true">&lsaquo;</span>
          @else
            <a href="{{ $students->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
          @endif
          @foreach($pages as $page)
            @if($previousPage && $page > $previousPage + 1)
              <span class="pagination-gap">...</span>
            @endif
            <a class="{{ $currentPage === $page ? 'active' : '' }}" href="{{ $students->url($page) }}">{{ $page }}</a>
            @php
              $previousPage = $page;
            @endphp
          @endforeach
          @if($students->hasMorePages())
            <a href="{{ $students->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
          @else
            <span aria-hidden="true">&rsaquo;</span>
          @endif
        </div>
      </div>
    @else
      <div class="p-4 text-muted">No students match the current filters.</div>
    @endif
  </div>
@endif

<script>
  document.getElementById('childrenFilters')?.addEventListener('submit', function(){
    this.querySelectorAll('input, select').forEach(function(control){
      if (!control.value) control.disabled = true;
    });
  });
</script>
@endsection
