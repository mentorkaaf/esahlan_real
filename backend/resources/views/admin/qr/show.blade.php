<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code — {{ $label }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,.10);
            padding: 40px 36px 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0f4ff;
            color: #4f46e5;
            border-radius: 99px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .badge.vendor { background: #fff7ed; color: #ea580c; }
        .badge.driver { background: #f0fdf4; color: #16a34a; }
        .badge.order  { background: #faf5ff; color: #9333ea; }

        h2 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 24px;
            line-height: 1.3;
        }

        .qr-wrap {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            display: inline-block;
            margin-bottom: 24px;
        }

        .qr-wrap svg {
            display: block;
            width: 220px;
            height: 220px;
        }

        .meta {
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            text-align: left;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            font-size: 13px;
        }

        .meta-row:not(:last-child) {
            border-bottom: 1px solid #e2e8f0;
        }

        .meta-key   { color: #64748b; font-weight: 500; }
        .meta-value { color: #0f172a; font-weight: 600; }

        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all .15s;
        }

        .btn-primary {
            background: #4f46e5;
            color: #fff;
        }
        .btn-primary:hover { background: #4338ca; }

        .btn-outline {
            background: #fff;
            color: #374151;
            border: 1.5px solid #d1d5db;
        }
        .btn-outline:hover { background: #f9fafb; }

        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .card { box-shadow: none; border: 1px solid #e2e8f0; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge {{ $type }}">
            @if($type === 'vendor') 🏪 Vendor
            @elseif($type === 'driver') 🚗 Driver
            @else 📦 Order
            @endif
        </div>

        <h2>{{ $label }}</h2>

        <div class="qr-wrap">
            {!! $svg !!}
        </div>

        @if(!empty($meta))
        <div class="meta">
            @foreach($meta as $key => $value)
            <div class="meta-row">
                <span class="meta-key">{{ $key }}</span>
                <span class="meta-value">{{ $value }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <div class="actions">
            <a href="{{ route('admin.qr.download', [$type, request()->route('id')]) }}"
               class="btn btn-primary">
                ⬇ Download PNG
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                🖨 Print
            </button>
        </div>
    </div>
</body>
</html>
