@extends('layouts.app')
@section('page-title', 'Meals')
@section('content')
<div class="d-flex align-items-center gap-2 mb-2">
  <a href="{{ route('meals.index') }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
  <h4 class="mb-0">Batch Meal Logging</h4>
</div>
<p class="text-muted small mb-3">Choose a scheduled session to load its planned participants and meals, then record attendance or substitutions. Use an ad hoc log only when no scheduled session applies.</p>
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
<div class="card nl-card p-3">
  <form method="POST" action="{{ route('meals.batch.store') }}">
    @csrf
    <div class="row g-3 mb-3">
      <div class="col-md-5">
        <label class="form-label">Scheduled Feeding Session</label>
        <select name="feeding_schedule_id" class="form-select" id="feedingScheduleSelect">
          <option value="">Ad hoc meal (not scheduled)</option>
          @foreach($schedules as $schedule)
            @php
              $scheduleAlreadyLogged = ($schedule->meals_count ?? 0) > 0;
              $scheduleIsFuture = $schedule->session_date->isFuture();
            @endphp
            <option
              value="{{ $schedule->id }}"
              @selected((string) old('feeding_schedule_id', request('feeding_schedule_id')) === (string) $schedule->id)
              @disabled($scheduleAlreadyLogged || $scheduleIsFuture)
            >
              {{ $schedule->session_date->format('M d, Y') }} · {{ $schedule->batch_name }} · {{ $schedule->meal_type }}
              @if($scheduleAlreadyLogged) · already logged @elseif($scheduleIsFuture) · available on session date @endif
            </option>
          @endforeach
        </select>
        <div class="form-text">Scheduled logs inherit their planned participants and menu. You can remove absentees or record substitutions.</div>
        @if($schedules->isEmpty())
          <div class="form-text text-warning">No unlogged scheduled sessions are available. Add a session in Feeding Schedule first.</div>
        @endif
      </div>
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

    <section class="border rounded-3 p-3 mb-3 bg-light" id="mealAssessmentPanel">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <div class="fw-bold">Prototype Group Nutrition Assessment</div>
          <div class="small text-muted">Uses the actual attendance and meal items currently selected below. It does not save an assessment or replace professional review.</div>
        </div>
        @if($nutritionPlanEnabled ?? false)
          <button type="button" class="btn btn-outline-primary btn-sm nl-btn" id="generateMealAssessment">Generate Assessment</button>
        @endif
      </div>
      <div class="small text-muted mt-2" id="mealAssessmentState">
        @if($nutritionPlanEnabled ?? false)
          Select the children actually served and the actual meal items, then generate the assessment.
        @else
          Prototype assessment is disabled. Meal logging remains available.
        @endif
      </div>
      <div class="mt-3" id="mealAssessmentResults" hidden></div>
    </section>

    <div class="mt-3">
      <div class="d-flex justify-content-between align-items-end mb-2 flex-wrap gap-3">
        <div>
          <div class="fw-semibold">Actual Attendance</div>
          <div class="small text-muted">Scheduled participants are selected automatically. Uncheck children who were absent.</div>
        </div>
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

@php
  $foodAssessmentData = $foods->map(fn($food) => [
    'id' => $food->id,
    'name' => $food->name,
    'text' => trim($food->name.' '.$food->portion.' '.$food->recipe.' '.implode(' ', $food->allergyAlerts())),
  ]);
