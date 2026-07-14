@extends('layouts.app')
@section('page-title', 'Meal Catalog')
@section('content')
<style>
  .allergy-alerts { display: flex; flex-wrap: wrap; gap: .35rem; max-width: 420px; }
  .allergy-pill { border-radius: 8px; padding: .25rem .55rem; background: #fff4d9; color: #a16207; font-size: .76rem; font-weight: 800; white-space: nowrap; }
  .allergy-pill.clear { background: #e3f0ea; color: #057243; }
</style>
<div class="d-flex justify-content-between mb-3">
  <div class="d-flex align-items-center gap-2">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
    <h4 class="mb-0">Meal Catalog</h4>
  </div>
  <div>
    <a href="{{ route('menu-items.create') }}" class="btn btn-success nl-btn me-2 d-inline-flex align-items-center gap-2"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg> Add Meal</a>
  <form method="POST" action="{{ route('menu-items.uploadCsv') }}" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2">
      @csrf
      <input type="file" class="form-control" name="csv" accept=".csv" />
      <button class="btn btn-outline-success nl-btn d-inline-flex align-items-center gap-2"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m17 8-5-5-5 5"/><path d="M5 21h14"/></svg> Upload Meals CSV</button>
    </form>
  </div>
</div>
<div class="card nl-card p-3">
  @if(isset($foods) && $foods->count())
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>Name</th><th>Portion</th><th>Allergy Alerts</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @foreach($foods as $f)
          @php
            $alerts = $f->allergyAlerts();
          @endphp
          <tr>
            <td>{{ $f->name }}</td>
            <td>{{ $f->portion }}</td>
            <td>
              <div class="allergy-alerts">
                @forelse($alerts as $alert)
                  <span class="allergy-pill">{{ $alert }}</span>
                @empty
                  <span class="allergy-pill clear">No common alerts</span>
                @endforelse
              </div>
            </td>
            <td class="text-end d-flex justify-content-end gap-2">
              <a href="{{ route('menu-items.show', $f) }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg> View</a>
              <a href="{{ route('menu-items.edit', $f) }}" class="btn btn-outline-primary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Edit</a>
              <form method="POST" action="{{ route('menu-items.destroy', $f) }}" onsubmit="return confirm('Delete this meal from your school\'s catalog?');">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg> Delete</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $foods->links() }}
  @else
    <div class="text-muted">No meals in the catalog yet.</div>
  @endif
</div>
@endsection
