<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', config('app.name', 'NutriFlow'))</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/nutriflow-logo.svg') }}">
  <meta name="theme-color" content="#7ec043">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  {{-- Load Vite assets only if built to avoid manifest error on fresh installs --}}
  @php
    $viteManifest = public_path('build/manifest.json');
  @endphp
  @if (file_exists($viteManifest))
    @vite(['resources/css/app.css','resources/js/app.js'])
  @endif
  <style>
    :root {
      --nf-navy: #082858;
      --nf-navy-soft: #173763;
      --nf-blue: #0b3b82;
      --nf-amber: #ffb703;
      --nf-muted: #7082a6;
      --nf-bg: #f4f6fa;
      --nf-line: #e4e9f2;
      --nf-card: #ffffff;
      --nf-surface: #ffffff;
      --nf-surface-soft: #f7f9fc;
      --nf-surface-raised: #fbfcfe;
      --nf-control-bg: #ffffff;
      --nf-control-border: #dbe3ef;
      --nf-text-primary: #082858;
      --nf-text-secondary: #526894;
      --nf-text-muted: #7082a6;
      --nf-link: #0b3b82;
      --nf-accent-text: #c78200;
      --bs-primary: var(--nf-blue);
      --bs-success: #138a55;
      --bs-link-color: var(--nf-blue);
    }
    * { letter-spacing: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--nf-bg); color: var(--nf-text-primary); }
    .app-shell { min-height: 100vh; display: flex; }
    .sidebar {
      position: sticky;
      top: 0;
      width: 224px;
      min-width: 224px;
      height: 100vh;
      background: #082858;
      color: #dbe7fb;
      display: flex;
      flex-direction: column;
      padding: 1.25rem .65rem;
    }
    .brand-block { display: flex; align-items: center; gap: .7rem; padding: 1.2rem .55rem 1.6rem; }
    .brand-mark {
      position: relative;
      width: 42px;
      height: 42px;
      flex: 0 0 42px;
      overflow: hidden;
      border-radius: 12px;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,.18));
    }
    .brand-logo-art {
      position: absolute;
      top: 2.5%;
      left: -34.2%;
      width: 168.3%;
      height: auto;
      max-width: none;
    }
    .svg-icon { width: 1rem; height: 1rem; flex: 0 0 auto; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; }
    .svg-icon.solid { fill: currentColor; stroke: none; }
    .brand-title { color: #fff; font-weight: 800; line-height: 1; }
    .brand-subtitle { color: #94a9cf; font-size: .72rem; margin-top: .22rem; }
    .side-nav { display: grid; gap: .35rem; }
    .side-link {
      color: #b8c8e4;
      display: flex;
      align-items: center;
      gap: .7rem;
      text-decoration: none;
      padding: .72rem .85rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: .92rem;
    }
    .side-link:hover { color: #fff; background: rgba(255,255,255,.08); }
    .side-link.active { color: var(--nf-amber); background: rgba(255,255,255,.1); }
    .side-footer { margin-top: auto; padding: .75rem .55rem; min-width: 0; overflow: hidden; }
    .user-chip { display: flex; align-items: center; gap: .65rem; color: #fff; min-width: 0; overflow: hidden; }
    .user-copy { min-width: 0; flex: 1 1 auto; overflow: hidden; }
    .user-copy .text-truncate { max-width: 100%; }
    .logout-form { flex: 0 0 auto; }
    .avatar {
      width: 32px; height: 32px; border-radius: 50%;
      display: grid; place-items: center;
      background: var(--nf-amber); color: #082858;
      font-weight: 800; font-size: .8rem;
      flex: 0 0 auto;
    }
    .main-wrap { flex: 1; min-width: 0; }
    .topbar {
      height: 40px;
      background: #fff;
      border-bottom: 1px solid var(--nf-line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.35rem;
    }
    .topbar-title { font-size: .88rem; font-weight: 800; color: #082858; }
    .search-pill {
      width: min(280px, 42vw);
      height: 32px;
      border: 1px solid #d3dcea;
      border-radius: 9px;
      background: #f1f5fb;
      color: #8292b4;
      padding: 0 .85rem;
      font-size: .82rem;
    }
    .content-wrap { padding: 1.7rem; }
    .nl-card { border: 1px solid var(--nf-line); border-radius: 10px; box-shadow: 0 3px 10px rgba(8,40,88,.06); background: var(--nf-card); }
    .nl-btn { border-radius: 8px; font-weight: 700; }
    .nf-suggest-wrap { position: relative; min-width: 0; }
    .nf-suggest-wrap > input { width: 100%; }
    .nf-suggest-menu {
      position: absolute;
      z-index: 1050;
      top: calc(100% + 6px);
      left: 0;
      right: 0;
      display: none;
      max-height: 220px;
      overflow-y: auto;
      border: 1px solid #dbe3ef;
      border-radius: 9px;
      background: #fff;
      box-shadow: 0 12px 28px rgba(8,40,88,.14);
      padding: .25rem;
    }
    .nf-suggest-menu.show { display: block; }
    .nf-suggest-option {
      width: 100%;
      border: 0;
      border-radius: 7px;
      background: transparent;
      color: #082858;
      display: block;
      padding: .48rem .65rem;
      text-align: left;
      font-size: .84rem;
      font-weight: 700;
    }
    .nf-suggest-option:hover, .nf-suggest-option:focus {
      background: #eef4fb;
      outline: none;
    }
    .btn-primary {
      --bs-btn-bg: var(--nf-blue);
      --bs-btn-border-color: var(--nf-blue);
      --bs-btn-hover-bg: #092f68;
      --bs-btn-hover-border-color: #092f68;
    }
    .btn-success {
      --bs-btn-bg: #138a55;
      --bs-btn-border-color: #138a55;
      --bs-btn-hover-bg: #107747;
      --bs-btn-hover-border-color: #107747;
    }
    .text-muted { color: var(--nf-muted) !important; }
    @media (max-width: 900px) {
      .app-shell { display: block; }
      .sidebar { position: relative; width: 100%; min-width: 0; height: auto; padding: .8rem; }
      .brand-block { padding: .25rem .4rem .75rem; }
      .side-nav { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .side-footer { display: none; }
      .content-wrap { padding: 1rem; }
      .topbar { padding: 0 1rem; }
    }
  </style>
    <style>
      /* Shared dark-theme tokens and component treatments. */
      body.dark {
        --nf-bg: #0d1420;
        --nf-line: #33445a;
        --nf-card: #151f2d;
        --nf-surface: #151f2d;
        --nf-surface-soft: #1b2736;
        --nf-surface-raised: #202d3e;
        --nf-control-bg: #101923;
        --nf-control-border: #3b4b60;
        --nf-text-primary: #f4f7fb;
        --nf-text-secondary: #d6dfeb;
        --nf-text-muted: #a7b4c8;
        --nf-link: #9ecbff;
        --nf-accent-text: #ffd166;
        color-scheme: dark;
        background: var(--nf-bg);
        color: var(--nf-text-primary);
      }
      body.dark .nl-card,
      body.dark .card,
      body.dark .modal-content { background: var(--nf-card); color: var(--nf-text-primary); border-color: var(--nf-line); }
      body.dark .topbar { background: var(--nf-card); border-color: var(--nf-line); }
      body.dark .topbar-title,
      body.dark h1,
      body.dark h2,
      body.dark h3,
      body.dark h4,
      body.dark h5,
      body.dark h6 { color: var(--nf-text-primary); }
      body.dark a { color: var(--nf-link); }
      body.dark .text-muted,
      body.dark .small.text-muted,
      body.dark .card .text-muted { color: var(--nf-text-muted) !important; }
      body.dark .form-text { color: var(--nf-text-muted); }
      body.dark .form-label { color: var(--nf-text-secondary); }
      body.dark .table { color: var(--nf-text-secondary); border-color: var(--nf-line); }
      body.dark .table > :not(caption) > * > * { color: var(--nf-text-secondary); background-color: transparent; border-color: var(--nf-line); }
      body.dark .content-wrap :where(
        .dash-hello h1, .metric-value, .panel-title, .risk-number,
        .children-title, .student-cell, .grade-summary, .section-summary, .section-student,
        .profile-title, .profile-name, .fact-value, .metric-value-profile, .bmi-summary-value,
        .growth-entry-title, .growth-source-table td, .create-title, .edit-title, .section-label,
        .meals-title, .meal-student, .schedule-title, .nav-range, .session-name,
        .student-picker-name, .menu-picker-row, .reports-page, .reports-title,
        .legend-count, .report-name, .import-title
      ) { color: var(--nf-text-primary); }
      body.dark .content-wrap :where(
        .dash-hello p, .metric-label, .metric-sub, .panel-subtitle,
        .children-subtitle, .table-footer, .student-meta, .profile-crumb, .fact-label,
        .metric-label-profile, .bmi-summary-label, .growth-entry-copy, .growth-source-table th,
        .create-subtitle, .edit-subtitle, .helper-copy, .meals-subtitle, .meal-meta, .meal-footer,
        .schedule-subtitle, .day-divider, .session-count, .session-grade, .session-meta, .student-picker-meta,
        .student-picker-count, .reports-subtitle, .filter-label, .filter-chip, .metric-title,
        .metric-note, .legend-row, .legend-percent, .chart-legend, .student-result-count,
        .report-table td, .import-subtitle, .import-step, .template-table td, .template-table th,
        .schedule-empty
      ) { color: var(--nf-text-muted); }
      body.dark .content-wrap :where(
        .schedule-item, .schedule-empty, .grade-card, .session-card, .growth-entry-card,
        .last-meal-box, .student-picker-list, .menu-picker-list
      ) { background: var(--nf-surface-soft); border-color: var(--nf-line); }
      body.dark .content-wrap :where(
        .mode-tabs, .bmi-period-tabs, .range-switch, .view-pill, .back-square, .icon-button,
        .count-chip, .meal-type-pill, .food-chip, .column-chip
      ) { background: var(--nf-surface-raised); color: var(--nf-text-secondary); border-color: var(--nf-line); }
      body.dark .content-wrap :where(.mode-tab.active, .bmi-period-tabs a.active, .range-switch a.active) {
        background: var(--nf-control-bg);
        color: var(--nf-accent-text);
      }
      body.dark .content-wrap :where(.schedule-date-label, .slot-icon, .link-action.warn) { color: var(--nf-accent-text); }
      body.dark .content-wrap :where(.link-action, .mini-pagination a, .mini-pagination span) { color: var(--nf-link); }
      body.dark .content-wrap :where(
        .form-control, .form-select, .search-pill, .student-search, .children-select,
        .add-section-input, .schedule-search, .schedule-select, .filter-control, .icon-btn
      ) { background: var(--nf-control-bg); color: var(--nf-text-primary); border-color: var(--nf-control-border); }
      body.dark :where(input, textarea)::placeholder { color: var(--nf-text-muted); opacity: 1; }
      body.dark :where(select, option) { background-color: var(--nf-control-bg); color: var(--nf-text-primary); }
      body.dark .content-wrap :where(
        .children-table thead th, .meal-table thead th, .growth-source-table thead th, .report-table th
      ) { background-color: var(--nf-surface-raised) !important; color: var(--nf-text-secondary); border-color: var(--nf-line); }
      body.dark .content-wrap .report-table td { border-color: var(--nf-line); }
      body.dark .content-wrap :where(.section-stack, .section-student, .student-picker-row, .menu-picker-row, .student-table-scroll, .growth-table-scroll) {
        border-color: var(--nf-line);
      }
      body.dark .content-wrap .section-student:hover { background: var(--nf-surface-raised); }
      body.dark .content-wrap :where(.risk-severe, .profile-metric.bmi-risk, .allergy-row, .allergy-notice, .result-box.blocked) {
        background: #341f27;
        border-color: #7f4550;
      }
      body.dark .content-wrap .risk-moderate { background: #342a18; border-color: #7d6223; }
      body.dark .content-wrap .risk-severe { --risk-alert-accent: #ff8a94; }
      body.dark .content-wrap .risk-moderate { --risk-alert-accent: #ffd166; }
      body.dark .content-wrap .risk-card :where(.risk-dot, .risk-label) { color: var(--risk-alert-accent); }
      body.dark .content-wrap .risk-card .risk-dot { background-color: var(--risk-alert-accent) !important; }
      body.dark .content-wrap .result-box.success { background: #153526; border-color: #2f6b4e; }
      body.dark .modal-header,
      body.dark .modal-footer { border-color: var(--nf-line); }
      body.dark .modal-header .btn-close { filter: invert(1) grayscale(1); }
      body.dark .btn-light { background: var(--nf-surface-raised); color: var(--nf-text-primary); border-color: var(--nf-line); }
      body.dark .btn-outline-secondary { color: var(--nf-link); border-color: #60738b; }
      body.dark .btn-outline-primary { color: var(--nf-link); border-color: #4d83bc; }
      body.dark .alert-success { background:#153526; color:#a9e8bd; border-color:#2f6b4e; }
      body.dark .nf-suggest-menu { background:var(--nf-card); border-color:var(--nf-line); box-shadow:0 12px 28px rgba(0,0,0,.28); }
      body.dark .nf-suggest-option { color:var(--nf-text-primary); }
      body.dark .nf-suggest-option:hover,
      body.dark .nf-suggest-option:focus { background:var(--nf-surface-raised); }
      body.dark .badge.bg-light { background-color:var(--nf-surface-raised) !important; color:var(--nf-text-primary) !important; border:1px solid var(--nf-line); }
      body.dark .badge.bg-warning.text-dark { color:#1a1a1a !important; }
      body.dark code { color:var(--nf-accent-text); }
      body.dark hr { border-color: var(--nf-line); opacity: 1; }
    </style>
</head>
<body class="{{ session('dark_mode') ? 'dark' : '' }}">
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand-block">
      <div class="brand-mark">
        <img class="brand-logo-art" src="{{ asset('assets/nutriflow-logo.svg') }}" alt="">
      </div>
      <div>
        <div class="brand-title">NutriFlow</div>
        <div class="brand-subtitle">{{ auth()->user()?->school?->name ?? 'School nutrition' }}</div>
      </div>
    </div>
    <nav class="side-nav">
      <a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/></svg><span>Dashboard</span></a>
      <a class="side-link {{ request()->routeIs('students.*') ? 'active' : '' }}" href="{{ route('students.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Children</span></a>
      <a class="side-link {{ request()->routeIs('meals.*') ? 'active' : '' }}" href="{{ route('meals.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg><span>Meal Logs</span></a>
      <a class="side-link {{ request()->routeIs('menu-items.*') ? 'active' : '' }}" href="{{ route('menu-items.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/></svg><span>Meal Catalog</span></a>
      <a class="side-link {{ request()->routeIs('feeding-schedules.*') ? 'active' : '' }}" href="{{ route('feeding-schedules.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg><span>Schedule</span></a>
      <a class="side-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/></svg><span>Reports</span></a>
    </nav>
    <div class="side-footer">
      <a class="side-link {{ request()->routeIs('settings.*') || request()->routeIs('student-import.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a8 8 0 0 0 .1-2l2-1.5-2-3.5-2.4 1a8 8 0 0 0-1.7-1L15 5.5h-4L10.6 8a8 8 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a8 8 0 0 0 .1 2l-2 1.5 2 3.5 2.4-1a8 8 0 0 0 1.7 1l.4 2.5h4l.4-2.5a8 8 0 0 0 1.7-1l2.4 1 2-3.5-2.2-1.5Z"/></svg><span>Settings</span></a>
      @auth
        <div class="user-chip mt-3">
          <div class="avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($p) => strtoupper(substr($p, 0, 1)))->take(2)->implode('') }}</div>
          <div class="user-copy">
            <div class="fw-bold small text-truncate">{{ auth()->user()->name }}</div>
            <div class="small text-muted">{{ auth()->user()->roleLabel() }}</div>
          </div>
          <form method="POST" action="{{ route('logout') }}" class="logout-form" id="logoutForm">
            @csrf
            <button type="button" class="btn btn-link text-muted p-0" title="Log out" aria-label="Open logout confirmation" data-bs-toggle="modal" data-bs-target="#logoutConfirmationModal"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18"/></svg></button>
          </form>
        </div>
      @endauth
    </div>
  </aside>
  <main class="main-wrap">
    <header class="topbar">
      <div class="topbar-title">@yield('page-title', 'Dashboard')</div>
      <div class="d-flex align-items-center gap-3">
        @php
          $topbarSchoolId = auth()->user()?->school_id;
          $topbarSuggestions = collect();
          if (auth()->check()) {
              $topbarSuggestions = \App\Models\Student::when($topbarSchoolId, fn($q) => $q->where('school_id', $topbarSchoolId))
                ->orderBy('name')
                ->limit(80)
                ->get(['name', 'class_name', 'section'])
                ->flatMap(fn($student) => [$student->name, $student->class_name, $student->section])
                ->merge(\App\Models\Food::when($topbarSchoolId, fn($q) => $q->where('school_id', $topbarSchoolId))->orderBy('name')->limit(40)->pluck('name'))
                ->filter()
                ->unique()
                ->values();
          }
        @endphp
        <input class="search-pill" type="search" placeholder="Search..." aria-label="Search" list="topbarSearchSuggestions">
        <datalist id="topbarSearchSuggestions">
          @foreach($topbarSuggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
          @endforeach
        </datalist>
        <svg class="svg-icon text-muted" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
      </div>
    </header>
    <div class="content-wrap">
      @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      @yield('content')
    </div>
  </main>
</div>
@auth
  <div class="modal fade" id="logoutConfirmationModal" tabindex="-1" aria-labelledby="logoutConfirmationTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="logoutConfirmationTitle">Log out of NutriFlow?</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to log out? You will need to sign in again to continue.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary nl-btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger nl-btn" form="logoutForm">Log out</button>
        </div>
      </div>
    </div>
  </div>
@endauth
@stack('modals')
@stack('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const suggestionInputs = Array.from(document.querySelectorAll('input[list]'));

    function closeAll(except) {
      document.querySelectorAll('.nf-suggest-menu.show').forEach(function(menu) {
        if (menu !== except) menu.classList.remove('show');
      });
    }

    suggestionInputs.forEach(function(input) {
      const listId = input.getAttribute('list');
      const sourceList = listId ? document.getElementById(listId) : null;
      if (!sourceList) return;

      const suggestions = Array.from(sourceList.querySelectorAll('option'))
        .map(option => option.value.trim())
        .filter(Boolean)
        .filter((value, index, values) => values.indexOf(value) === index);

      input.removeAttribute('list');
      sourceList.hidden = true;

      const wrapper = document.createElement('div');
      wrapper.className = 'nf-suggest-wrap';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);

      const menu = document.createElement('div');
      menu.className = 'nf-suggest-menu';
      wrapper.appendChild(menu);

      function render() {
        const query = input.value.trim().toLowerCase();
        menu.innerHTML = '';

        if (!query) {
          menu.classList.remove('show');
          return;
        }

        const matches = suggestions
          .filter(value => value.toLowerCase().includes(query))
          .slice(0, 8);

        if (!matches.length) {
          menu.classList.remove('show');
          return;
        }

        matches.forEach(function(value) {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'nf-suggest-option';
          button.textContent = value;
          button.addEventListener('mousedown', function(event) {
            event.preventDefault();
            input.value = value;
            menu.classList.remove('show');
            input.focus();
          });
          menu.appendChild(button);
        });

        closeAll(menu);
        menu.classList.add('show');
      }

      input.addEventListener('input', render);
      input.addEventListener('focus', render);
      input.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
          menu.classList.remove('show');
        }
      });
      input.addEventListener('blur', function() {
        setTimeout(() => menu.classList.remove('show'), 120);
      });
    });

    document.addEventListener('click', function(event) {
      if (!event.target.closest('.nf-suggest-wrap')) closeAll();
    });
  });
</script>
</body>
</html>
