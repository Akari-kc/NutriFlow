@extends('layouts.app')
@section('page-title', 'Meals')
@section('content')
<div class="d-flex align-items-center gap-2 mb-2">
  <a href="{{ route('meals.index') }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
  <h4 class="mb-0">Batch Meal Logging</h4>
</div>
{{-- Removed explanatory text; filter controls will be shown below the student list --}}
<div class="card nl-card p-3">
  <form method="POST" action="{{ route('meals.batch.store') }}">
    @csrf
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <label class="form-label">Meal Type</label>
        <select name="meal_type" class="form-select" required>
          <option value="">-- Select --</option>
          @foreach(['Breakfast','Lunch','Snack','Dinner'] as $t)
            <option value="{{ $t }}" @selected(old('meal_type')==$t)>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Served At</label>
        <input type="datetime-local" name="served_at" value="{{ old('served_at', now('Asia/Manila')->format('Y-m-d\\TH:i')) }}" max="{{ now('Asia/Manila')->format('Y-m-d\\TH:i') }}" class="form-control" required />
      </div>
    </div>

    <div class="mb-2 d-flex justify-content-between align-items-center">
  <div class="fw-semibold">Meal Items (applied to all selected students)</div>
      <button type="button" class="btn btn-outline-success btn-sm nl-btn" id="addItem">Add Item</button>
    </div>
    <div id="items">
      @php
        $oldItems = old('items', [["food_id"=>null,"quantity"=>1]]);
      @endphp
      @foreach($oldItems as $idx => $it)
        <div class="row g-2 align-items-end mb-2">
          <div class="col-md-6">
            <label class="form-label">Meal</label>
            <select name="items[{{ $idx }}][food_id]" class="form-select" required>
              <option value="">-- Select --</option>
              @foreach($foods as $f)
                <option value="{{ $f->id }}" @selected(($it['food_id'] ?? null) == $f->id)>{{ $f->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Quantity</label>
            <input type="number" step="1" min="1" name="items[{{ $idx }}][quantity]" value="{{ (int)($it['quantity'] ?? 1) }}" class="form-control" required />
          </div>
        </div>
      @endforeach
    </div>

    <div class="mt-3">
      <div class="d-flex justify-content-between align-items-end mb-2 flex-wrap gap-3">
        <div class="fw-semibold">Select Students (first 30)</div>
        <div class="d-flex gap-3 align-items-end flex-wrap">
          <div>
            <label class="form-label">Search</label>
            <input id="filterSearch" class="form-control" value="{{ $search ?? '' }}" placeholder="Student name" list="batchStudentSuggestions">
            <datalist id="batchStudentSuggestions">
              @foreach(($studentSuggestions ?? collect()) as $studentName)
                <option value="{{ $studentName }}"></option>
              @endforeach
            </datalist>
          </div>
          <div>
            <label class="form-label">Grade</label>
            <select id="filterClass" class="form-select">
              <option value="">All</option>
              @foreach(($classes ?? []) as $c)
                <option value="{{ $c }}" @selected(request('class_name')==$c)>{{ $c }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Section</label>
            <select id="filterSection" class="form-select" data-current-section="{{ request('section') }}">
              <option value="">All</option>
              @foreach(($sections ?? []) as $sec)
                <option value="{{ $sec }}" @selected(request('section')==$sec)>{{ $sec }}</option>
              @endforeach
            </select>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm nl-btn" type="button" onclick="applyFilter()">Filter</button>
            <a href="{{ route('meals.batch') }}" class="btn btn-outline-secondary btn-sm nl-btn">Clear</a>
          </div>
        </div>
      </div>
      <div class="table-responsive" style="max-height: 420px; overflow:auto;">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>
                Serve
                <input type="checkbox" id="selectAllPage" title="Select all visible students" class="form-check-input ms-1 align-middle" />
              </th>
              <th>Name</th>
              <th>Gender</th>
              <th>Section</th>
              <th>Class</th>
            </tr>
          </thead>
          <tbody>
            @foreach($students as $s)
              <tr>
                <td><input type="checkbox" class="student-check" name="served_students[]" value="{{ $s->id }}" /></td>
                <td>{{ $s->name }}</td>
                <td>{{ $s->gender }}</td>
                <td>{{ $s->section }}</td>
                <td>{{ $s->class_name }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="small text-muted mt-2"><span id="selectedCount">0</span> students selected across filters.</div>
      {{ $students->links() }}
    </div>

    <div id="persistedSelections"></div>
    <div class="mt-3">
      <button class="btn btn-success nl-btn">Log Meals</button>
      <a href="{{ route('meals.index') }}" class="btn btn-outline-secondary nl-btn">Cancel</a>
    </div>
  </form>
</div>

<script>
  const foods = @json($foods->map(fn($f)=>['id'=>$f->id,'name'=>$f->name]));
  const itemsDiv = document.getElementById('items');
  document.getElementById('addItem').addEventListener('click', () => addRow());
  function addRow(){
    const idx = itemsDiv.querySelectorAll('.row').length;
    const row = document.createElement('div');
    row.innerHTML = rowHtml(idx);
    itemsDiv.appendChild(row.firstElementChild);
  }
  function rowHtml(idx){
    return `
    <div class=\"row g-2 align-items-end mb-2\">
      <div class=\"col-md-6\">
  <label class=\"form-label\">Meal</label>
        <select name=\"items[${idx}][food_id]\" class=\"form-select\" required>
          <option value=\"\">-- Select --</option>
          ${foods.map(f=>`<option value=\"${f.id}\">${f.name}</option>`).join('')}
        </select>
      </div>
      <div class=\"col-md-3\">
  <label class=\"form-label\">Quantity</label>
  <input type=\"number\" step=\"1\" min=\"1\" name=\"items[${idx}][quantity]\" value=\"1\" class=\"form-control\" required />
      </div>
    </div>`;
  }
  const batchGradeSections = @json(($gradeSections ?? collect())->map(fn($sections) => $sections->values()));
  const selectedStorageKey = 'nutriflow.batchMeal.selectedStudents';
  const selectedIds = new Set(JSON.parse(localStorage.getItem(selectedStorageKey) || '[]').map(String));

  function persistSelectedIds() {
    localStorage.setItem(selectedStorageKey, JSON.stringify(Array.from(selectedIds)));
  }

  function updateSelectedCount() {
    const selectedCount = document.getElementById('selectedCount');
    if (selectedCount) selectedCount.textContent = selectedIds.size;
  }

  function syncVisibleChecks() {
    document.querySelectorAll('.student-check').forEach(function(box) {
      box.checked = selectedIds.has(String(box.value));
    });
  }

  function appendPersistedSelections() {
    const container = document.getElementById('persistedSelections');
    if (!container) return;
    container.innerHTML = '';
    selectedIds.forEach(function(id) {
      const visibleBox = document.querySelector(`.student-check[value="${CSS.escape(id)}"]`);
      if (visibleBox) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'served_students[]';
      input.value = id;
      container.appendChild(input);
    });
  }

  (function selectAllInit(){
    const master = document.getElementById('selectAllPage');
    if (!master) return;
    const boxes = () => Array.from(document.querySelectorAll('.student-check'));
    function refreshMaster(){
      const arr = boxes();
      const total = arr.length;
      const checked = arr.filter(b=>b.checked).length;
      master.checked = total > 0 && checked === total;
      master.indeterminate = checked > 0 && checked < total;
    }
    master.addEventListener('change', ()=>{
      const val = master.checked;
      boxes().forEach(cb => {
        cb.checked = val;
        if (val) selectedIds.add(String(cb.value));
        else selectedIds.delete(String(cb.value));
      });
      persistSelectedIds();
      updateSelectedCount();
      master.indeterminate = false;
    });
    boxes().forEach(cb => cb.addEventListener('change', function(){
      if (cb.checked) selectedIds.add(String(cb.value));
      else selectedIds.delete(String(cb.value));
      persistSelectedIds();
      updateSelectedCount();
      refreshMaster();
    }));
    syncVisibleChecks();
    updateSelectedCount();
    refreshMaster();
  })();

  function syncBatchSections() {
    const cls = document.getElementById('filterClass');
    const sec = document.getElementById('filterSection');
    if (!cls || !sec) return;
    const selected = sec.value || sec.dataset.currentSection || '';
    const sections = cls.value ? (batchGradeSections[cls.value] || []) : Object.values(batchGradeSections).flat();
    const uniqueSections = Array.from(new Set(sections)).sort();
    sec.innerHTML = '<option value="">All</option>';
    uniqueSections.forEach(function(section) {
      const option = document.createElement('option');
      option.value = section;
      option.textContent = section;
      sec.appendChild(option);
    });
    if (selected && uniqueSections.includes(selected)) {
      sec.value = selected;
    }
  }
  document.getElementById('filterClass')?.addEventListener('change', function(){
    const sec = document.getElementById('filterSection');
    if (sec) sec.dataset.currentSection = '';
    syncBatchSections();
  });
  syncBatchSections();

  function applyFilter(){
    document.querySelectorAll('.student-check').forEach(cb => {
      if (cb.checked) selectedIds.add(String(cb.value));
      else selectedIds.delete(String(cb.value));
    });
    persistSelectedIds();
    const cls = document.getElementById('filterClass').value;
    const sec = document.getElementById('filterSection').value;
    const q = document.getElementById('filterSearch').value.trim();
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (cls) params.set('class_name', cls);
    if (sec) params.set('section', sec);
    const url = `{{ route('meals.batch') }}` + (params.toString() ? `?${params.toString()}` : '');
    window.location.href = url;
  }
  document.querySelector('form[action="{{ route('meals.batch.store') }}"]')?.addEventListener('submit', function(){
    document.querySelectorAll('.student-check').forEach(cb => {
      if (cb.checked) selectedIds.add(String(cb.value));
      else selectedIds.delete(String(cb.value));
    });
    appendPersistedSelections();
  });
</script>
@endsection
