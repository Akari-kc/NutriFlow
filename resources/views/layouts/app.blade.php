<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', config('app.name', 'NutriFlow'))</title>
  <link rel="icon" type="image/png" href="{{ asset('images/nutrilog_logo.png') }}">
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
      --bs-primary: var(--nf-blue);
      --bs-success: #138a55;
      --bs-link-color: var(--nf-blue);
    }
    * { letter-spacing: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--nf-bg); color: #082858; }
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
      width: 38px; height: 38px; border-radius: 50%;
      display: grid; place-items: center;
      border: 2px solid rgba(255,183,3,.55);
      color: var(--nf-amber);
      font-size: 1.15rem;
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
      /* Simple dark theme */
      body.dark { background: #0f1214; color: #e8e8e8; }
      body.dark .nl-card { background: #161b1f; color: #e8e8e8; }
      body.dark .topbar { background: #161b1f; border-color: #2a2f34; }
      body.dark .topbar-title, body.dark h1, body.dark h2, body.dark h3, body.dark h4, body.dark h5, body.dark h6 { color: #e8e8e8; }
      body.dark a { color: #cfe8ff; }
      /* Improve contrast for muted/helper texts */
      body.dark .text-muted, body.dark .small.text-muted, body.dark .card .text-muted { color: #a8b3be !important; }
      body.dark .form-text { color: #9aa5b1; }
      body.dark .form-label { color: #cfd8e3; }
      body.dark .table { color: #ddd; }
      body.dark .table > :not(caption) > * > * { color: #ddd; background-color: transparent; }
      body.dark .form-control, body.dark .form-select, body.dark .search-pill { background:#111418; color:#e8e8e8; border-color:#2a2f34; }
      body.dark .form-control::placeholder { color:#9aa5b1; }
      body.dark .nf-suggest-menu { background:#161b1f; border-color:#2a2f34; box-shadow: 0 12px 28px rgba(0,0,0,.28); }
      body.dark .nf-suggest-option { color:#e8e8e8; }
      body.dark .nf-suggest-option:hover, body.dark .nf-suggest-option:focus { background:#253041; }
      body.dark .btn-outline-secondary { color:#cfe8ff; border-color:#425466; }
      body.dark .btn-outline-primary { color:#9ecbff; border-color:#2b6cb0; }
      body.dark .alert-success { background:#0f2a18; color:#9be7a6; border-color:#1b3a26; }
      /* Badges and chips on dark background */
      body.dark .badge.bg-light { background-color:#2a2f34 !important; color:#e8e8e8 !important; border:1px solid #3a4046; }
      body.dark .badge.bg-warning.text-dark { color:#1a1a1a !important; }
      body.dark code { color:#ffd479; }
    </style>
</head>
<body class="{{ session('dark_mode') ? 'dark' : '' }}">
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand-block">
      <div class="brand-mark">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V9"/><path d="M12 9c-4 0-6-2-6-6 4 0 6 2 6 6Z"/><path d="M12 12c4 0 6-2 6-6-4 0-6 2-6 6Z"/><path d="M7 21h10"/></svg>
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
      <a class="side-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a8 8 0 0 0 .1-2l2-1.5-2-3.5-2.4 1a8 8 0 0 0-1.7-1L15 5.5h-4L10.6 8a8 8 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a8 8 0 0 0 .1 2l-2 1.5 2 3.5 2.4-1a8 8 0 0 0 1.7 1l.4 2.5h4l.4-2.5a8 8 0 0 0 1.7-1l2.4 1 2-3.5-2.2-1.5Z"/></svg><span>Settings</span></a>
      @auth
        <div class="user-chip mt-3">
          <div class="avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($p) => strtoupper(substr($p, 0, 1)))->take(2)->implode('') }}</div>
          <div class="user-copy">
            <div class="fw-bold small text-truncate">{{ auth()->user()->name }}</div>
            <div class="small text-muted">Nutrition Aide</div>
          </div>
          <form method="POST" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button class="btn btn-link text-muted p-0" title="Logout"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18"/></svg></button>
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
