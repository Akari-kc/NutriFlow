@extends('layouts.app')
@section('page-title', 'Student Import')
@section('content')
<style>
  .import-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.1rem; }
  .import-title { color: #082858; font-weight: 900; margin: 0; font-size: 1.25rem; }
  .import-subtitle { color: #7c8bad; font-size: .84rem; margin-top: .3rem; max-width: 720px; }
  .import-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr); gap: 1rem; align-items: start; }
  .import-panel { padding: 1.25rem; }
  .panel-title { color: #082858; font-weight: 900; margin-bottom: .25rem; }
  .panel-subtitle { color: #7c8bad; font-size: .8rem; }
  .column-list { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: .8rem; }
  .column-chip { border: 1px solid #dbe3ef; background: #f7f9fc; color: #30466f; border-radius: 8px; padding: .3rem .55rem; font-size: .78rem; font-weight: 800; }
  .column-chip.required { background: #eef4fb; border-color: #cdddf2; color: #0b3b82; }
  .import-steps { display: grid; gap: .7rem; margin-top: .9rem; }
  .import-step { display: flex; gap: .7rem; align-items: flex-start; color: #30466f; font-size: .86rem; }
  .step-number { width: 24px; height: 24px; border-radius: 8px; background: #0b3b82; color: #fff; display: grid; place-items: center; font-size: .76rem; font-weight: 900; flex: 0 0 auto; }
  .result-box { border-radius: 10px; padding: .9rem; margin-bottom: 1rem; }
  .result-box.success { background: #e7f8ee; border: 1px solid #bfe8d0; color: #057243; }
  .result-box.blocked { background: #fff5f4; border: 1px solid #ffc9c4; color: #b42318; }
  .result-stats { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .65rem; }
  .result-stat { background: rgba(255,255,255,.72); border-radius: 8px; padding: .35rem .55rem; font-size: .78rem; font-weight: 900; }
  .error-list { margin: .65rem 0 0; padding-left: 1.15rem; font-size: .82rem; }
  .template-table th { color: #7c8bad; font-size: .76rem; }
  .template-table td { color: #30466f; font-size: .8rem; }
  @media (max-width: 980px) {
    .import-grid { grid-template-columns: 1fr; }
    .import-head { display: block; }
    .import-head .btn { margin-top: .8rem; }
  }
</style>

<div class="import-head">
  <div>
    <h1 class="import-title">Student Batch Import</h1>
    <div class="import-subtitle">Use this for a clean client setup. The CSV creates or updates children by LRN / Student ID, creates grade sections, and saves growth measurements when height and weight columns are present.</div>
  </div>
  <a href="{{ route('student-import.template') }}" class="btn btn-outline-primary nl-btn">Download CSV Template</a>
</div>

@if($result)
  @php $summary = $result['summary'] ?? []; @endphp
  <div class="result-box {{ ($result['imported'] ?? false) ? 'success' : 'blocked' }}">
    <div class="fw-bold">{{ ($result['imported'] ?? false) ? 'Import completed' : 'Import blocked' }}</div>
    <div class="small mt-1">
      {{ ($result['imported'] ?? false) ? 'The uploaded file was saved.' : 'No rows were saved. Fix the CSV errors and upload again.' }}
    </div>
    <div class="result-stats">
      <span class="result-stat">{{ number_format($summary['rows'] ?? 0) }} rows read</span>
      <span class="result-stat">{{ number_format($summary['created'] ?? 0) }} students created</span>
      <span class="result-stat">{{ number_format($summary['updated'] ?? 0) }} students updated</span>
      <span class="result-stat">{{ number_format($summary['measurements'] ?? 0) }} growth records saved</span>
    </div>
    @if(!empty($result['errors']))
      <ul class="error-list">
        @foreach($result['errors'] as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    @endif
  </div>
@endif

<div class="import-grid">
  <div class="nl-card import-panel">
    <div class="panel-title">Upload Student CSV</div>
    <div class="panel-subtitle">A batch succeeds only when every row passes validation, so the catalog does not end up half-imported.</div>

    <form method="POST" action="{{ route('student-import.import') }}" enctype="multipart/form-data" class="mt-3">
      @csrf
      <label class="form-label">CSV File</label>
      <input type="file" name="csv" class="form-control" accept=".csv,text/csv,text/plain" required>
      @error('csv')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
      <div class="form-text">Maximum file size: 5 MB. Use the template if you are unsure about column names.</div>
      <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-primary nl-btn">Import Students</button>
      </div>
    </form>

    <div class="mt-4">
      <div class="panel-title mb-0">CSV Columns</div>
      <div class="panel-subtitle">Required columns must be present exactly as shown.</div>
      <div class="column-list">
        @foreach($requiredColumns as $column)
          <span class="column-chip required">{{ $column }}</span>
        @endforeach
        @foreach($optionalColumns as $column)
          <span class="column-chip">{{ $column }}</span>
        @endforeach
      </div>
    </div>

    <div class="table-responsive mt-4">
      <table class="table table-sm template-table mb-0">
        <thead><tr><th>Column</th><th>Purpose</th></tr></thead>
        <tbody>
          <tr><td>lrn</td><td>Unique student key. Existing matching students are updated.</td></tr>
          <tr><td>grade, section</td><td>Creates the child grade/section assignment and adds the section if new.</td></tr>
          <tr><td>allergies</td><td>Use comma-separated values, such as Milk, Peanuts.</td></tr>
          <tr><td>measured_at, weight_kg, height_cm</td><td>Optional growth record. Repeat the same LRN on another row for another measurement date.</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="nl-card import-panel">
    <div class="panel-title">Recommended Workflow</div>
    <div class="panel-subtitle">For a dataless client install, start here before meal logs or schedules.</div>
    <div class="import-steps">
      <div class="import-step"><span class="step-number">1</span><span>Download the template and fill one row per child. Add repeated rows for extra growth records.</span></div>
      <div class="import-step"><span class="step-number">2</span><span>Use LRN / Student ID consistently. It is the matching key for updates and measurements.</span></div>
      <div class="import-step"><span class="step-number">3</span><span>Upload the completed CSV. If any row has errors, the system blocks the batch and shows what to fix.</span></div>
      <div class="import-step"><span class="step-number">4</span><span>After a successful import, review Children and Reports to confirm the roster and BMI records.</span></div>
    </div>
  </div>
</div>
@endsection
