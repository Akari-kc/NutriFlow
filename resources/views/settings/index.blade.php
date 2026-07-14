@extends('layouts.app')
@section('page-title', 'Settings')
@section('content')
<h4>Settings</h4>

<div class="card nl-card p-3 mb-3">
  <div class="fw-semibold mb-2">Appearance</div>
  <form method="POST" action="{{ route('settings.theme') }}" class="d-flex align-items-center gap-2" id="themeForm">
    @csrf
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" role="switch" id="darkMode" name="dark_mode" value="1" @checked($dark)>
      <label class="form-check-label" for="darkMode">Dark mode</label>
    </div>
  </form>
  <div class="small text-muted mt-2">Applies to your current session.</div>
  </div>

<div class="card nl-card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div class="fw-semibold">Change Password</div>
  </div>
  <form method="POST" action="{{ route('settings.password') }}" class="row g-2 mt-2" autocomplete="off">
    @csrf
    <div class="col-md-4">
      <label class="form-label small">Current Password</label>
      <input type="password" name="current_password" class="form-control form-control-sm" required>
      @error('current_password')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
      <label class="form-label small">New Password</label>
      <input type="password" name="password" class="form-control form-control-sm" minlength="8" required>
      @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
      <label class="form-label small">Confirm Password</label>
      <input type="password" name="password_confirmation" class="form-control form-control-sm" minlength="8" required>
    </div>
    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary btn-sm nl-btn">Update Password</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const toggle = document.getElementById('darkMode');
    const form = document.getElementById('themeForm');
    if (toggle && form) {
      toggle.addEventListener('change', function(){
        form.submit();
      });
    }
  });
</script>
@endpush
