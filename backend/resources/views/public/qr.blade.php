<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $qr->headline ?? $qr->title }} — eSahlan</title>
    <meta property="og:title" content="{{ $qr->headline ?? $qr->title }}">
    <meta property="og:description" content="{{ $qr->description ?? 'eSahlan Service' }}">
    @if($qr->logo_url)<meta property="og:image" content="{{ $qr->logo_url }}">@endif
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:24px 16px 40px;}

        .card{background:#fff;border-radius:24px;box-shadow:0 8px 40px rgba(0,0,0,.10);width:100%;max-width:420px;overflow:hidden;}

        .card-top{height:6px;background:var(--accent);}

        .logo-wrap{padding:28px 28px 0;text-align:center;}
        .logo-img{width:96px;height:96px;border-radius:20px;object-fit:cover;border:3px solid rgba(0,0,0,.06);box-shadow:0 4px 16px rgba(0,0,0,.1);}
        .logo-initials{width:96px;height:96px;border-radius:20px;background:var(--accent);color:#fff;font-size:36px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto;}

        .card-body{padding:20px 28px 28px;}
        .type-badge{display:inline-flex;align-items:center;gap:5px;background:var(--accent-light);color:var(--accent);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px;}
        .headline{font-size:24px;font-weight:800;color:#0f172a;line-height:1.2;margin-bottom:8px;}
        .desc{font-size:14px;color:#64748b;line-height:1.6;margin-bottom:20px;}

        .fields{border-top:1px solid #f1f5f9;padding-top:16px;margin-bottom:20px;}
        .field-row{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f8fafc;}
        .field-row:last-child{border-bottom:none;}
        .field-key{font-size:13px;color:#94a3b8;font-weight:500;}
        .field-val{font-size:14px;color:#0f172a;font-weight:700;text-align:right;max-width:60%;word-break:break-word;}

        .cta-btn{display:block;width:100%;padding:15px;background:var(--accent);color:#fff;border-radius:14px;text-align:center;font-size:16px;font-weight:800;text-decoration:none;letter-spacing:.01em;transition:opacity .15s;margin-bottom:12px;}
        .cta-btn:hover{opacity:.9;}

        .esahlan-footer{margin-top:28px;text-align:center;}
        .esahlan-logo{display:inline-flex;align-items:center;gap:8px;font-size:16px;font-weight:800;color:#0f172a;text-decoration:none;}
        .esahlan-logo span{color:var(--accent);}
        .footer-sub{font-size:12px;color:#94a3b8;margin-top:4px;}
    </style>
    <style>:root{--accent:{{ $qr->color }};--accent-light:{{ $qr->color }}18;}</style>
</head>
<body>
    <div class="card">
        <div class="card-top"></div>

        @if($qr->logo_url)
        <div class="logo-wrap">
            <img src="{{ $qr->logo_url }}" alt="{{ $qr->headline }}" class="logo-img"
                 onerror="this.style.display='none'">
        </div>
        @endif

        <div class="card-body">
            @php $typeInfo = \App\Models\QrCode::types()[$qr->type] ?? ['icon'=>'⚙️','label'=>'Service']; @endphp
            <div class="type-badge">{{ $typeInfo['icon'] }} {{ $typeInfo['label'] }}</div>

            @if($qr->headline)
            <div class="headline">{{ $qr->headline }}</div>
            @endif

            @if($qr->description)
            <div class="desc">{{ $qr->description }}</div>
            @endif

            @if($qr->fields && count($qr->fields))
            <div class="fields">
                @foreach($qr->fields as $field)
                <div class="field-row">
                    <span class="field-key">{{ $field['label'] }}</span>
                    <span class="field-val">{{ $field['value'] }}</span>
                </div>
                @endforeach
            </div>
            @endif

            @if($qr->cta_label && $qr->cta_url)
            <a href="{{ $qr->cta_url }}" class="cta-btn" target="_blank">
                {{ $qr->cta_label }} →
            </a>
            @endif
        </div>
    </div>

    <div class="esahlan-footer">
        <a href="{{ url('/') }}" class="esahlan-logo">
            <span>e</span>Sahlan
        </a>
        <div class="footer-sub">Powered by eSahlan Platform</div>
    </div>
</body>
</html>
