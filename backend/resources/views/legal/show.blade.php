<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $title }} — eSahlan</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            color: #1a1a2e;
            line-height: 1.7;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }
        .header {
            background: #07003B;
            color: #fff;
            padding: 20px 20px 16px;
            position: sticky; top: 0; z-index: 10;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }
        .header-inner {
            max-width: 720px; margin: 0 auto;
            display: flex; align-items: center; gap: 12px;
        }
        .logo { width: 36px; height: 36px; border-radius: 9px; }
        .header h1 { font-size: 17px; font-weight: 800; }
        .lang-toggle {
            margin-left: auto;
            display: flex; gap: 6px;
        }
        .lang-btn {
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
            border: 1.5px solid rgba(255,255,255,0.3);
            color: rgba(255,255,255,0.7); background: transparent;
            text-decoration: none; cursor: pointer;
        }
        .lang-btn.active {
            background: #F5A623; border-color: #F5A623; color: #07003B;
        }
        .content {
            max-width: 720px; margin: 0 auto;
            padding: 24px 20px 48px;
        }
        h2 { font-size: 22px; font-weight: 800; margin-bottom: 16px; color: #07003B; }
        h3 { font-size: 15px; font-weight: 700; margin: 20px 0 8px; color: #07003B; }
        p  { margin-bottom: 12px; font-size: 14px; color: #444; }
        ul { padding-left: 20px; margin-bottom: 12px; }
        li { font-size: 14px; color: #444; margin-bottom: 6px; }
        strong { color: #1a1a2e; }
        .updated {
            font-size: 12px; color: #888; margin-bottom: 20px;
            padding-bottom: 16px; border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-inner">
            <img src="{{ asset('storage/app-icon.png') }}" class="logo"
                 onerror="this.style.display='none'" alt="eSahlan">
            <h1>{{ $title }}</h1>
            <div class="lang-toggle">
                <a href="?lang=en" class="lang-btn {{ $lang === 'en' ? 'active' : '' }}">EN</a>
                <a href="?lang=so" class="lang-btn {{ $lang === 'so' ? 'active' : '' }}">SO</a>
            </div>
        </div>
    </div>
    <div class="content">
        {!! $content !!}
    </div>
</body>
</html>
