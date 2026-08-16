<!DOCTYPE html>
<html lang="so">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eSahlan Staff — Gal</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      min-height: 100vh;
      background: #0e0d2e;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Segoe UI', system-ui, sans-serif;
      padding: 20px; overflow: hidden; position: relative;
    }

    /* Ambient glows */
    .glow-orange {
      position: absolute; width: 560px; height: 560px; border-radius: 50%;
      background: radial-gradient(circle, rgba(247,148,29,.18) 0%, transparent 70%);
      top: -180px; right: -120px; pointer-events: none;
    }
    .glow-purple {
      position: absolute; width: 400px; height: 400px; border-radius: 50%;
      background: radial-gradient(circle, rgba(99,102,241,.1) 0%, transparent 70%);
      bottom: -100px; left: -100px; pointer-events: none;
    }
    .glow-center {
      position: absolute; width: 700px; height: 200px;
      background: radial-gradient(ellipse, rgba(247,148,29,.06) 0%, transparent 70%);
      top: 50%; left: 50%; transform: translate(-50%,-50%); pointer-events: none;
    }

    /* Grid dots background */
    body::before {
      content: '';
      position: absolute; inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
      background-size: 32px 32px; pointer-events: none;
    }

    .wrap { width: 100%; max-width: 420px; position: relative; z-index: 1; }

    /* Logo + brand */
    .brand-top { text-align: center; margin-bottom: 32px; }
    .brand-logo {
      width: 68px; height: 68px; border-radius: 18px;
      background: linear-gradient(135deg, #F7941D 0%, #e06a00 100%);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px; box-shadow: 0 12px 40px rgba(247,148,29,.4);
    }
    .brand-logo span { font-size: 28px; font-weight: 900; color: #fff; }
    .brand-title { font-size: 22px; font-weight: 800; color: #fff; }
    .brand-title b { color: #F7941D; }
    .brand-sub { font-size: 13px; color: #94a3b8; margin-top: 4px; }

    /* Card */
    .card {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px; padding: 36px;
      backdrop-filter: blur(16px);
      box-shadow: 0 32px 80px rgba(0,0,0,.5);
    }
    .card-title {
      font-size: 16px; font-weight: 700; color: #f1f5f9;
      margin-bottom: 6px;
    }
    .card-sub { font-size: 13px; color: #64748b; margin-bottom: 28px; }

    /* Error */
    .alert-error {
      background: rgba(220,38,38,.12); border: 1px solid rgba(220,38,38,.3);
      color: #fca5a5; border-radius: 10px; padding: 12px 14px;
      font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
    }

    /* Field */
    .field { margin-bottom: 18px; }
    .field label { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; margin-bottom: 7px; text-transform: uppercase; letter-spacing: .06em; }
    .input-wrap { position: relative; }
    .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 14px; }
    .field input {
      width: 100%; padding: 13px 14px 13px 40px;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
      border-radius: 12px; color: #f1f5f9; font-size: 14px;
      transition: border-color .15s, background .15s;
      outline: none;
    }
    .field input::placeholder { color: #475569; }
    .field input:focus { border-color: #F7941D; background: rgba(255,255,255,.09); }
    .toggle-pw { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #64748b; cursor: pointer; font-size: 14px; background: none; border: none; }

    /* Remember */
    .remember { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
    .remember input { width: 15px; height: 15px; accent-color: #F7941D; cursor: pointer; }
    .remember label { font-size: 13px; color: #64748b; cursor: pointer; }

    /* Submit */
    .submit-btn {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, #F7941D, #e06a00);
      border: none; border-radius: 12px;
      color: #fff; font-size: 15px; font-weight: 700;
      cursor: pointer; transition: all .2s;
      box-shadow: 0 8px 24px rgba(247,148,29,.35);
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(247,148,29,.45); }
    .submit-btn:active { transform: translateY(0); }

    /* Divider hint */
    .hint { text-align: center; margin-top: 24px; font-size: 12px; color: #475569; }
    .hint b { color: #64748b; }
  </style>
</head>
<body>
  <div class="glow-orange"></div>
  <div class="glow-purple"></div>
  <div class="glow-center"></div>

  <div class="wrap">
    {{-- Brand --}}
    <div class="brand-top">
      <div class="brand-logo"><span>e</span></div>
      <div class="brand-title">e<b>Sahlan</b> Staff Portal</div>
      <div class="brand-sub">Shaqaalaha u qaaska ah — Employee Self-Service</div>
    </div>

    {{-- Card --}}
    <div class="card">
      <div class="card-title">Ku soo dhawoow 👋</div>
      <div class="card-sub">Isticmaal telefon, email, ama employee number + password/PIN</div>

      @if($errors->any())
      <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        {{ $errors->first() }}
      </div>
      @endif

      <form action="{{ route('employee.login.post') }}" method="POST">
        @csrf

        <div class="field">
          <label>Telefon / Email / Employee No</label>
          <div class="input-wrap">
            <i class="fas fa-user"></i>
            <input type="text" name="login" value="{{ old('login') }}"
              placeholder="0612345678  /  ESH-EMP-0001"
              autocomplete="username" autofocus>
          </div>
        </div>

        <div class="field">
          <label>Password / PIN</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="pw-input"
              placeholder="Password ama 4-6 digit PIN"
              autocomplete="current-password">
            <button type="button" class="toggle-pw" onclick="togglePw()">
              <i class="fas fa-eye" id="pw-icon"></i>
            </button>
          </div>
        </div>

        <div class="remember">
          <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
          <label for="remember">I garowso 30 maalmood</label>
        </div>

        <button type="submit" class="submit-btn">
          <i class="fas fa-sign-in-alt"></i>
          Gal
        </button>
      </form>
    </div>

    <div class="hint">
      Password-ka ma garanayso? <b>Xiriir HR team-kaaga.</b>
    </div>
  </div>

  <script>
    function togglePw() {
      const input = document.getElementById('pw-input');
      const icon  = document.getElementById('pw-icon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }
  </script>
</body>
</html>
