@php
  $isEdit = !empty($session);
  $value = fn($field, $default = '') => old($field, $isEdit ? $session->{$field} : $default);
  $selectedStudentIds = collect(old('participant_student_ids', $isEdit ? ($session->participant_student_ids ?? []) : []))
      ->map(fn($id) => (int) $id)
      ->all();
  $selectedFoodIds = collect(old('selected_food_ids', $isEdit ? ($session->selected_food_ids ?? []) : []))
      ->map(fn($id) => (int) $id)
      ->all();
@endphp
<div class="modal fade schedule-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="{{ $action }}">
        @csrf
        @if($method !== 'POST')
          @method($method)
        @endif
        <div class="modal-header">
          <h5 class="modal-title">{{ $title }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
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
              <input type="date" name="session_date" value="{{ $isEdit ? $session->session_date->format('Y-m-d') : now('Asia/Manila')->toDateString() }}" class="form-control" required>
            </div>
            <div>
              <label class="form-label">Assigned Aide</label>
              <input name="assigned_aide" value="{{ $value('assigned_aide', auth()->user()?->name) }}" class="form-control">
            </div>
            <div>
              <label class="form-label">Start Time</label>
              <input type="time" name="start_time" value="{{ $isEdit ? \Carbon\Carbon::parse($session->start_time)->format('H:i') : '07:00' }}" class="form-control" required>
            </div>
            <div>
              <label class="form-label">End Time</label>
              <input type="time" name="end_time" value="{{ $isEdit ? \Carbon\Carbon::parse($session->end_time)->format('H:i') : '07:30' }}" class="form-control" required>
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
              <div class="student-picker-list">
                @foreach($students as $student)
                  <label class="student-picker-row" data-student-row data-name="{{ Str::lower($student->name) }}" data-grade="{{ $student->class_name }}" data-section="{{ $student->section }}">
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
            <div class="full">
              <label class="form-label">Menu Items</label>
              <div class="menu-picker-list">
                @foreach($foods as $food)
                  <label class="menu-picker-row">
                    <span>{{ $food->name }}</span>
                    <input class="form-check-input" type="checkbox" name="selected_food_ids[]" value="{{ $food->id }}" @checked(in_array($food->id, $selectedFoodIds, true))>
                  </label>
                @endforeach
              </div>
            </div>
            <div class="full">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3">{{ $value('notes') }}</textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          @if($isEdit)
            <button type="submit" class="btn btn-outline-danger nl-btn" form="deleteSchedule{{ $session->id }}">Delete</button>
          @else
            <span></span>
          @endif
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary nl-btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary nl-btn">{{ $isEdit ? 'Save Changes' : 'Add Session' }}</button>
          </div>
        </div>
      </form>
      @if($isEdit)
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
    const count = picker.querySelector('[data-student-count]');
    const sectionOptions = Array.from(section.options);

    function updateCount() {
      const selected = rows.filter((row) => row.querySelector('input[type="checkbox"]').checked).length;
      count.textContent = selected + ' ' + (selected === 1 ? 'student' : 'students') + ' selected';
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

    search.addEventListener('input', filterRows);
    grade.addEventListener('change', syncSectionOptions);
    section.addEventListener('change', filterRows);
    rows.forEach((row) => row.querySelector('input[type="checkbox"]').addEventListener('change', updateCount));
    modal.addEventListener('shown.bs.modal', function () {
      syncSectionOptions();
      updateCount();
    });
    syncSectionOptions();
    updateCount();
  })();
</script>
