@extends('layouts.app')
@section('page-title', 'Children')
@section('content')
<style>
  .create-shell { max-width: 980px; }
  .create-title { color: #082858; font-weight: 900; margin: 0; }
  .create-subtitle { color: #7c8bad; font-size: .82rem; margin-top: .25rem; }
  .create-card { padding: 1.35rem; }
  .section-label { color: #082858; font-weight: 900; margin-bottom: .75rem; }
  .section-label-row { display: flex; align-items: center; gap: .6rem; margin-bottom: .75rem; }
  .section-label-row .section-label { margin-bottom: 0; }
  .allergy-stack { display: grid; gap: .6rem; }
  .allergy-row-edit { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 38px; gap: .5rem; align-items: center; }
  .other-allergy-input[hidden] { display: none !important; }
  .icon-btn { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; border: 1px solid #dbe3ef; background: #fff; color: #0b3b82; font-weight: 900; }
  .icon-btn.add { background: #0b3b82; color: #fff; border-color: #0b3b82; }
  .icon-btn.remove { color: #d92d20; }
  .helper-copy { color: #7c8bad; font-size: .78rem; margin-top: .45rem; }
  .unit-pair { display: grid; grid-template-columns: minmax(0, 1fr) 98px; gap: .5rem; }
  .new-section-field[hidden] { display: none !important; }
  @media (max-width: 760px) {
    .allergy-row-edit { grid-template-columns: minmax(0, 1fr) 34px; }
    .other-allergy-input { grid-column: 1 / -1; }
    .unit-pair { grid-template-columns: minmax(0, 1fr) 84px; }
  }
</style>

@php
  $rawSelected = collect(old('allergies', ['']))->values();
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

<div class="create-shell">
  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('students.index') }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
    <div>
      <h1 class="create-title h4">Add Child</h1>
      <div class="create-subtitle">Create a student profile with the school grade list and section details.</div>
    </div>
  </div>

  <div class="nl-card create-card">
    <form method="POST" action="{{ route('students.store') }}">
      @csrf

      <div class="section-label">Name</div>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label">First Name</label>
          <input name="first_name" value="{{ old('first_name') }}" class="form-control" required>
          @error('first_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">M.I.</label>
          <input name="middle_initial" value="{{ old('middle_initial') }}" class="form-control" maxlength="5" placeholder="M.">
          @error('middle_initial')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name</label>
          <input name="last_name" value="{{ old('last_name') }}" class="form-control" required>
          @error('last_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">Suffix</label>
          <select name="suffix" class="form-select">
            @foreach($suffixOptions as $suffix)
              <option value="{{ $suffix }}" @selected(old('suffix') === $suffix)>{{ $suffix ?: 'None' }}</option>
            @endforeach
          </select>
          @error('suffix')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="section-label">School Details</div>
      <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select" required>
            <option value="">--</option>
            <option value="Male" @selected(old('gender')==='Male')>Male</option>
            <option value="Female" @selected(old('gender')==='Female')>Female</option>
          </select>
          @error('gender')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-4">
          <label class="form-label">Birthdate</label>
          <input type="date" name="birthdate" value="{{ old('birthdate') }}" class="form-control" required>
          @error('birthdate')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Age</label>
          <input type="number" class="form-control" id="computedAge" disabled>
          <div class="form-text">Auto</div>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label">Grade/Class</label>
          <select name="class_name" class="form-select" id="classNameSelect" required>
            <option value="">Select</option>
            @foreach(($classes ?? []) as $className)
              <option value="{{ $className }}" @selected(old('class_name') === $className)>{{ $className }}</option>
            @endforeach
          </select>
          @error('class_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-8">
          <label class="form-label">Section</label>
          <select name="section" class="form-select" id="sectionSelect" data-current-section="{{ old('section') }}" required>
            <option value="">Select</option>
            @foreach(($sections ?? []) as $section)
              <option value="{{ $section }}" @selected(old('section') === $section)>{{ $section }}</option>
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
        @foreach($selected as $allergy)
          <div class="allergy-row-edit">
            <select name="allergies[]" class="form-select allergy-select">
              <option value="">No allergy selected</option>
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
      <div class="helper-copy mb-4">Use one row per allergy. Choose Other when the allergy is not listed.</div>
      @error('allergies')<div class="text-danger small">{{ $message }}</div>@enderror
      @error('allergies.*')<div class="text-danger small">{{ $message }}</div>@enderror

      <div class="section-label">Initial Growth</div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Weight</label>
          <div class="unit-pair">
            <input type="number" step="0.01" min="0" name="weight_value" value="{{ old('weight_value') }}" class="form-control" required>
            <select name="weight_unit" class="form-select">
              <option value="kg" @selected(old('weight_unit', 'kg') === 'kg')>kg</option>
              <option value="g" @selected(old('weight_unit') === 'g')>g</option>
              <option value="lb" @selected(old('weight_unit') === 'lb')>lb</option>
            </select>
          </div>
          @error('weight_value')<div class="text-danger small">{{ $message }}</div>@enderror
          @error('weight_unit')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Height</label>
          <div class="unit-pair">
            <input type="number" step="0.1" min="0" name="height_value" value="{{ old('height_value') }}" class="form-control" required>
            <select name="height_unit" class="form-select">
              <option value="cm" @selected(old('height_unit', 'cm') === 'cm')>cm</option>
              <option value="m" @selected(old('height_unit') === 'm')>m</option>
              <option value="in" @selected(old('height_unit') === 'in')>in</option>
              <option value="ft" @selected(old('height_unit') === 'ft')>ft</option>
            </select>
          </div>
          @error('height_value')<div class="text-danger small">{{ $message }}</div>@enderror
          @error('height_unit')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="d-flex justify-content-end mt-4">
        <a href="{{ route('students.index') }}" class="btn btn-outline-secondary nl-btn me-2">Cancel</a>
        <button class="btn btn-primary nl-btn">Save Child</button>
      </div>
    </form>
  </div>
</div>

<template id="allergyTemplate">
  <div class="allergy-row-edit">
    <select name="allergies[]" class="form-select allergy-select">
      <option value="">No allergy selected</option>
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

    function sync(){ if(age){ age.value = computeAge(b?.value || ''); } }
    if(b){ b.addEventListener('change', sync); b.addEventListener('blur', sync); }
    sync();

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

      const addOption = document.createElement('option');
      addOption.value = '__new';
      addOption.textContent = '+ Add section';
      sectionSelect.appendChild(addOption);

      if (selectedSection && (sections.includes(selectedSection) || selectedSection === '__new')) {
        sectionSelect.value = selectedSection;
      }
      syncSectionInput();
    }
    classNameSelect?.addEventListener('change', rebuildSectionOptions);
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
@endsection
