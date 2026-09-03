@extends('layouts.app')
@section('page-title', 'Children')
@section('content')
<style>
  .edit-shell { max-width: 980px; }
  .edit-title { color: #082858; font-weight: 900; margin: 0; }
  .edit-subtitle { color: #7c8bad; font-size: .82rem; margin-top: .25rem; }
  .edit-card { padding: 1.35rem; }
  .section-label { color: #082858; font-weight: 900; margin-bottom: .75rem; }
  .allergy-stack { display: grid; gap: .6rem; }
  .allergy-row-edit { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 38px; gap: .5rem; align-items: center; }
  .section-label-row { display: flex; align-items: center; gap: .6rem; margin-bottom: .75rem; }
  .section-label-row .section-label { margin-bottom: 0; }
  .other-allergy-input[hidden] { display: none !important; }
  .icon-btn { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; border: 1px solid #dbe3ef; background: #fff; color: #0b3b82; font-weight: 900; }
  .icon-btn.add { background: #0b3b82; color: #fff; border-color: #0b3b82; }
  .icon-btn.remove { color: #d92d20; }
  .helper-copy { color: #7c8bad; font-size: .78rem; margin-top: .45rem; }
  .new-section-field[hidden] { display: none !important; }
  @media (max-width: 760px) {
    .allergy-row-edit { grid-template-columns: minmax(0, 1fr) 34px; }
    .other-allergy-input { grid-column: 1 / -1; }
  }
</style>

@php
  $rawSelected = collect(old('allergies', $selectedAllergies->all() ?: ['']))->values();
  $oldOther = collect(old('allergy_other', []))->values();
  $selected = $rawSelected->map(function ($allergy, $index) use ($allergyOptions, $oldOther) {
      $isKnown = $allergy === '' || in_array($allergy, $allergyOptions, true) || $allergy === 'Other';
      return [
          'select' => $isKnown ? $allergy : 'Other',
          'other' => $oldOther->get($index, $isKnown && $allergy !== 'Other' ? '' : $allergy),
      ];
  });
  $suffixOptions = ['', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
  $gradeSectionOptions = ($gradeSections ?? collect())->map(fn($gradeSections) => $gradeSections->values());
@endphp

<div class="edit-shell">
  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
    <div>
      <h1 class="edit-title h4">Edit Child</h1>
      <div class="edit-subtitle">Update profile details, name format, and allergy records.</div>
    </div>
  </div>

  <div class="nl-card edit-card">
    <form method="POST" action="{{ route('students.update', $student) }}">
      @csrf
      @method('PATCH')

      <div class="section-label">Name <span class="text-muted fw-normal">(optional)</span></div>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label">First Name</label>
          <input name="first_name" value="{{ old('first_name', $nameParts['first_name']) }}" class="form-control">
          @error('first_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">M.I.</label>
          <input name="middle_initial" value="{{ old('middle_initial', $nameParts['middle_initial']) }}" class="form-control" maxlength="5" placeholder="M.">
          @error('middle_initial')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name</label>
          <input name="last_name" value="{{ old('last_name', $nameParts['last_name']) }}" class="form-control">
          @error('last_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">Suffix</label>
          <select name="suffix" class="form-select">
            @foreach($suffixOptions as $suffix)
              <option value="{{ $suffix }}" @selected(old('suffix', $nameParts['suffix']) === $suffix)>{{ $suffix ?: 'None' }}</option>
            @endforeach
          </select>
          @error('suffix')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="helper-copy mb-4">The name is display-only and may be left blank. The permanent Learner ID remains the record key.</div>

      <div class="section-label">School Details</div>
      <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-4">
          <label class="form-label">Learner ID</label>
          <input value="{{ $student->learner_uid }}" class="form-control" disabled>
          <div class="form-text">Permanent and cannot be changed.</div>
        </div>
        <div class="col-lg-3 col-md-4">
          <label class="form-label">LRN (optional)</label>
          <input name="lrn" value="{{ old('lrn', $student->lrn) }}" class="form-control" placeholder="Optional">
          @error('lrn')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option value="">--</option>
            <option value="Male" @selected(old('gender', $student->gender)==='Male')>Male</option>
            <option value="Female" @selected(old('gender', $student->gender)==='Female')>Female</option>
          </select>
          @error('gender')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-4">
          <label class="form-label">Birthdate</label>
          <input type="date" name="birthdate" value="{{ old('birthdate', optional($student->birthdate)->format('Y-m-d')) }}" class="form-control">
          <div class="form-text" id="ageRangeHint">Kinder to Grade 6 age range: 5 to 12 years old.</div>
          @error('birthdate')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Age</label>
          <input type="number" class="form-control" id="computedAge" value="{{ $student->birthdate ? $student->birthdate->age : '' }}" disabled>
          <div class="form-text">Auto</div>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Grade/Class</label>
          <select name="class_name" class="form-select" id="classNameSelect">
            <option value="">Select</option>
            @foreach(($classes ?? []) as $className)
              <option value="{{ $className }}" @selected(old('class_name', $student->class_name) === $className)>{{ $className }}</option>
            @endforeach
          </select>
          <div class="form-text" id="gradeAgeHint">Choose birthdate and grade to check the usual age range.</div>
          @error('class_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-8">
          <label class="form-label">Section</label>
          <select name="section" class="form-select" id="sectionSelect" data-current-section="{{ old('section', $student->section) }}">
            <option value="">Select</option>
            @foreach(($sections ?? []) as $section)
              <option value="{{ $section }}" @selected(old('section', $student->section) === $section)>{{ $section }}</option>
            @endforeach
            <option value="__new" @selected(old('section') === '__new')>+ Add section</option>
          </select>
          <input name="section_new" value="{{ old('section_new') }}" class="form-control mt-2 new-section-field" id="sectionNewInput" placeholder="New section" @if(old('section') !== '__new') hidden @endif>
          @error('section')<div class="text-danger small">{{ $message }}</div>@enderror
          @error('section_new')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="section-label-row">
        <div class="section-label">Allergies</div>
        <button class="icon-btn add" type="button" id="addAllergy" title="Add allergy">+</button>
      </div>
      <div class="allergy-stack mb-2" id="allergyRows">
        @foreach($selected as $idx => $allergy)
          <div class="allergy-row-edit">
            <select name="allergies[]" class="form-select allergy-select">
              <option value="">Not recorded</option>
              @foreach($allergyOptions as $option)
                <option value="{{ $option }}" @selected($allergy['select'] === $option)>{{ $option }}</option>
              @endforeach
              <option value="Other" @selected($allergy['select'] === 'Other')>Other</option>
            </select>
            <input name="allergy_other[]" value="{{ $allergy['other'] }}" class="form-control other-allergy-input" placeholder="Type allergy" @if($allergy['select'] !== 'Other') hidden @endif>
            <button class="icon-btn remove" type="button" onclick="this.closest('.allergy-row-edit').remove()" title="Remove allergy">&times;</button>
          </div>
        @endforeach
      </div>
      <div class="helper-copy">Blank means Not recorded, not that the learner has no allergies. Use one row per known allergy.</div>
      @error('allergies')<div class="text-danger small">{{ $message }}</div>@enderror
      @error('allergies.*')<div class="text-danger small">{{ $message }}</div>@enderror

      <div class="d-flex justify-content-end mt-4">
        <button class="btn btn-primary nl-btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<template id="allergyTemplate">
  <div class="allergy-row-edit">
    <select name="allergies[]" class="form-select allergy-select">
      <option value="">Not recorded</option>
      @foreach($allergyOptions as $option)
        <option value="{{ $option }}">{{ $option }}</option>
      @endforeach
      <option value="Other">Other</option>
    </select>
    <input name="allergy_other[]" class="form-control other-allergy-input" placeholder="Type allergy" hidden>
    <button class="icon-btn remove" type="button" onclick="this.closest('.allergy-row-edit').remove()" title="Remove allergy">&times;</button>
  </div>
</template>

@push('scripts')
<script>
  (function(){
    const b = document.querySelector('input[name="birthdate"]');
    const age = document.getElementById('computedAge');
    const addAllergy = document.getElementById('addAllergy');
    const allergyRows = document.getElementById('allergyRows');
    const allergyTemplate = document.getElementById('allergyTemplate');
    const classNameSelect = document.getElementById('classNameSelect');
    const sectionSelect = document.getElementById('sectionSelect');
    const sectionNewInput = document.getElementById('sectionNewInput');
    const gradeSections = @json($gradeSectionOptions);

    function computeAge(str){
      if(!str) return '';
      const bd = new Date(str + 'T00:00:00');
      if (isNaN(bd)) return '';
      const now = new Date();
      let years = now.getFullYear() - bd.getFullYear();
      const m = now.getMonth() - bd.getMonth();
      if (m < 0 || (m === 0 && now.getDate() < bd.getDate())) years--;
      return years < 0 ? 0 : years;
    }
    function sync(){ const v = computeAge(b.value); if(age){ age.value = v; } }
    function syncAgeWarning(){
      const hint = document.getElementById('ageRangeHint');
      const computed = computeAge(b?.value || '');
      if (!hint) return;
      if (computed !== '' && (computed < 5 || computed > 12)) {
        hint.className = 'form-text text-danger fw-semibold';
        hint.textContent = 'Age is outside the Kinder to Grade 6 range. Use ages 5 to 12.';
      } else {
        hint.className = 'form-text';
        hint.textContent = 'Kinder to Grade 6 age range: 5 to 12 years old.';
      }
    }
    function expectedAgeForGrade(grade) {
      if (grade === 'Kinder') return 5;
      const match = String(grade || '').match(/\d+/);
      return match ? Number(match[0]) + 5 : null;
    }
    function syncGradeAgeWarning(){
      const hint = document.getElementById('gradeAgeHint');
      const computed = computeAge(b?.value || '');
      const grade = classNameSelect?.value || '';
      const expected = expectedAgeForGrade(grade);
      if (!hint) return;
      if (computed === '' || !expected) {
        hint.className = 'form-text';
        hint.textContent = 'Choose birthdate and grade to check the usual age range.';
        return;
      }
      const minAge = expected - 2;
      const maxAge = expected + 2;
      if (computed < minAge || computed > maxAge) {
        hint.className = 'form-text text-warning fw-semibold';
        hint.textContent = grade + ' usually fits ages ' + minAge + ' to ' + maxAge + '. Please review this age/grade pairing.';
      } else {
        hint.className = 'form-text text-success';
        hint.textContent = 'Age looks reasonable for ' + grade + '.';
      }
    }
    if(b){
      b.addEventListener('change', function(){ sync(); syncAgeWarning(); syncGradeAgeWarning(); });
      b.addEventListener('blur', function(){ sync(); syncAgeWarning(); syncGradeAgeWarning(); });
    }
    sync();
    syncAgeWarning();
    syncGradeAgeWarning();

    if (addAllergy && allergyRows && allergyTemplate) {
      addAllergy.addEventListener('click', function(){
        allergyRows.appendChild(allergyTemplate.content.cloneNode(true));
      });
    }

    function syncSectionInput() {
      if (!sectionSelect || !sectionNewInput) return;
      const adding = sectionSelect.value === '__new';
      sectionNewInput.hidden = !adding;
      sectionNewInput.required = adding;
      if (!adding) sectionNewInput.value = '';
    }

    function rebuildSectionOptions() {
      if (!classNameSelect || !sectionSelect) return;
      const selectedSection = sectionSelect.value || sectionSelect.dataset.currentSection || '';
      const sections = gradeSections[classNameSelect.value] || [];

      sectionSelect.innerHTML = '<option value="">Select</option>';
      sections.forEach(function(section){
        const option = document.createElement('option');
        option.value = section;
        option.textContent = section;
        sectionSelect.appendChild(option);
      });

      if (selectedSection && !sections.includes(selectedSection) && selectedSection !== '__new') {
        const currentOption = document.createElement('option');
        currentOption.value = selectedSection;
        currentOption.textContent = selectedSection;
        sectionSelect.appendChild(currentOption);
      }

      const addOption = document.createElement('option');
      addOption.value = '__new';
      addOption.textContent = '+ Add section';
      sectionSelect.appendChild(addOption);

      if (selectedSection) {
        sectionSelect.value = selectedSection;
      }
      syncSectionInput();
    }
    classNameSelect?.addEventListener('change', function(){
      rebuildSectionOptions();
      syncGradeAgeWarning();
    });
    rebuildSectionOptions();
    sectionSelect?.addEventListener('change', syncSectionInput);
    syncSectionInput();

    function syncOtherInput(select) {
      const row = select.closest('.allergy-row-edit');
      const input = row ? row.querySelector('.other-allergy-input') : null;
      if (!input) return;
      const show = select.value === 'Other';
      input.hidden = !show;
      if (!show) input.value = '';
    }

    allergyRows?.addEventListener('change', function(event){
      if (event.target.matches('.allergy-select')) {
        syncOtherInput(event.target);
      }
    });
    document.querySelectorAll('.allergy-select').forEach(syncOtherInput);
  })();
</script>
@endpush

<div class="card nl-card p-3 mt-3 edit-shell">
  <div class="fw-semibold mb-2">Danger Zone</div>
  <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Remove this student? This will also delete their meals and measurements.');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger nl-btn">Remove Student</button>
  </form>
</div>
@endsection
