@extends('admin.layouts.app')
@section('title', 'App Download Link — Analytics')
@section('content')

@php
$deviceTotal = max($androidCnt + $iosCnt + $desktopCnt, 1);
$androidPct  = round($androidCnt / $deviceTotal * 100);
$iosPct      = round($iosCnt     / $deviceTotal * 100);
$desktopPct  = round($desktopCnt / $deviceTotal * 100);
$chartMax    = max(collect($chart)->max('total'), 1);
@endphp

{{-- Page header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
    <div>
        <h1 class="page-title">📲 App Download Link</h1>
        <p class="page-subtitle">
            Track who taps <strong>esahlan.com/app</strong> — device type, timing, country, and more.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        {{-- Range selector --}}
        <form method="GET" style="display:flex;gap:8px;align-items:center">
            <select name="range" onchange="this.form.submit()"
                style="padding:8px 12px;border-radius:9px;border:1px solid #e5e7eb;font-size:13px;font-weight:600;color:#111;background:#fff">
                @foreach([7=>'Last 7 days',14=>'Last 14 days',30=>'Last 30 days',90=>'Last 90 days'] as $v=>$l)
                <option value="{{ $v }}" {{ $range==$v?'selected':'' }}>{{ $l }}</option>
                @endforeach
            </select>
        </form>
        {{-- Live link --}}
        <a href="{{ route('app.download') }}" target="_blank"
           style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--brand,#f59e0b);color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none">
            <i class="fas fa-external-link-alt"></i> esahlan.com/app
        </a>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#065f46;font-weight:600">
    ✅ {{ session('success') }}
</div>
@endif

{{-- ── KPI Cards ──────────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
    @foreach([
        ['Total Clicks','all time',$total,'fas fa-hand-pointer','#6366f1','rgba(99,102,241,.1)'],
        ['Today',date('d M Y'),$today,'fas fa-sun','#f59e0b','rgba(245,158,11,.1)'],
        ['This Week','Mon – now',$thisWeek,'fas fa-calendar-week','#10b981','rgba(16,185,129,.1)'],
        ['This Month',date('F'),$thisMonth,'fas fa-calendar','#3b82f6','rgba(59,130,246,.1)'],
    ] as $card)
    <div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <span style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">{{ $card[0] }}</span>
            <div style="width:34px;height:34px;border-radius:9px;background:{{ $card[5] }};display:flex;align-items:center;justify-content:center">
                <i class="{{ $card[3] }}" style="color:{{ $card[4] }};font-size:14px"></i>
            </div>
        </div>
        <div style="font-size:32px;font-weight:900;color:#111;line-height:1">{{ number_format($card[2]) }}</div>
        <div style="font-size:11px;color:#9ca3af;margin-top:4px">{{ $card[1] }}</div>
    </div>
    @endforeach
</div>

{{-- ── Device Breakdown + Chart ──────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:24px">

    {{-- Device Donut --}}
    <div style="background:#fff;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb">
        <h3 style="font-size:14px;font-weight:800;color:#111;margin-bottom:18px">Device Split</h3>
        {{-- SVG donut --}}
        <div style="display:flex;justify-content:center;margin-bottom:20px">
        @php
            $a = $androidPct; $i = $iosPct; $d = $desktopPct;
            // Build conic gradient
            $aEnd = $a * 3.6; $iEnd = $aEnd + $i * 3.6;
        @endphp
        <div style="
            width:130px;height:130px;border-radius:50%;
            background:conic-gradient(
                #01875f 0deg {{ $aEnd }}deg,
                #1a1a2e {{ $aEnd }}deg {{ $iEnd }}deg,
                #6366f1 {{ $iEnd }}deg 360deg
            );
            display:flex;align-items:center;justify-content:center;
            box-shadow:0 4px 16px rgba(0,0,0,.1)
        ">
            <div style="width:80px;height:80px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;flex-direction:column">
                <div style="font-size:18px;font-weight:900;color:#111">{{ $androidCnt + $iosCnt + $desktopCnt }}</div>
                <div style="font-size:9px;color:#9ca3af;font-weight:600">CLICKS</div>
            </div>
        </div>
        </div>
        {{-- Legend --}}
        @foreach([
            ['Android', $androidCnt, $androidPct, '#01875f'],
            ['iOS',     $iosCnt,     $iosPct,     '#1a1a2e'],
            ['Desktop', $desktopCnt, $desktopPct, '#6366f1'],
        ] as $row)
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:10px;height:10px;border-radius:3px;background:{{ $row[3] }};flex-shrink:0"></div>
                <span style="font-size:13px;font-weight:600;color:#374151">{{ $row[0] }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:80px;height:6px;background:#f3f4f6;border-radius:99px;overflow:hidden">
                    <div style="width:{{ $row[2] }}%;height:100%;background:{{ $row[3] }};border-radius:99px"></div>
                </div>
                <span style="font-size:12px;font-weight:800;color:#111;min-width:28px;text-align:right">{{ $row[1] }}</span>
                <span style="font-size:10px;color:#9ca3af;min-width:30px">({{ $row[2] }}%)</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Daily Bar Chart --}}
    <div style="background:#fff;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb">
        <h3 style="font-size:14px;font-weight:800;color:#111;margin-bottom:18px">Clicks — Last {{ $range }} Days</h3>
        <div style="display:flex;align-items:flex-end;gap:6px;height:140px">
            @foreach($chart as $day)
            @php $pct = $chartMax > 0 ? round($day['total'] / $chartMax * 100) : 0; @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                <div style="font-size:9px;font-weight:700;color:{{ $day['total']>0?'#374151':'#d1d5db' }}">
                    {{ $day['total'] ?: '' }}
                </div>
                <div style="width:100%;flex:1;display:flex;flex-direction:column;justify-content:flex-end">
                    <div style="width:100%;min-height:2px;height:{{ max($pct,1) }}%;
                        background:{{ $day['total']>0 ? 'linear-gradient(180deg,#6366f1,#4f46e5)' : '#f3f4f6' }};
                        border-radius:4px 4px 0 0;transition:.3s">
                    </div>
                </div>
                <div style="font-size:9px;color:#9ca3af;font-weight:600;white-space:nowrap">{{ $day['date'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Hourly Today + Countries ────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px">

    {{-- Hourly Chart --}}
    <div style="background:#fff;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb">
        <h3 style="font-size:14px;font-weight:800;color:#111;margin-bottom:18px">Today by Hour</h3>
        @php $hourMax = max($hourly->max('cnt'), 1); @endphp
        <div style="display:flex;align-items:flex-end;gap:3px;height:80px">
            @foreach($hourly as $h)
            @php $hp = round($h['cnt'] / $hourMax * 100); @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center">
                <div style="width:100%;min-height:2px;height:{{ max($hp,2) }}%;
                    background:{{ $h['cnt']>0?'linear-gradient(180deg,#f59e0b,#d97706)':'#f3f4f6' }};
                    border-radius:2px 2px 0 0" title="{{ $h['hour'] }}:00 — {{ $h['cnt'] }} clicks">
                </div>
                @if($h['hour'] % 6 === 0)
                <div style="font-size:8px;color:#9ca3af;margin-top:3px">{{ $h['hour'] }}h</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Top Countries --}}
    <div style="background:#fff;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb">
        <h3 style="font-size:14px;font-weight:800;color:#111;margin-bottom:16px">Top Countries</h3>
        @if($countries->isEmpty())
        <div style="text-align:center;padding:20px 0;color:#9ca3af;font-size:13px">
            <i class="fas fa-globe" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3"></i>
            No location data yet.<br>
            <span style="font-size:11px">Detected via Cloudflare CF-IPCountry header</span>
        </div>
        @else
        @php $cMax = $countries->max('cnt'); @endphp
        @foreach($countries as $c)
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <span style="font-size:14px">🌍</span>
            <div style="flex:1">
                <div style="display:flex;justify-content:space-between;margin-bottom:3px">
                    <span style="font-size:12px;font-weight:700;color:#374151">{{ $c->country_name }}</span>
                    <span style="font-size:12px;font-weight:800;color:#111">{{ $c->cnt }}</span>
                </div>
                <div style="height:4px;background:#f3f4f6;border-radius:99px;overflow:hidden">
                    <div style="width:{{ round($c->cnt/$cMax*100) }}%;height:100%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:99px"></div>
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>
</div>

{{-- ── Store URL Settings ─────────────────────────────────────────────────── --}}
<div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
        <div style="width:36px;height:36px;background:#f0fdf4;border-radius:9px;display:flex;align-items:center;justify-content:center">
            <i class="fas fa-link" style="color:#10b981;font-size:15px"></i>
        </div>
        <div>
            <h3 style="font-size:15px;font-weight:800;color:#111;margin:0">Store Links</h3>
            <p style="font-size:11px;color:#9ca3af;margin:0">Update when your apps go live on the stores</p>
        </div>
    </div>
    <form action="{{ route('admin.app-link.urls') }}" method="POST">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px">
            <div>
                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:7px">
                    <i class="fab fa-google-play" style="color:#01875f;margin-right:5px"></i> Google Play URL
                </label>
                <input name="android_url" value="{{ $androidUrl }}"
                    style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:13px;color:#111;box-sizing:border-box"
                    placeholder="https://play.google.com/store/apps/details?id=com.esahlan.app">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:7px">
                    <i class="fab fa-apple" style="color:#111;margin-right:5px"></i> App Store URL
                </label>
                <input name="ios_url" value="{{ $iosUrl }}"
                    style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:13px;color:#111;box-sizing:border-box"
                    placeholder="https://apps.apple.com/app/id1234567890">
            </div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:#6b7280">
                <i class="fas fa-info-circle"></i>
                The download page at <strong>esahlan.com/app</strong> uses these links instantly — no redeploy needed.
            </div>
            <button type="submit" style="padding:10px 22px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer">
                <i class="fas fa-save"></i> Save Links
            </button>
        </div>
    </form>
</div>

{{-- ── Recent Clicks Table ─────────────────────────────────────────────────── --}}
<div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:16px 22px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
        <h3 style="font-size:15px;font-weight:800;color:#111;margin:0">Recent Clicks</h3>
        <form action="{{ route('admin.app-link.clear') }}" method="POST" onsubmit="return confirm('Delete click records older than 90 days?')">
            @csrf
            <input type="hidden" name="older_than" value="90">
            <button type="submit" style="padding:6px 14px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer">
                <i class="fas fa-trash"></i> Clear Old Data
            </button>
        </form>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb">
                <th style="padding:9px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Device</th>
                <th style="padding:9px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">OS / Browser</th>
                <th style="padding:9px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Country</th>
                <th style="padding:9px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">IP</th>
                <th style="padding:9px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Referrer</th>
                <th style="padding:9px 16px;text-align:right;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Time</th>
            </tr>
        </thead>
        <tbody>
            @php
                $deviceStyles = [
                    'android' => ['🤖','#01875f','rgba(1,135,95,.1)'],
                    'ios'     => ['🍎','#1a1a2e','rgba(26,26,46,.08)'],
                    'desktop' => ['🖥️','#6366f1','rgba(99,102,241,.1)'],
                ];
            @endphp
            @forelse($recent as $click)
            @php $ds = $deviceStyles[$click->device_type] ?? ['❓','#6b7280','#f3f4f6']; @endphp
            <tr style="border-top:1px solid #f3f4f6;transition:background .1s" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                <td style="padding:10px 16px">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:{{ $ds[2] }};border-radius:6px;font-size:11px;font-weight:800;color:{{ $ds[1] }}">
                        {{ $ds[0] }} {{ ucfirst($click->device_type) }}
                    </span>
                </td>
                <td style="padding:10px 16px;font-size:12px;color:#374151">
                    {{ $click->os ?? '—' }} / {{ $click->browser ?? '—' }}
                </td>
                <td style="padding:10px 16px;font-size:12px;color:#374151">
                    @if($click->country_name)
                        🌍 {{ $click->country_name }}
                    @else
                        <span style="color:#9ca3af">Unknown</span>
                    @endif
                </td>
                <td style="padding:10px 16px;font-size:11px;color:#9ca3af;font-family:monospace">
                    {{ $click->ip ? substr($click->ip, 0, -3) . '***' : '—' }}
                </td>
                <td style="padding:10px 16px;font-size:11px;color:#9ca3af;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    {{ $click->referer ? parse_url($click->referer, PHP_URL_HOST) : 'Direct' }}
                </td>
                <td style="padding:10px 16px;text-align:right;font-size:12px;color:#6b7280;white-space:nowrap">
                    {{ \Carbon\Carbon::parse($click->created_at)->diffForHumans() }}
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:40px;text-align:center;color:#9ca3af;font-size:13px">
                <i class="fas fa-hand-pointer" style="font-size:36px;display:block;margin-bottom:12px;opacity:.3"></i>
                No clicks yet. Share <strong>esahlan.com/app</strong> to start tracking.
            </td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

@endsection
