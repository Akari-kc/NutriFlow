@extends('layouts.app')
@section('page-title', 'Meal Catalog')
@section('content')
<style>
  .allergy-alerts { display: flex; flex-wrap: wrap; gap: .35rem; }
  .allergy-pill { border-radius: 8px; padding: .25rem .55rem; background: #fff4d9; color: #a16207; font-size: .76rem; font-weight: 800; white-space: nowrap; }
  .allergy-pill.clear { background: #e3f0ea; color: #057243; }
</style>
@php
  $alerts = $food->allergyAlerts();
@endphp
<div class="d-flex align-items-center gap-2 mb-2">
  <a href="{{ route('menu-items.index') }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
  <h4 class="mb-0">Meal Detail</h4>
</div>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card nl-card p-3">
      <div class="fw-semibold mb-2">{{ $food->name }}</div>
      <div class="text-muted">Portion: {{ $food->portion ?? '—' }}</div>
      <hr/>
      <div class="fw-semibold mb-2">Allergy Alerts</div>
      <div class="allergy-alerts">
        @forelse($alerts as $alert)
          <span class="allergy-pill">{{ $alert }}</span>
        @empty
          <span class="allergy-pill clear">No common alerts</span>
        @endforelse
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card nl-card p-3">
      <div class="fw-semibold mb-2">Recipe</div>
      @if(!empty($food->recipe))
        <pre class="mb-0" style="white-space: pre-wrap;">{{ $food->recipe }}</pre>
      @else
        <div class="text-muted">No recipe provided.</div>
      @endif
    </div>
  </div>
</div>
@endsection
