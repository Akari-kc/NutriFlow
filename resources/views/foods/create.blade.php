@extends('layouts.app')
@section('page-title', 'Meal Catalog')
@section('content')
<div class="d-flex align-items-center gap-2 mb-2">
  <a href="{{ route('menu-items.index') }}" class="btn btn-outline-secondary btn-sm nl-btn d-inline-flex align-items-center gap-1"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Back</a>
  <h4 class="mb-0">Add Meal to Catalog</h4>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card nl-card p-3">
  <form method="POST" action="{{ route('menu-items.store') }}">
    @csrf
    @include('foods.form-fields')
    <div class="mt-3 d-flex justify-content-end">
      <button class="btn btn-success nl-btn d-inline-flex align-items-center gap-2"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg> Save</button>
    </div>
  </form>
</div>
@endsection