@endphp
<script>
  const foods = @json($foodAssessmentData);
  const scheduleData = @json($scheduleData);
  const studentAssessmentData = @json($studentAssessmentData);
  const scheduleSelect = document.getElementById('feedingScheduleSelect');
  const mealTypeSelect = document.querySelector('select[name="meal_type"]');
  const servedAtInput = document.querySelector('input[name="served_at"]');
  const itemsDiv = document.getElementById('items');
  document.getElementById('addItem').addEventListener('click', () => addRow());
  function addRow(selectedFoodId = null){
    const idx = itemsDiv.querySelectorAll('.row').length;
    const row = document.createElement('div');
    row.innerHTML = rowHtml(idx, selectedFoodId);
    itemsDiv.appendChild(row.firstElementChild);
  }
  function rowHtml(idx, selectedFoodId = null){
    return `
    <div class=\"row g-2 align-items-end mb-2\">
      <div class=\"col-md-6\">
  <label class=\"form-label\">Meal</label>
        <select name=\"items[${idx}][food_id]\" class=\"form-select\" required>
          <option value=\"\">-- Select --</option>
          ${foods.map(f=>`<option value=\"${f.id}\" ${String(f.id) === String(selectedFoodId) ? 'selected' : ''}>${f.name}</option>`).join('')}
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
  const initialScheduleId = scheduleSelect?.value || '';
  const preserveOldInput = @json($errors->any());

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

  function refreshPageMaster() {
    const master = document.getElementById('selectAllPage');
    if (!master) return;
    const boxes = Array.from(document.querySelectorAll('.student-check'));
    const checked = boxes.filter((box) => box.checked).length;
    master.checked = boxes.length > 0 && checked === boxes.length;
    master.indeterminate = checked > 0 && checked < boxes.length;
  }

  function applyScheduledSession(scheduleId) {
    const schedule = scheduleData[String(scheduleId)];
    if (!schedule) return;

    selectedIds.clear();
    (schedule.participant_ids || []).forEach((id) => selectedIds.add(String(id)));
    persistSelectedIds();
    syncVisibleChecks();
    updateSelectedCount();
    refreshPageMaster();

    mealTypeSelect.value = schedule.meal_type;
    servedAtInput.value = schedule.served_at;
    itemsDiv.replaceChildren();
    (schedule.food_ids || []).forEach((foodId) => addRow(foodId));
    if (!(schedule.food_ids || []).length) addRow();
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

  scheduleSelect?.addEventListener('change', function() {
    if (this.value) {
      applyScheduledSession(this.value);
      clearMealAssessment('Scheduled details loaded. Confirm actual attendance and substitutions before assessing or logging.');
      return;
    }
    clearMealAssessment('Ad hoc mode selected. Choose the actual participants and meal items manually.');
  });
  if (initialScheduleId && !preserveOldInput) applyScheduledSession(initialScheduleId);

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
    if (scheduleSelect?.value) params.set('feeding_schedule_id', scheduleSelect.value);
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

  const assessmentButton = document.getElementById('generateMealAssessment');
  const assessmentState = document.getElementById('mealAssessmentState');
  const assessmentResults = document.getElementById('mealAssessmentResults');

  function clearMealAssessment(message) {
    if (assessmentResults) {
      assessmentResults.hidden = true;
      assessmentResults.replaceChildren();
    }
    if (assessmentState && message) assessmentState.textContent = message;
  }

  function allergyTerms(allergy) {
    const base = String(allergy || '').trim().toLowerCase();
    const aliases = {
      'milk': ['milk', 'dairy', 'lactose', 'cheese', 'cream', 'butter'],
      'lactose': ['milk', 'dairy', 'lactose'],
      'egg': ['egg', 'eggs'],
      'eggs': ['egg', 'eggs'],
      'peanuts': ['peanut', 'peanuts', 'nut'],
      'tree nuts': ['tree nuts', 'nut', 'almond', 'cashew', 'walnut'],
      'wheat/gluten': ['wheat', 'gluten', 'flour', 'bread', 'noodle', 'pancit'],
      'shellfish': ['shellfish', 'shrimp', 'crab', 'squid']
    };
    const terms = [base];
    Object.entries(aliases).forEach(([key, values]) => {
      if (base === key || base.includes(key)) terms.push(...values);
    });
    return Array.from(new Set(terms.filter(Boolean)));
  }

  function selectedFoodIds() {
    return Array.from(itemsDiv.querySelectorAll('select[name$="[food_id]"]'))
      .map((select) => Number(select.value))
      .filter(Boolean);
  }

  function recordedAllergyWarnings(foodIds) {
    const selectedFoods = foodIds
      .map((id) => foods.find((food) => Number(food.id) === Number(id)))
      .filter(Boolean);
    const warnings = [];

    selectedIds.forEach((studentId) => {
      const student = studentAssessmentData[String(studentId)];
      if (!student?.allergies) return;
      const allergies = String(student.allergies).split(/[,;\n]+/).map((value) => value.trim()).filter(Boolean);
      selectedFoods.forEach((food) => {
        const foodText = String(food.text || '').toLowerCase();
        if (allergies.some((allergy) => allergyTerms(allergy).some((term) => foodText.includes(term)))) {
          warnings.push(student.name + ': ' + food.name + ' may conflict with ' + allergies.join(', '));
        }
      });
    });

    return warnings;
  }

  async function generateMealAssessment() {
    const participantIds = Array.from(selectedIds).map(Number);
    const foodIds = selectedFoodIds();
    if (!participantIds.length || !foodIds.length) {
      clearMealAssessment('Select at least one child and one meal item first.');
      return;
    }

    assessmentButton.disabled = true;
    assessmentButton.textContent = 'Assessing...';
    clearMealAssessment('Assessing the selected attendance and meal items...');

    try {
      const response = await fetch(@json(route('feeding-schedules.recommendations')), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': @json(csrf_token())
        },
        body: JSON.stringify({ participant_student_ids: participantIds })
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to generate the assessment.');
      if (payload.status !== 'success') {
        clearMealAssessment(payload.message || 'The assessment is currently unavailable.');
        return;
      }

      const warnings = recordedAllergyWarnings(foodIds);
      const recommendedIds = new Set((payload.meals || []).map((meal) => Number(meal.food_id)));
      const matchingFoods = foodIds.filter((id) => recommendedIds.has(Number(id))).length;
      const summary = payload.summary || {};
      const headline = document.createElement('div');
      headline.className = 'fw-bold text-primary';
      headline.textContent = summary.eligible_count + ' of ' + summary.selected_count + ' selected children were eligible for prototype assessment.';
      const fit = document.createElement('div');
      fit.className = 'mt-2';
      fit.textContent = matchingFoods + ' of ' + foodIds.length + ' selected meal item(s) appear among the current top group matches.';
      const priorities = document.createElement('div');
      priorities.className = 'small text-muted mt-2';
      priorities.textContent = 'Combined priorities: ' + Object.entries(payload.priorities || {})
        .map(([name, level]) => name + ' (' + level + ')')
        .join(', ');
      const safety = document.createElement('div');
      safety.className = warnings.length ? 'alert alert-danger mt-3 mb-0' : 'alert alert-success mt-3 mb-0';
      safety.textContent = warnings.length
        ? 'Recorded allergy warning: ' + warnings.slice(0, 3).join(' · ')
        : 'No conflict was found between the selected foods and the available recorded allergy text.';
      assessmentResults.replaceChildren(headline, fit, priorities, safety);
      assessmentResults.hidden = false;
      assessmentState.textContent = 'Assessment generated for the current selections. Change attendance or meals and regenerate if needed.';
    } catch (error) {
      clearMealAssessment(error.message || 'Unable to generate the assessment.');
    } finally {
      assessmentButton.disabled = false;
      assessmentButton.textContent = 'Generate Assessment';
    }
  }

  assessmentButton?.addEventListener('click', generateMealAssessment);
  if (initialScheduleId && !preserveOldInput && assessmentState) {
    assessmentState.textContent = 'Scheduled details loaded. Confirm actual attendance and substitutions before assessing or logging.';
  }
</script>
@endsection
