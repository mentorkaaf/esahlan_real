@extends('admin.layouts.app')
@section('title', 'Community Dashboard')

@push('styles')
<style>
  .cm-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }

  /* ── Stat cards ── */
  .cm-card {
    background:#fff; border-radius:18px; padding:24px;
    border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06), 0 1px 2px rgba(16,24,40,.04);
    transition:box-shadow .2s, transform .2s;
  }
  .cm-card:hover { box-shadow:0 4px 16px rgba(7,0,59,.09); transform:translateY(-1px); }

  .cm-metric-value {
    font-size:32px; font-weight:800; line-height:1.1; letter-spacing:-1px; color:#101828;
  }
  .cm-metric-label { font-size:13px; color:#667085; font-weight:500; margin-top:4px; }

  .cm-badge-up   { background:#ECFDF3; color:#027A48; font-size:12px; font-weight:700; padding:3px 10px; border-radius:100px; }
  .cm-badge-warn { background:#FFF4ED; color:#B54708; font-size:12px; font-weight:700; padding:3px 10px; border-radius:100px; }
  .cm-badge-gray { background:#F2F4F7; color:#667085; font-size:12px; font-weight:600; padding:3px 10px; border-radius:100px; }

  /* ── Section card ── */
  .cm-section {
    background:#fff; border-radius:18px;
    border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06);
    overflow:hidden;
  }
  .cm-section-hdr {
    padding:18px 24px; border-bottom:1px solid #F2F4F7;
    display:flex; align-items:center; justify-content:space-between;
  }
  .cm-section-title { font-size:14px; font-weight:800; color:#101828; }
  .cm-section-sub   { font-size:12px; color:#667085; margin-top:1px; }

  /* ── Table ── */
  .cm-table { width:100%; border-collapse:collapse; }
  .cm-table th {
    padding:10px 20px; font-size:11px; font-weight:700; color:#667085;
    text-transform:uppercase; letter-spacing:.6px; background:#F9FAFB;
    border-bottom:1px solid #F2F4F7; text-align:left;
  }
  .cm-table td { padding:14px 20px; border-bottom:1px solid #F9FAFB; vertical-align:middle; }
  .cm-table tr:last-child td { border-bottom:none; }
  .cm-table tr:hover td { background:#F9FAFB; }

  /* ── Type pill ── */
  .type-pill { font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }

  /* ── Avatar ── */
  .cm-avatar {
    width:36px; height:36px; border-radius:50%; object-fit:cover;
    background:linear-gradient(135deg,#07003B,#FF8A00);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:13px; font-weight:800; flex-shrink:0;
  }

  /* ── Progress bar ── */
  .cm-progress { background:#F2F4F7; border-radius:4px; height:6px; overflow:hidden; }
  .cm-progress-fill { height:100%; border-radius:4px; transition:width .4s; }

  /* ── Action btn ── */
  .cm-btn-icon {
    width:32px; height:32px; border-radius:8px; border:1.5px solid;
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; font-size:12px; transition:all .15s; background:transparent;
  }

  /* ── Scrollbar ── */
  .cm-scroll::-webkit-scrollbar { width:4px; }
  .cm-scroll::-webkit-scrollbar-thumb { background:#E5E7EB; border-radius:4px; }

  /* ── Link ── */
  .cm-link { color:#FF8A00; font-size:12px; font-weight:700; text-decoration:none; }
  .cm-link:hover { text-decoration:underline; }

  /* ── Divider ── */
  .cm-divider { border:none; border-top:1px solid #F2F4F7; margin:0; }
</style>
@endpush

@section('content')
<div class="cm-page">

  {{-- ══════════════ HEADER ══════════════ --}}
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px;gap:16px;flex-wrap:wrap">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
        <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-chart-line" style="color:#fff;font-size:15px"></i>
        </div>
        <h1 style="font-size:22px;font-weight:900;color:#101828;letter-spacing:-.5px;margin:0">Community Dashboard</h1>
      </div>
      <p style="font-size:13px;color:#667085;margin:0">
        Live metrics · {{ now()->format('l, d M Y') }} &nbsp;·&nbsp;
        <span style="color:#12B76A;font-weight:600">● Active</span>
      </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="{{ route('admin.community.posts') }}"
         style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#07003B;color:#fff;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none">
        <i class="fas fa-file-alt" style="font-size:11px"></i> Manage Posts
      </a>
      <a href="{{ route('admin.community.reports') }}"
         style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#fff;color:#344054;border:1.5px solid #D0D5DD;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none">
        <i class="fas fa-flag" style="font-size:11px;color:#F04438"></i> Reports
        @if($stats['reports_pending']>0)
          <span style="background:#F04438;color:#fff;border-radius:100px;padding:1px 7px;font-size:11px;font-weight:800">{{ $stats['reports_pending'] }}</span>
        @endif
      </a>
      <a href="{{ route('admin.community.moderation') }}"
         style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#fff;color:#344054;border:1.5px solid #D0D5DD;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none">
        <i class="fas fa-shield-alt" style="font-size:11px"></i> Moderation
      </a>
    </div>
  </div>

  {{-- ══════════════ KPI ROW ══════════════ --}}
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">

    {{-- Members --}}
    <div class="cm-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="width:42px;height:42px;border-radius:12px;background:#EEF4FF;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-users" style="color:#3538CD;font-size:17px"></i>
        </div>
        <span class="cm-badge-up"><i class="fas fa-arrow-up" style="font-size:9px"></i> +{{ $stats['members_today'] }} today</span>
      </div>
      <div class="cm-metric-value">{{ number_format($stats['members']) }}</div>
      <div class="cm-metric-label">Total Members</div>
      <hr class="cm-divider" style="margin:14px 0">
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#667085">
        <i class="fas fa-calendar-week" style="color:#D0D5DD"></i>
        +{{ $stats['members_week'] }} joined this week
      </div>
    </div>

    {{-- Posts --}}
    <div class="cm-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="width:42px;height:42px;border-radius:12px;background:#FFF4ED;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-file-alt" style="color:#FF8A00;font-size:17px"></i>
        </div>
        <span class="cm-badge-up"><i class="fas fa-arrow-up" style="font-size:9px"></i> +{{ $stats['posts_today'] }} today</span>
      </div>
      <div class="cm-metric-value" style="color:#FF8A00">{{ number_format($stats['posts']) }}</div>
      <div class="cm-metric-label">Total Posts</div>
      <hr class="cm-divider" style="margin:14px 0">
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#667085">
        <i class="fas fa-calendar-week" style="color:#D0D5DD"></i>
        +{{ $stats['posts_week'] }} posts this week
      </div>
    </div>

    {{-- Engagement --}}
    <div class="cm-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="width:42px;height:42px;border-radius:12px;background:#F4F3FF;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-heart" style="color:#7A5AF8;font-size:17px"></i>
        </div>
        <span class="cm-badge-gray">{{ number_format($stats['total_comments']) }} comments</span>
      </div>
      <div class="cm-metric-value" style="color:#7A5AF8">{{ number_format($stats['total_reactions']) }}</div>
      <div class="cm-metric-label">Total Reactions</div>
      <hr class="cm-divider" style="margin:14px 0">
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#667085">
        <i class="fas fa-eye" style="color:#D0D5DD"></i>
        {{ number_format($stats['total_views']) }} total views
      </div>
    </div>

    {{-- Reports --}}
    <div class="cm-card" style="{{ $stats['reports_pending']>0 ? 'border-color:#FEE4E2;background:#FFFBFA' : '' }}">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="width:42px;height:42px;border-radius:12px;background:{{ $stats['reports_pending']>0 ? '#FEE4E2' : '#F2F4F7' }};display:flex;align-items:center;justify-content:center">
          <i class="fas fa-flag" style="color:{{ $stats['reports_pending']>0 ? '#F04438' : '#98A2B3' }};font-size:17px"></i>
        </div>
        @if($stats['reports_pending']>0)
          <span class="cm-badge-warn">⚠ Needs review</span>
        @else
          <span class="cm-badge-up">✓ All clear</span>
        @endif
      </div>
      <div class="cm-metric-value" style="color:{{ $stats['reports_pending']>0 ? '#F04438' : '#98A2B3' }}">{{ $stats['reports_pending'] }}</div>
      <div class="cm-metric-label">Pending Reports</div>
      <hr class="cm-divider" style="margin:14px 0;border-color:{{ $stats['reports_pending']>0 ? '#FEE4E2' : '#F2F4F7' }}">
      <a href="{{ route('admin.community.reports') }}"
         style="font-size:12px;font-weight:700;color:{{ $stats['reports_pending']>0 ? '#F04438' : '#667085' }};text-decoration:none;display:flex;align-items:center;gap:4px">
        Review reports <i class="fas fa-chevron-right" style="font-size:9px"></i>
      </a>
    </div>
  </div>

  {{-- ══════════════ MIDDLE ROW ══════════════ --}}
  <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;margin-bottom:20px">

    {{-- ── Bar Chart ── --}}
    <div class="cm-section">
      <div class="cm-section-hdr">
        <div>
          <div class="cm-section-title">Post Activity</div>
          <div class="cm-section-sub">Daily posts — last 7 days</div>
        </div>
        <div style="display:flex;gap:20px">
          <div style="text-align:center">
            <div style="font-size:18px;font-weight:800;color:#101828">{{ $stats['stories_today'] }}</div>
            <div style="font-size:11px;color:#667085">Stories today</div>
          </div>
          <div style="text-align:center">
            <div style="font-size:18px;font-weight:800;color:#FF8A00">{{ $stats['messages_today'] }}</div>
            <div style="font-size:11px;color:#667085">Messages</div>
          </div>
          <div style="text-align:center">
            <div style="font-size:18px;font-weight:800;color:#7A5AF8">{{ $stats['active_chats'] }}</div>
            <div style="font-size:11px;color:#667085">Active chats</div>
          </div>
        </div>
      </div>

      <div style="padding:24px">
        @php
          $days   = collect(range(6,0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));
          $vals   = $days->map(fn($d) => $dailyPosts[$d] ?? 0);
          $maxVal = $vals->max() ?: 1;
          $svgH   = 140;
          $svgW   = 560;
          $barW   = 48;
          $gap    = ($svgW - count($days)*$barW) / (count($days)+1);
        @endphp
        <svg viewBox="0 0 {{ $svgW }} {{ $svgH + 30 }}" style="width:100%;overflow:visible">
          {{-- Grid lines --}}
          @foreach([0,0.25,0.5,0.75,1] as $frac)
            @php $y = $svgH - $svgH * $frac; @endphp
            <line x1="0" y1="{{ $y }}" x2="{{ $svgW }}" y2="{{ $y }}"
                  stroke="#F2F4F7" stroke-width="1"/>
            @if($frac > 0)
              <text x="-4" y="{{ $y + 4 }}" text-anchor="end" font-size="10" fill="#98A2B3">
                {{ round($maxVal * $frac) }}
              </text>
            @endif
          @endforeach

          {{-- Bars --}}
          @foreach($days as $i => $day)
            @php
              $cnt = $vals[$i];
              $barH = max(4, round(($cnt / $maxVal) * $svgH));
              $x = $gap + $i * ($barW + $gap);
              $y = $svgH - $barH;
              $dayLabel = \Carbon\Carbon::parse($day)->format('D');
              $isToday = $day === now()->format('Y-m-d');
            @endphp
            <defs>
              <linearGradient id="bg{{ $i }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="{{ $isToday ? '#FF8A00' : '#07003B' }}" stop-opacity="1"/>
                <stop offset="100%" stop-color="{{ $isToday ? '#FFBB5C' : '#3538CD' }}" stop-opacity=".7"/>
              </linearGradient>
            </defs>
            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $barH }}"
                  rx="6" fill="url(#bg{{ $i }})"/>
            @if($cnt > 0)
              <text x="{{ $x + $barW/2 }}" y="{{ $y - 5 }}"
                    text-anchor="middle" font-size="11" font-weight="700"
                    fill="{{ $isToday ? '#FF8A00' : '#344054' }}">{{ $cnt }}</text>
            @endif
            <text x="{{ $x + $barW/2 }}" y="{{ $svgH + 18 }}"
                  text-anchor="middle" font-size="11" fill="{{ $isToday ? '#FF8A00' : '#98A2B3' }}"
                  font-weight="{{ $isToday ? '700' : '500' }}">{{ $dayLabel }}</text>
          @endforeach
        </svg>
      </div>
    </div>

    {{-- ── Content Breakdown + Top Creators ── --}}
    <div style="display:flex;flex-direction:column;gap:16px">

      {{-- Content Breakdown --}}
      <div class="cm-section">
        <div class="cm-section-hdr">
          <div class="cm-section-title">Content Breakdown</div>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          @php
            $typeConf = [
              'video' => ['#FF8A00','#FFF4ED','fa-video'],
              'image' => ['#7A5AF8','#F4F3FF','fa-image'],
              'text'  => ['#07003B','#EEF4FF','fa-align-left'],
              'reel'  => ['#12B76A','#ECFDF3','fa-film'],
              'audio' => ['#F04438','#FEF3F2','fa-microphone'],
            ];
            $total = $postTypes->sum() ?: 1;
          @endphp
          @forelse($postTypes as $type => $cnt)
            @php [$col,$bg,$ico] = $typeConf[$type] ?? ['#667085','#F2F4F7','fa-file']; $pct = round(($cnt/$total)*100); @endphp
            <div>
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:28px;height:28px;border-radius:8px;background:{{ $bg }};display:flex;align-items:center;justify-content:center">
                    <i class="fas {{ $ico }}" style="font-size:11px;color:{{ $col }}"></i>
                  </div>
                  <span style="font-size:13px;font-weight:600;color:#344054">{{ ucfirst($type) }}</span>
                </div>
                <span style="font-size:13px;font-weight:800;color:#101828">{{ $cnt }} <span style="color:#98A2B3;font-weight:500">{{ $pct }}%</span></span>
              </div>
              <div class="cm-progress">
                <div class="cm-progress-fill" style="width:{{ $pct }}%;background:{{ $col }}"></div>
              </div>
            </div>
          @empty
            <p style="text-align:center;color:#98A2B3;font-size:13px;padding:12px 0">No posts yet</p>
          @endforelse
        </div>
      </div>

      {{-- Top Creators --}}
      <div class="cm-section" style="flex:1">
        <div class="cm-section-hdr">
          <div class="cm-section-title">Top Creators</div>
          <a href="{{ route('admin.community.users') }}" class="cm-link">View all →</a>
        </div>
        <div style="padding:8px 0">
          @forelse($topUsers as $i => $profile)
          <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;{{ !$loop->last ? 'border-bottom:1px solid #F9FAFB' : '' }}">
            <span style="font-size:11px;font-weight:800;color:#D0D5DD;width:16px">{{ $i+1 }}</span>
            @if($profile->user?->avatar)
              <img src="{{ asset('storage/'.$profile->user->avatar) }}"
                   style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #F2F4F7" alt="">
            @else
              <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:800;flex-shrink:0">
                {{ strtoupper(substr($profile->user->name ?? 'U',0,1)) }}
              </div>
            @endif
            <div style="flex:1;min-width:0">
              <p style="font-size:13px;font-weight:700;color:#101828;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ $profile->user->name ?? '—' }}
                @if($profile->is_verified)
                  <i class="fas fa-certificate" style="color:#FF8A00;font-size:10px;margin-left:3px"></i>
                @endif
              </p>
              <p style="font-size:11px;color:#667085;margin:2px 0 0">{{ number_format($profile->followers_count) }} followers</p>
            </div>
          </div>
          @empty
            <p style="text-align:center;color:#98A2B3;font-size:13px;padding:20px">No users yet</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════════ BOTTOM ROW ══════════════ --}}
  <div style="display:grid;grid-template-columns:1fr 380px;gap:16px">

    {{-- Recent Posts --}}
    <div class="cm-section">
      <div class="cm-section-hdr">
        <div>
          <div class="cm-section-title">Recent Posts</div>
          <div class="cm-section-sub">Latest community content</div>
        </div>
        <a href="{{ route('admin.community.posts') }}" class="cm-link">View all →</a>
      </div>
      <table class="cm-table">
        <thead>
          <tr>
            <th>Author</th>
            <th>Content</th>
            <th>Type</th>
            <th style="text-align:center">Engagement</th>
            <th>When</th>
            <th style="text-align:center">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentPosts as $post)
          <tr id="post-{{ $post->id }}">
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                @if($post->user?->avatar)
                  <img src="{{ asset('storage/'.$post->user->avatar) }}"
                       style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #F2F4F7" alt="">
                @else
                  <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:800;flex-shrink:0">
                    {{ strtoupper(substr($post->user->name ?? 'U',0,1)) }}
                  </div>
                @endif
                <span style="font-size:13px;font-weight:600;color:#101828;white-space:nowrap">{{ $post->user->name ?? '—' }}</span>
              </div>
            </td>
            <td style="max-width:200px">
              <p style="font-size:13px;color:#344054;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ $post->content ? Str::limit($post->content, 55) : '(media only)' }}
              </p>
            </td>
            <td>
              @php
                $typeStyle = [
                  'video' => ['#FF8A00','#FFF4ED'],
                  'image' => ['#7A5AF8','#F4F3FF'],
                  'text'  => ['#344054','#F2F4F7'],
                  'reel'  => ['#12B76A','#ECFDF3'],
                  'audio' => ['#F04438','#FEF3F2'],
                ][$post->type] ?? ['#667085','#F9FAFB'];
              @endphp
              <span class="type-pill" style="color:{{ $typeStyle[0] }};background:{{ $typeStyle[1] }}">{{ ucfirst($post->type) }}</span>
            </td>
            <td>
              <div style="display:flex;align-items:center;justify-content:center;gap:12px">
                <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#667085;font-weight:600">
                  <i class="fas fa-heart" style="color:#F04438;font-size:10px"></i> {{ $post->likes_count }}
                </span>
                <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#667085;font-weight:600">
                  <i class="fas fa-comment" style="color:#7A5AF8;font-size:10px"></i> {{ $post->comments_count }}
                </span>
                <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#667085;font-weight:600">
                  <i class="fas fa-eye" style="color:#D0D5DD;font-size:10px"></i> {{ number_format($post->views_count ?? 0) }}
                </span>
              </div>
            </td>
            <td style="font-size:12px;color:#98A2B3;white-space:nowrap">{{ $post->created_at->diffForHumans() }}</td>
            <td style="text-align:center">
              <button onclick="deletePost({{ $post->id }})"
                      class="cm-btn-icon" style="border-color:#FECDCA;color:#F04438"
                      onmouseover="this.style.background='#F04438';this.style.color='#fff'"
                      onmouseout="this.style.background='transparent';this.style.color='#F04438'">
                <i class="fas fa-trash" style="font-size:11px"></i>
              </button>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" style="text-align:center;padding:40px;color:#98A2B3">
            <i class="fas fa-file-alt" style="font-size:28px;display:block;margin-bottom:8px;color:#E5E7EB"></i>
            No posts yet
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Right: Reports + Quick Actions --}}
    <div style="display:flex;flex-direction:column;gap:16px">

      {{-- Pending Reports --}}
      <div class="cm-section" style="{{ $stats['reports_pending']>0 ? 'border-color:#FECDCA' : '' }}">
        <div class="cm-section-hdr" style="{{ $stats['reports_pending']>0 ? 'background:#FEF3F2;border-color:#FECDCA' : '' }}">
          <div>
            <div class="cm-section-title" style="{{ $stats['reports_pending']>0 ? 'color:#B42318' : '' }}">
              <i class="fas fa-flag" style="margin-right:6px;font-size:13px"></i>
              Pending Reports
              @if($stats['reports_pending']>0)
                <span style="background:#F04438;color:#fff;border-radius:100px;padding:1px 8px;font-size:11px;font-weight:800;margin-left:6px">{{ $stats['reports_pending'] }}</span>
              @endif
            </div>
          </div>
          <a href="{{ route('admin.community.reports') }}" class="cm-link" style="{{ $stats['reports_pending']>0 ? 'color:#B42318' : '' }}">All →</a>
        </div>
        @forelse($pendingReports as $report)
        <div style="padding:13px 20px;{{ !$loop->last ? 'border-bottom:1px solid #FEF3F2' : '' }};display:flex;align-items:center;gap:12px">
          <div style="width:34px;height:34px;border-radius:50%;background:#FEF3F2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas fa-flag" style="color:#F04438;font-size:12px"></i>
          </div>
          <div style="flex:1;min-width:0">
            <p style="font-size:13px;font-weight:700;color:#101828;margin:0">{{ $report->reporter->name ?? 'Unknown' }}</p>
            <p style="font-size:11px;color:#667085;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              {{ Str::limit($report->reason ?? $report->type ?? 'Report', 36) }}
            </p>
          </div>
          <span style="font-size:11px;color:#98A2B3;white-space:nowrap">{{ $report->created_at->diffForHumans(null,true) }}</span>
        </div>
        @empty
        <div style="display:flex;flex-direction:column;align-items:center;padding:32px 20px">
          <div style="width:48px;height:48px;border-radius:50%;background:#ECFDF3;display:flex;align-items:center;justify-content:center;margin-bottom:10px">
            <i class="fas fa-check" style="color:#12B76A;font-size:18px"></i>
          </div>
          <p style="font-size:14px;font-weight:700;color:#12B76A;margin:0">All clear!</p>
          <p style="font-size:12px;color:#98A2B3;margin:4px 0 0">No pending reports to review</p>
        </div>
        @endforelse
      </div>

      {{-- Quick Actions --}}
      <div class="cm-section">
        <div class="cm-section-hdr">
          <div class="cm-section-title">Quick Actions</div>
        </div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
          @foreach([
            ['route'=>'admin.community.posts',      'icon'=>'fa-file-alt',    'label'=>'Posts',       'col'=>'#3538CD','bg'=>'#EEF4FF'],
            ['route'=>'admin.community.users',      'icon'=>'fa-user-shield', 'label'=>'Users',       'col'=>'#7A5AF8','bg'=>'#F4F3FF'],
            ['route'=>'admin.community.moderation', 'icon'=>'fa-shield-alt',  'label'=>'Moderation',  'col'=>'#FF8A00','bg'=>'#FFF4ED'],
            ['route'=>'admin.community.algorithm',  'icon'=>'fa-brain',       'label'=>'Algorithm',   'col'=>'#12B76A','bg'=>'#ECFDF3'],
            ['route'=>'admin.community.groups',     'icon'=>'fa-layer-group', 'label'=>'Groups',      'col'=>'#2E90FA','bg'=>'#EFF8FF'],
            ['route'=>'admin.community-ads.index',  'icon'=>'fa-bullhorn',    'label'=>'Ads & Pages', 'col'=>'#F79009','bg'=>'#FFFAEB'],
          ] as $a)
          <a href="{{ route($a['route']) }}"
             style="display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:12px;border:1.5px solid #F2F4F7;text-decoration:none;transition:all .15s"
             onmouseover="this.style.borderColor='{{ $a['col'] }}30';this.style.background='{{ $a['bg'] }}'"
             onmouseout="this.style.borderColor='#F2F4F7';this.style.background='transparent'">
            <div style="width:32px;height:32px;border-radius:9px;background:{{ $a['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="fas {{ $a['icon'] }}" style="font-size:12px;color:{{ $a['col'] }}"></i>
            </div>
            <span style="font-size:12px;font-weight:700;color:#344054">{{ $a['label'] }}</span>
          </a>
          @endforeach
        </div>
      </div>

    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
function deletePost(id) {
  if (!confirm('Permanently delete this post?')) return;
  fetch(`/admin/community/posts/${id}`, {
    method:'DELETE',
    headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
  }).then(r => {
    if (r.ok) {
      const el = document.getElementById('post-'+id);
      if (el) { el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(),300); }
    }
  });
}
</script>
@endpush
