<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NutriFlow - Sign In</title>
  <style>
    :root {
      --nf-navy: #10366f;
      --nf-deep: #0b2858;
      --nf-gold: #f5b400;
      --nf-blue: #174987;
      --nf-muted: #8494b8;
      --nf-line: #d3dbea;
      --nf-field: #eef2f8;
    }

    * { box-sizing: border-box; }

    html, body { min-height: 100%; }

    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--nf-deep);
      background-color: #153e77;
      background-image:
        radial-gradient(circle at 1px 1px, rgba(255,255,255,.08) 1px, transparent 0),
        linear-gradient(135deg, #12376f 0%, #164a8b 100%);
      background-size: 28px 28px, cover;
    }

    .login-page {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 32px;
    }

    .login-window {
      width: min(844px, 96vw);
      min-height: 516px;
      display: grid;
      grid-template-columns: 342px minmax(0, 1fr);
      background: #fff;
      border: 1px solid rgba(255,255,255,.22);
      border-radius: 9px;
      overflow: hidden;
      box-shadow: 0 22px 70px rgba(2, 18, 47, .34);
    }

    .brand-panel {
      position: relative;
      overflow: hidden;
      padding: 42px 40px;
      color: #fff;
      background: linear-gradient(180deg, #123a76 0%, #0d2d63 100%);
    }

    .brand-panel::before,
    .brand-panel::after {
      content: "";
      position: absolute;
      border: 1px solid rgba(245, 180, 0, .18);
      border-radius: 999px;
      pointer-events: none;
    }

    .brand-panel::before {
      width: 250px;
      height: 250px;
      right: -96px;
      bottom: -76px;
    }

    .brand-panel::after {
      width: 158px;
      height: 158px;
      right: -42px;
      bottom: -38px;
    }

    .brand-mark {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 36px;
    }

    .brand-icon {
      width: 46px;
      height: 46px;
      display: grid;
      place-items: center;
      border: 2px solid rgba(245, 180, 0, .55);
      border-radius: 999px;
      color: var(--nf-gold);
      font-size: 22px;
    }

    .brand-name {
      font-size: 16px;
      font-weight: 800;
      letter-spacing: 0;
      line-height: 1.1;
    }

    .brand-school {
      margin-top: 4px;
      color: var(--nf-gold);
      font-size: 11px;
      line-height: 1.2;
    }

    .brand-title {
      max-width: 250px;
      margin: 0 0 14px;
      color: #fff;
      font-size: 24px;
      line-height: 1.28;
      font-weight: 850;
      letter-spacing: 0;
    }

    .brand-copy {
      max-width: 260px;
      margin: 0 0 26px;
      color: #d8e1f2;
      font-size: 15px;
      line-height: 1.62;
    }

    .brand-footer {
      position: relative;
      z-index: 1;
      color: rgba(216,225,242,.58);
      font-size: 12px;
      margin-top: 12px;
    }

    .svg-icon {
      width: 1rem;
      height: 1rem;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
      fill: none;
    }

    .form-panel {
      display: grid;
      grid-template-rows: 32px 1fr 30px;
      min-width: 0;
      background: #fff;
    }

    .window-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 14px;
      border-bottom: 1px solid #e1e6ef;
      background: #f7f8fb;
      color: #8796b5;
      font-size: 12px;
    }

    .window-actions {
      display: flex;
      align-items: center;
      gap: 24px;
      color: #8a9abb;
      font-size: 14px;
    }

    .signin-body {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 42px 40px 34px;
    }

    .signin-title {
      margin: 0;
      color: var(--nf-deep);
      font-size: 25px;
      line-height: 1.2;
      font-weight: 850;
      letter-spacing: 0;
    }

    .signin-subtitle {
      margin: 6px 0 28px;
      color: var(--nf-muted);
      font-size: 14px;
    }

    .field-row { margin-bottom: 16px; }

    .field-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 7px;
      color: #52658b;
      font-size: 12px;
      font-weight: 700;
    }

    .field-head a {
      color: #c77c00;
      text-decoration: none;
      font-weight: 750;
    }

    .input-shell {
      position: relative;
    }

    .signin-input {
      width: 100%;
      height: 43px;
      border: 1px solid #cbd5e6;
      border-radius: 4px;
      padding: 0 13px;
      color: var(--nf-deep);
      background: var(--nf-field);
      font: inherit;
      outline: none;
    }

    .signin-input:focus {
      border-color: #7f98c4;
      box-shadow: 0 0 0 3px rgba(23,73,135,.1);
    }

    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      border: 0;
      padding: 0;
      background: transparent;
      color: #8494b8;
      cursor: pointer;
      font-size: 15px;
    }

    .remember-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 2px;
      color: #3f4250;
      font-size: 14px;
    }

    .remember-row input {
      width: 18px;
      height: 18px;
      margin: 0;
      accent-color: var(--nf-blue);
    }

    .signin-button {
      width: 100%;
      height: 44px;
      border: 0;
      border-radius: 6px;
      margin-top: 22px;
      background: #0b3b82;
      color: #fff;
      font-size: 15px;
      font-weight: 850;
      cursor: pointer;
      box-shadow: 0 10px 24px rgba(11,59,130,.22);
    }

    .signin-button:hover { background: #082f6a; }

    .demo-note {
      margin-top: 22px;
      color: #8796b5;
      font-size: 12px;
      text-align: center;
    }

    .demo-note strong { color: #c77c00; }

    .error-box {
      border: 1px solid #ffc9c4;
      border-radius: 7px;
      padding: 10px 12px;
      margin-bottom: 18px;
      background: #fff5f4;
      color: #d92d20;
      font-size: 13px;
      font-weight: 700;
    }

    .status-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 14px;
      border-top: 1px solid #e1e6ef;
      background: #f7f8fb;
      color: #8796b5;
      font-size: 12px;
    }

    @media (max-width: 820px) {
      .login-page { padding: 18px; }
      .login-window {
        grid-template-columns: 1fr;
        min-height: auto;
      }

      .brand-panel {
        padding: 30px 28px;
      }

      .brand-mark { margin-bottom: 24px; }
      .brand-title { max-width: none; }
      .brand-copy { max-width: none; }
      .signin-body { padding: 34px 28px; }
    }
  </style>
</head>
<body>
  <main class="login-page">
    <section class="login-window" aria-label="NutriFlow sign in">
      <aside class="brand-panel">
        <div class="brand-mark">
          <div class="brand-icon">
            <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V9"/><path d="M12 9c-4 0-6-2-6-6 4 0 6 2 6 6Z"/><path d="M12 12c4 0 6-2 6-6-4 0-6 2-6 6Z"/><path d="M7 21h10"/></svg>
          </div>
          <div>
            <div class="brand-name">NutriFlow</div>
            <div class="brand-school">Eulogio Rodriguez Integrated School</div>
          </div>
        </div>

        <h1 class="brand-title">School Feeding Program Tracker</h1>
        <p class="brand-copy">Monitor student nutrition, track BMI trends, and manage meal programs for Mandaluyong's learners.</p>

        <div class="brand-footer">City of Mandaluyong - DepEd NCR</div>
      </aside>

      <section class="form-panel">
        <div class="window-bar">
          <span>NutriFlow - Sign In</span>
          <span class="window-actions" aria-hidden="true">
            <span>-</span>
            <span>□</span>
            <span>×</span>
          </span>
        </div>

        <div class="signin-body">
          <div>
            <h2 class="signin-title">Welcome back</h2>
            <p class="signin-subtitle">Sign in to your NutriFlow account</p>

            @if ($errors->any())
              <div class="error-box">
                @foreach ($errors->all() as $error)
                  <div>{{ $error }}</div>
                @endforeach
              </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
              @csrf
              <div class="field-row">
                <label class="field-head" for="email">
                  <span>Email address</span>
                </label>
                <input id="email" class="signin-input" type="email" name="email" value="{{ old('email', 'aide@example.com') }}" placeholder="you@eris.edu.ph" required autofocus>
              </div>

              <div class="field-row">
                <div class="field-head">
                  <label for="password">Password</label>
                  <a href="#" onclick="return false;">Forgot password?</a>
                </div>
                <div class="input-shell">
                  <input id="password" class="signin-input" type="password" name="password" placeholder="Password" required>
                  <button class="password-toggle" type="button" aria-label="Show password" id="passwordToggle">
                    <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
              </div>

              <label class="remember-row" for="remember">
                <input type="checkbox" name="remember" id="remember">
                <span>Keep me signed in</span>
              </label>

              <button class="signin-button" type="submit">Sign In</button>
            </form>

            <div class="demo-note">Demo: use <strong>aide@example.com</strong> with password <strong>password</strong></div>
          </div>
        </div>

        <div class="status-bar">
          <span>NutriFlow v2.4.1</span>
          <span>Eulogio Rodriguez Integrated School - 2026</span>
        </div>
      </section>
    </section>
  </main>

  <script>
    const toggle = document.getElementById('passwordToggle');
    const password = document.getElementById('password');

    toggle?.addEventListener('click', () => {
      const isPassword = password.type === 'password';
      password.type = isPassword ? 'text' : 'password';
      toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      toggle.innerHTML = isPassword
        ? '<svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 2.8 2.8"/><path d="M9.9 4.2A10.6 10.6 0 0 1 12 4c6.5 0 10 8 10 8a17.8 17.8 0 0 1-3.1 4.4"/><path d="M6.1 6.1C3.5 7.9 2 12 2 12s3.5 8 10 8a10.7 10.7 0 0 0 5.9-1.8"/></svg>'
        : '<svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    });
  </script>
</body>
</html>
