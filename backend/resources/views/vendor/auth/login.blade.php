<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Login — eSahlan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #FF8A00; --brand-dark: #e07500; --navy: #07003B;
            --sidebar-bg: #0c0148; --bg: #f0f2f8; --surface: #ffffff;
            --text: #1a1a2e; --text-muted: #7b7fa8; --border: #e8eaf0;
            --danger: #ef4444; --radius: 14px;
        }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--navy) 0%, #1a0874 50%, #0c0148 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff; border-radius: 20px; padding: 40px;
            width: 100%; max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }
        .login-logo {
            display: flex; align-items: center; gap: 12px; margin-bottom: 32px;
        }
        .logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--brand), #ff6200);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff;
            box-shadow: 0 6px 18px rgba(255,138,0,0.4);
        }
        .logo-text h1 { font-size: 22px; font-weight: 800; color: var(--text); letter-spacing: -.4px; }
        .logo-text p  { font-size: 11px; color: var(--text-muted); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 1px; }
        .login-title { font-size: 17px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .login-sub   { font-size: 13px; color: var(--text-muted); margin-bottom: 28px; }
        .form-group  { margin-bottom: 18px; }
        .form-label  { display: block; font-size: 12.5px; font-weight: 700; color: var(--text); margin-bottom: 7px; }
        .input-wrap  { position: relative; }
        .input-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .form-control {
            width: 100%; padding: 11px 13px 11px 38px; border-radius: 10px;
            border: 1.5px solid var(--border); font-size: 14px; color: var(--text);
            background: #fafbff; outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(255,138,0,0.1); background: #fff; }
        .error-msg { display: flex; align-items: center; gap: 7px; background: rgba(239,68,68,0.07); border: 1px solid rgba(239,68,68,0.2); color: #b91c1c; padding: 10px 14px; border-radius: 9px; font-size: 13px; margin-bottom: 16px; }
        .btn-login {
            width: 100%; padding: 13px; border-radius: 11px;
            background: linear-gradient(135deg, var(--brand), #ff6200);
            color: #fff; font-size: 15px; font-weight: 800;
            border: none; cursor: pointer; letter-spacing: .3px;
            box-shadow: 0 6px 20px rgba(255,138,0,0.35);
            transition: all .2s;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(255,138,0,0.45); }
        .btn-login:active { transform: translateY(0); }
        .login-footer { text-align: center; margin-top: 22px; font-size: 12px; color: var(--text-muted); }
        .login-footer a { color: var(--brand); text-decoration: none; font-weight: 600; }
        .login-footer a:hover { text-decoration: underline; }
        .particles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
        .particle {
            position: absolute; width: 2px; height: 2px; background: rgba(255,138,0,.25); border-radius: 50%;
            animation: float var(--dur, 8s) var(--delay, 0s) infinite ease-in-out;
        }
        @keyframes float {
            0%,100% { transform: translate(0,0) scale(1); opacity: 0; }
            20% { opacity: 1; }
            80% { opacity: .5; }
            to  { transform: translate(var(--tx,20px),var(--ty,-60px)) scale(.5); opacity: 0; }
        }
    </style>
</head>
<body>
<div class="particles" aria-hidden="true">
    @for($i=0;$i<20;$i++)
    <div class="particle" style="
        left:{{ rand(0,100) }}%;top:{{ rand(20,100) }}%;
        --dur:{{ rand(6,14) }}s;--delay:{{ rand(0,8) }}s;
        --tx:{{ rand(-30,30) }}px;--ty:{{ rand(-80,-20) }}px;
        width:{{ rand(2,5) }}px;height:{{ rand(2,5) }}px;
    "></div>
    @endfor
</div>

<div class="login-card">
    <div class="login-logo">
        <div class="logo-icon"><i class="fa-solid fa-store"></i></div>
        <div class="logo-text">
            <h1>eSahlan</h1>
            <p>Vendor Panel</p>
        </div>
    </div>

    <h2 class="login-title">Welcome back</h2>
    <p class="login-sub">Sign in to manage your store</p>

    @if($errors->any())
    <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form action="{{ route('vendor.login.post') }}" method="POST">
        @csrf
        <div class="form-group">
            <label class="form-label">Phone Number</label>
            <div class="input-wrap">
                <i class="fa-solid fa-phone"></i>
                <input type="text" name="phone" class="form-control" placeholder="e.g. 252612345678" value="{{ old('phone') }}" required autofocus>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">PIN / Password</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Enter your 4-digit PIN" required>
            </div>
        </div>
        <button type="submit" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> &nbsp;Sign In</button>
    </form>

    <div class="login-footer">
        <a href="{{ url('/admin/login') }}">Admin Panel</a> &nbsp;·&nbsp;
        <a href="{{ url('/') }}">Back to Home</a>
    </div>
</div>
</body>
</html>
