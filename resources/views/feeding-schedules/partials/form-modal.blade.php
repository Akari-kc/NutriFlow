@php
  $isEdit = !empty($session);
  $value = fn($field, $default = '') => old($field, $isEdit ? $session->{$field} : $default);
  $selectedStudentIds = collect(old('participant_student_ids', $isEdit ? ($session->participant_student_ids ?? []) : []))
      ->map(fn($id) => (int) $id)
      ->all();
  $selectedFoodIds = collect(old('selected_food_ids', $isEdit ? ($session->selected_food_ids ?? []) : []))
      ->map(fn($id) => (int) $id)
      ->all();
  $formContext = $isEdit ? 'edit:'.$session->id : 'add';
  $showFormErrors = $errors->any() && old('_schedule_form_mode') === $formContext;
@endphp
<div class="modal fade schedule-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="{{ $action }}" data-schedule-form>
        @csrf
        <input type="hidden" name="_schedule_form_mode" value="{{ $formContext }}">
        @if($method !== 'POST')
          @method($method)
        @endif
        <div class="modal-header">
          <h5 class="modal-title">{{ $title }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          @if($showFormErrors)
            <div class="alert alert-danger" role="alert">
              <div class="fw-bold">Please correct the following:</div>
              <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          <div class="alert alert-danger" role="alert" data-schedule-client-errors hidden></div>
          <div class="schedule-modal-grid">
            <div class="full">
              <label class="form-label">Session Name</label>
              <input name="session_name" value="{{ old('session_name', $isEdit ? $session->batch_name : '') }}" class="form-control" placeholder="Breakfast Group A" required>
            </div>
            <div>
              <label class="form-label">Meal</label>
              <select name="meal_type" class="form-select" required>
                @foreach($mealTypes as $type)
                  <option value="{{ $type }}" @selected($value('meal_type', 'Breakfast') === $type)>{{ $type }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select name="status" class="form-select" required>
                @foreach($statuses as $status)
                  <option value="{{ $status }}" @selected($value('status', 'Scheduled') === $status)>{{ $status }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label">Date</label>
              <input type="date" name="session_date" value="{{ old('session_date', $isEdit ? $session->session_date->format('Y-m-d') : now('Asia/Manila')->toDateString()) }}" class="form-control" required>
            </div>
            <div>
              <label class="form-label">Assigned Aide</label>
              <input name="assigned_aide" value="{{ $value('assigned_aide', auth()->user()?->name) }}" class="form-control">
            </div>
            <div>
              <label class="form-label">Start Time</label>
              <input type="time" name="start_time" value="{{ old('start_time', $isEdit ? \Carbon\Carbon::parse($session->start_time)->format('H:i') : '07:00') }}" class="form-control" required>
            </div>
            <div>
              <label class="form-label">End Time</label>
              <input type="time" name="end_time" value="{{ old('end_time', $isEdit ? \Carbon\Carbon::parse($session->end_time)->format('H:i') : '07:30') }}" class="form-control" required>
            </div>
            <div class="full" data-student-picker>
              <label class="form-label">Participating Students</label>
              <div class="student-picker-toolbar">
                <input class="form-control" type="search" placeholder="Search students..." data-student-search>
                <select class="form-select" data-student-grade>
                  <option value="">All grades</option>
                  @foreach($classes as $class)
                    <option value="{{ $class }}">{{ $class }}</option>
                  @endforeach
                </select>
                <select class="form-select" data-student-section>
                  <option value="">All sections</option>
                  @foreach($sections as $section)
                    <option value="{{ $section }}">{{ $section }}</option>
                  @endforeach
                </select>
              </div>
              <div class="student-picker-actions">
                <button type="button" class="btn btn-outline-primary btn-sm nl-btn" data-select-all-students>Select all</button>
                <button type="button" class="btn btn-outline-secondary btn-sm nl-btn" data-clear-students>Clear selection</button>
                <span class="small text-muted">Select all applies to the students matching the current filters.</span>
              </div>
              <div class="student-picker-list">
                @foreach($students as $student)
                  <label class="student-picker-row" data-student-row data-name="{{ Str::lower($student->name) }}" data-grade="{{ $student->class_name }}" data-section="{{ $student->section }}" data-allergies="{{ $student->allergies }}">
                    <span>
                      <span class="student-picker-name">{{ $student->name }}</span>
                      <span class="student-picker-meta d-block">{{ $student->class_name }} - Section {{ $student->section }}</span>
                    </span>
                    <input class="form-check-input" type="checkbox" name="participant_student_ids[]" value="{{ $student->id }}" @checked(in_array($student->id, $selectedStudentIds, true))>
                  </label>
                @endforeach
              </div>
              <div class="student-picker-count" data-student-count>0 students selected</div>
            </div>
            @include('feeding-schedules.partials.cohort-recommendations')
            <div class="full">
              <label class="form-label">Menu Items</label>
              <div class="menu-picker-list">
                @foreach($foods as $food)
                  <label class="menu-picker-row" data-food-row data-food-name="{{ $food->name }}" data-food-text="{{ trim($food->name.' '.$food->portion.' '.$food->recipe.' '.implode(' ', $food->allergyAlerts())) }}">
                    <span>{{ $food->name }}</span>
                    <input class="form-check-input" type="checkbox" name="selected_food_ids[]" value="{{ $food->id }}" @checked(in_array($food->id, $selectedFoodIds, true))>
                  </label>
                @endforeach
              </div>
              <div class="allergy-notice mt-2" data-allergy-warning hidden></div>
            </div>
            <div class="full">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3">{{ $value('notes') }}</textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          @if($isEdit && auth()->user()?->isSchoolAdmin())
            <button type="submit" class="btn btn-outline-danger nl-btn" form="deleteSchedule{{ $session->id }}">Delete</button>
          @else
            <span></span>
          @endif
          <div class="d-flex gap-2">
            @if($isEdit && ($session->meals_count ?? 0) > 0)
              <span class="btn btn-light nl-btn disabled" aria-disabled="true">Meal Log Recorded</span>
            @elseif($isEdit && $session->status !== 'Cancelled' && !$session->session_date->isFuture())
              <a href="{{ route('meals.batch', ['feeding_schedule_id' => $session->id]) }}" class="btn btn-success nl-btn">Log This Session</a>
            @endif
            <button type="button" class="btn btn-outline-secondary nl-btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary nl-btn">{{ $isEdit ? 'Save Changes' : 'Add Session' }}</button>
          </div>
        </div>
      </form>
      @if($isEdit && auth()->user()?->isSchoolAdmin())
        <form id="deleteSchedule{{ $session->id }}" method="POST" action="{{ route('feeding-schedules.destroy', $session) }}" onsubmit="return confirm('Delete this feeding session?');">
          @csrf
          @method('DELETE')
        </form>
      @endif
    </div>
  </div>
</div>

<script>
  (function () {
    const modal = document.getElementById(@json($modalId));
    if (!modal) return;

    const picker = modal.querySelector('[data-student-picker]');
    if (!picker) return;

    const search = picker.querySelector('[data-student-search]');
    const grade = picker.querySelector('[data-student-grade]');
    const section = picker.querySelector('[data-student-section]');
    const rows = Array.from(picker.querySelectorAll('[data-student-row]'));
    const foodRows = Array.from(modal.querySelectorAll('[data-food-row]'));
    const count = picker.querySelector('[data-student-count]');
    const allergyWarning = modal.querySelector('[data-allergy-warning]');
    const form = modal.querySelector('[data-schedule-form]');
    const clientErrors = modal.querySelector('[data-schedule-client-errors]');
    const selectAll = picker.querySelector('[data-select-all-students]');
    const clearStudents = picker.querySelector('[data-clear-students]');
    const sectionOptions = Array.from(section.options);

    function updateCount() {
      const selected = rows.filter((row) => row.querySelector('input[type="checkbox"]').checked).length;
      count.textContent = selected + ' ' + (selected === 1 ? 'student' : 'students') + ' selected';
      updateAllergyWarning();
    }

    function allergyTerms(allergy) {
      const base = (allergy || '').trim().toLowerCase();
      const terms = [base];
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
      Object.keys(aliases).forEach((key) => {
        if (base === key || base.includes(key)) terms.push(...aliases[key]);
      });
      return Array.from(new Set(terms.filter(Boolean)));
    }

    function splitAllergies(value) {
      return (value || '').split(/[,;\n]+/).map((item) => item.trim()).filter(Boolean);
    }

    function escapeHtml(value) {
      return String(value).replace(/[&<>"']/g, function(character) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
      });
    }

    function updateAllergyWarning() {
      if (!allergyWarning) return;
      const checkedStudents = rows.filter((row) => row.querySelector('input[type="checkbox"]').checked);
      const checkedFoods = foodRows.filter((row) => row.querySelector('input[type="checkbox"]').checked);
      const warnings = [];

      checkedStudents.forEach((studentRow) => {
        const allergies = splitAllergies(studentRow.dataset.allergies);
        if (!allergies.length) return;
        checkedFoods.forEach((foodRow) => {
          const foodText = (foodRow.dataset.foodText || '').toLowerCase();
          const matched = allergies.some((allergy) => allergyTerms(allergy).some((term) => foodText.includes(term)));
          if (matched) {
            warnings.push(studentRow.querySelector('.student-picker-name').textContent + ': ' + foodRow.dataset.foodName + ' may contain ' + allergies.join(', '));
          }
        });
      });

      if (!warnings.length) {
        allergyWarning.hidden = true;
        allergyWarning.innerHTML = '';
        return;
      }

      allergyWarning.hidden = false;
      allergyWarning.innerHTML = '<div>Allergy warning</div><ul>' + warnings.slice(0, 4).map((warning) => '<li>' + escapeHtml(warning) + '</li>').join('') + (warnings.length > 4 ? '<li>' + (warnings.length - 4) + ' more warning' + (warnings.length - 4 === 1 ? '' : 's') + '</li>' : '') + '</ul>';
    }

    function filterRows() {
      const term = (search.value || '').trim().toLowerCase();
      const selectedGrade = grade.value;
      const selectedSection = section.value;

      rows.forEach((row) => {
        const matchesSearch = term === '' || row.dataset.name.includes(term);
        const matchesGrade = selectedGrade === '' || row.dataset.grade === selectedGrade;
        const matchesSection = selectedSection === '' || row.dataset.section === selectedSection;
        row.hidden = !(matchesSearch && matchesGrade && matchesSection);
      });
    }

    function syncSectionOptions() {
      const selectedGrade = grade.value;
      const availableSections = new Set(
        rows
          .filter((row) => selectedGrade === '' || row.dataset.grade === selectedGrade)
          .map((row) => row.dataset.section)
      );

      sectionOptions.forEach((option) => {
        option.hidden = option.value !== '' && !availableSections.has(option.value);
      });

      if (section.value && !availableSections.has(section.value)) {
        section.value = '';
      }

      filterRows();
    }

    function setVisibleSelection(checked) {
      rows.filter((row) => !row.hidden).forEach((row) => {
        const input = row.querySelector('input[type="checkbox"]');
        if (input.checked === checked) return;
        input.checked = checked;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
      updateCount();
    }

    form?.addEventListener('submit', function (event) {
      const messages = [];
      if (!rows.some((row) => row.querySelector('input[type="checkbox"]').checked)) {
        messages.push('Select at least one participating student.');
      }
      if (!foodRows.some((row) => row.querySelector('input[type="checkbox"]').checked)) {
        messages.push('Select at least one menu item before adding the session.');
      }
      if (!messages.length || !clientErrors) return;

      event.preventDefault();
      clientErrors.replaceChildren();
      const title = document.createElement('div');
      title.className = 'fw-bold';
      title.textContent = 'The feeding session was not added.';
      const list = document.createElement('ul');
      list.className = 'mb-0 mt-1';
      messages.forEach((message) => {
        const item = document.createElement('li');
        item.textContent = message;
        list.appendChild(item);
      });
      clientErrors.append(title, list);
      clientErrors.hidden = false;
      clientErrors.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    search.addEventListener('input', filterRows);
    grade.addEventListener('change', syncSectionOptions);
    section.addEventListener('change', filterRows);
    selectAll?.addEventListener('click', () => setVisibleSelection(true));
    clearStudents?.addEventListener('click', () => setVisibleSelection(false));
    rows.forEach((row) => row.querySelector('input[type="checkbox"]').addEventListener('change', updateCount));
    foodRows.forEach((row) => row.querySelector('input[type="checkbox"]').addEventListener('change', updateAllergyWarning));
    modal.addEventListener('shown.bs.modal', function () {
      syncSectionOptions();
      updateCount();
      updateAllergyWarning();
    });
    syncSectionOptions();
    updateCount();
    updateAllergyWarning();
  })();
</script>
