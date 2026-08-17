<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSahlan — Download App</title>
    <meta name="description" content="Download the eSahlan app for iOS and Android. Shop, exchange, learn and more.">

    {{-- Open Graph --}}
    <meta property="og:title" content="eSahlan App">
    <meta property="og:description" content="Your all-in-one super app — eFood, eExchange, eLearning & more.">
    <meta property="og:image" content="https://esahlan.com/images/og-app.png">
    <meta property="og:url" content="https://esahlan.com/app">

    {{-- Smart App Banners (iOS) --}}
    {{-- <meta name="apple-itunes-app" content="app-id=XXXXXXXXX"> --}}

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --brand: #f59e0b;
            --brand-dark: #d97706;
            --navy: #0f172a;
            --navy2: #1e293b;
            --text: #f8fafc;
            --muted: #94a3b8;
            --card: rgba(255,255,255,0.06);
            --border: rgba(255,255,255,0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--navy);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
            position: relative;
        }

        /* Ambient background blobs */
        body::before {
            content: '';
            position: fixed;
            top: -200px; left: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -200px; right: -200px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 42px 36px 36px;
            text-align: center;
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 1;
            animation: fadeUp .5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Logo */
        .logo-wrap {
            width: 88px; height: 88px;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            border-radius: 22px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 16px 40px rgba(245,158,11,0.35);
            font-size: 44px;
        }

        h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.5px;
            margin-bottom: 8px;
        }
        h1 span { color: var(--brand); }

        .tagline {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        /* Detecting state */
        #detecting {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .spinner {
            width: 36px; height: 36px;
            border: 3px solid rgba(245,158,11,0.2);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .detect-text {
            font-size: 13px;
            color: var(--muted);
        }

        /* Progress bar */
        .progress-wrap {
            width: 100%;
            height: 3px;
            background: rgba(255,255,255,0.08);
            border-radius: 99px;
            margin-bottom: 28px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--brand), #f97316);
            border-radius: 99px;
            width: 0%;
            transition: width 2s linear;
        }

        /* Buttons */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 20px;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all .2s;
            margin-bottom: 12px;
        }
        .btn:last-child { margin-bottom: 0; }

        .btn-android {
            background: linear-gradient(135deg, #01875f, #00a86b);
            color: #fff;
            box-shadow: 0 8px 24px rgba(1,135,95,0.35);
        }
        .btn-android:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(1,135,95,0.45); }

        .btn-ios {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        .btn-ios:hover { transform: translateY(-2px); border-color: rgba(255,255,255,0.2); }

        .btn-web {
            background: rgba(255,255,255,0.06);
            color: var(--muted);
            border: 1px solid var(--border);
            font-size: 13px;
            font-weight: 600;
            padding: 12px 20px;
        }
        .btn-web:hover { background: rgba(255,255,255,0.1); color: var(--text); }

        .btn svg { flex-shrink: 0; }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 0;
            color: var(--muted);
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Feature pills */
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 28px;
        }
        .pill {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.2);
            color: var(--brand);
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 99px;
        }

        /* Redirect notice */
        #redirect-notice {
            display: none;
            background: rgba(1,135,95,0.1);
            border: 1px solid rgba(1,135,95,0.25);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            color: #4ade80;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Footer */
        footer {
            margin-top: 24px;
            font-size: 11px;
            color: var(--muted);
            opacity: .6;
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>

<div class="card">

    <div class="logo-wrap">🛒</div>

    <h1>e<span>Sahlan</span></h1>
    <p class="tagline">
        Your all-in-one super app.<br>
        Food · Exchange · Learning · More
    </p>

    {{-- Redirect notice (shown when auto-redirecting) --}}
    <div id="redirect-notice">
        ✓ Opening your store…
    </div>

    {{-- Detecting state --}}
    <div id="detecting">
        <div class="spinner"></div>
        <span class="detect-text">Detecting your device…</span>
    </div>

    {{-- Progress bar --}}
    <div class="progress-wrap">
        <div class="progress-bar" id="progress"></div>
    </div>

    {{-- Manual download buttons (always visible as fallback) --}}
    <a id="btn-android" class="btn btn-android" href="{{ $androidUrl ?? 'https://play.google.com/store/apps/details?id=com.esahlan.app' }}" target="_blank">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3.18 23.76a2 2 0 0 0 2.07-.19L16.69 12 5.25.43A2 2 0 0 0 3 2.25v19.5a2 2 0 0 0 .18 2.01zM19.44 10.5l-2.55-1.47-2.84 2.97 2.84 2.97 2.57-1.49a2 2 0 0 0 0-3.48z"/>
        </svg>
        Get it on Google Play
    </a>

    <a id="btn-ios" class="btn btn-ios" href="{{ $iosUrl ?? 'https://apps.apple.com/app/id000000000' }}" target="_blank">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
        </svg>
        Download on the App Store
    </a>

    <div class="divider">or</div>

    <a class="btn btn-web" href="https://esahlan.com">
        🌐 Open in Browser
    </a>

    <div class="features">
        <span class="pill">🍔 eFood</span>
        <span class="pill">⇄ eExchange</span>
        <span class="pill">📚 eLearning</span>
        <span class="pill">🛒 eShop</span>
        <span class="pill">📦 eParcel</span>
    </div>

</div>

<footer>© {{ date('Y') }} eSahlan · All rights reserved</footer>

<script>
(function () {
    var ua = navigator.userAgent || navigator.vendor || window.opera;

    var isIOS     = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var isAndroid = /android/i.test(ua);

    var ANDROID_URL = '{{ $androidUrl ?? "https://play.google.com/store/apps/details?id=com.esahlan.app" }}';
    var IOS_URL     = '{{ $iosUrl     ?? "https://apps.apple.com/app/id000000000" }}';

    var notice   = document.getElementById('redirect-notice');
    var progress = document.getElementById('progress');
    var detecting = document.getElementById('detecting');

    function redirectTo(url, label) {
        // Update detecting text
        detecting.querySelector('.detect-text').textContent = 'Opening ' + label + '…';
        detecting.querySelector('.detect-text').style.color = '#f59e0b';

        // Show notice
        notice.style.display = 'block';
        notice.textContent   = '✓ Redirecting to ' + label + '…';

        // Animate progress
        setTimeout(function () { progress.style.width = '100%'; }, 50);

        // Redirect after 1.8s (let progress animate)
        setTimeout(function () { window.location.href = url; }, 1800);
    }

    if (isIOS) {
        redirectTo(IOS_URL, 'App Store');
    } else if (isAndroid) {
        redirectTo(ANDROID_URL, 'Google Play');
    } else {
        // Desktop — show both buttons clearly, stop spinner
        detecting.querySelector('.detect-text').textContent = 'Choose your platform below';
        detecting.querySelector('.detect-text').style.color = '#94a3b8';
        detecting.querySelector('.spinner').style.display = 'none';
        progress.style.width = '100%';
        progress.style.transition = 'width .4s ease';
        progress.style.background = 'rgba(255,255,255,0.15)';
    }
})();
</script>

</body>
</html>
