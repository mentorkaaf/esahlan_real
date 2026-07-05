@extends('admin.layouts.app')
@section('title', 'Feed Algorithm Dashboard')

@push('styles')
<style>
  /* Gradient cards */
  .grad-blue   { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
  .grad-green  { background: linear-gradient(135deg,#11998e 0%,#38ef7d 100%); }
  .grad-orange { background: linear-gradient(135deg,#f7971e 0%,#ffd200 100%); }
  .grad-red    { background: linear-gradient(135deg,#f953c6 0%,#b91d73 100%); }
  .grad-teal   { background: linear-gradient(135deg,#00b4db 0%,#0083b0 100%); }
  .grad-purple { background: linear-gradient(135deg,#8360c3 0%,#2ebf91 100%); }

  /* Glass card */
  .glass {
    background: rgba(255,255,255,0.95);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
  }

  /* Stat card */
  .stat-kard {
    border-radius: 18px;
    padding: 20px 22px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    transition: transform .2s, box-shadow .2s;
  }
  .stat-kard:hover { transform: translateY(-3px); box-shadow: 0 16px 36px rgba(0,0,0,.2); }
  .stat-kard::after {
    content:'';position:absolute;top:-20px;right:-20px;
    width:100px;height:100px;border-radius:50%;
    background:rgba(255,255,255,.12);
  }
  .stat-kard::before {
    content:'';position:absolute;bottom:-30px;right:20px;
    width:70px;height:70px;border-radius:50%;
    background:rgba(255,255,255,.08);
  }

  /* Animated live dot */
  .live-dot {
    width:9px;height:9px;border-radius:50%;
    background:#22c55e;display:inline-block;
    box-shadow: 0 0 0 0 rgba(34,197,94,.5);
    animation: livePulse 1.5s infinite;
  }
  @keyframes livePulse {
    0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
    70%  { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
    100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
  }

  /* Score badge */
  .score-badge {
    display:inline-flex;align-items:center;justify-content:center;
    min-width:46px;height:26px;border-radius:20px;
    font-size:12px;font-weight:700;padding:0 8px;
  }

  /* Thin progress bar */
  .tbar { height:6px;border-radius:3px;background:#f1f5f9;overflow:hidden; }
  .tbar-fill { height:100%;border-radius:3px;transition:width .7s cubic-bezier(.4,0,.2,1); }

  /* Row hover */
  .algo-row:hover { background:#f8faff; }

  /* Rank circle */
  .rank-circle {
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:700;color:white;flex-shrink:0;
  }

  /* Section title */
  .sec-title {
    font-size:15px;font-weight:700;color:#1e293b;
    display:flex;align-items:center;gap:8px;
  }

  /* Pool tag */
  .pool-tag {
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 13px;border-radius:30px;font-size:12px;font-weight:600;
  }

  /* Scrollable table */
  .table-wrap { overflow-x:auto; }
  .algo-table { width:100%;border-collapse:collapse;font-size:13px; }
  .algo-table th {
    padding:10px 14px;font-size:11px;font-weight:600;
    text-transform:uppercase;letter-spacing:.6px;
    color:#94a3b8;background:#f8faff;white-space:nowrap;
    border-bottom:1px solid #e8edf5;
  }
  .algo-table td { padding:12px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle; }

  /* Interaction bar chart */
  .ibar-track { height:8px;background:#f1f5f9;border-radius:4px;flex:1; }
  .ibar-fill   { height:100%;border-radius:4px;transition:width .6s; }

  /* Spinner */
  .spin { animation:spin 1s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
</style>
@endpush

@section('content')
<div style="padding:28px 24px;max-width:1400px;margin:0 auto">

  {{-- ══ HEADER ══ --}}
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:28px">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
        <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-brain" style="color:white;font-size:16px"></i>
        </div>
        <h1 style="font-size:22px;font-weight:800;color:#0f172a;margin:0">Feed Algorithm Dashboard</h1>
      </div>
      <p style="color:#64748b;font-size:13px;margin:0;padding-left:48px">Real-time ranking engine · auto-refreshes every 15 seconds</p>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:8px;background:white;border-radius:30px;padding:8px 16px;box-shadow:0 2px 10px rgba(0,0,0,.07)">
        <span class="live-dot"></span>
        <span style="font-weight:600;font-size:13px;color:#22c55e">LIVE</span>
        <span style="color:#94a3b8;font-size:12px;margin-left:4px" id="last-updated">Loading…</span>
      </div>
      <button onclick="fetchData(true)"
        style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:0;border-radius:30px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;box-shadow:0 4px 14px rgba(102,126,234,.4);transition:.2s"
        onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
        <i class="fas fa-sync-alt" id="spin-icon"></i> Refresh Now
      </button>
    </div>
  </div>

  {{-- ══ STAT CARDS ══ --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px">

    <div class="stat-kard grad-blue">
      <div style="font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">Total Posts</div>
      <div style="font-size:32px;font-weight:800;line-height:1;margin-bottom:6px" id="s-total">—</div>
      <div style="font-size:11px;opacity:.75" id="s-total-sub">in 90-day window</div>
      <i class="fas fa-file-alt" style="position:absolute;top:18px;right:22px;font-size:22px;opacity:.25"></i>
    </div>

    <div class="stat-kard grad-green">
      <div style="font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">Scored Posts</div>
      <div style="font-size:32px;font-weight:800;line-height:1;margin-bottom:6px" id="s-scored">—</div>
      <div style="font-size:11px;opacity:.75" id="s-scored-sub">have a ranking score</div>
      <i class="fas fa-star" style="position:absolute;top:18px;right:22px;font-size:22px;opacity:.25"></i>
    </div>

    <div class="stat-kard grad-orange">
      <div style="font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">Avg Score</div>
      <div style="font-size:32px;font-weight:800;line-height:1;margin-bottom:6px" id="s-avg">—</div>
      <div style="font-size:11px;opacity:.75">across all scored posts</div>
      <i class="fas fa-chart-line" style="position:absolute;top:18px;right:22px;font-size:22px;opacity:.25"></i>
    </div>

    <div class="stat-kard grad-red">
      <div style="font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">Top Score</div>
      <div style="font-size:32px;font-weight:800;line-height:1;margin-bottom:6px" id="s-max">—</div>
      <div style="font-size:11px;opacity:.75">highest ranked post</div>
      <i class="fas fa-trophy" style="position:absolute;top:18px;right:22px;font-size:22px;opacity:.25"></i>
    </div>

    <div class="stat-kard grad-teal">
      <div style="font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">Active Users</div>
      <div style="font-size:32px;font-weight:800;line-height:1;margin-bottom:6px" id="s-active">—</div>
      <div style="font-size:11px;opacity:.75">browsing feed right now</div>
      <i class="fas fa-users" style="position:absolute;top:18px;right:22px;font-size:22px;opacity:.25"></i>
    </div>

    <div class="stat-kard grad-purple">
      <div style="font-size:11px;font-weight:600;opacity:.8;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px">Actions (24h)</div>
      <div style="font-size:32px;font-weight:800;line-height:1;margin-bottom:6px" id="s-inter">—</div>
      <div style="font-size:11px;opacity:.75">likes, comments, shares…</div>
      <i class="fas fa-bolt" style="position:absolute;top:18px;right:22px;font-size:22px;opacity:.25"></i>
    </div>

  </div>

  {{-- ══ ROW 2: Score dist + Interactions + Hashtags ══ --}}
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px">

    {{-- Score Distribution --}}
    <div class="glass" style="padding:24px">
      <div class="sec-title" style="margin-bottom:20px">
        <span style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#f953c6,#b91d73);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-fire" style="color:white;font-size:13px"></i>
        </span>
        Score Distribution
      </div>
      <div id="score-dist">
        <div style="text-align:center;padding:20px;color:#94a3b8"><i class="fas fa-circle-notch spin"></i></div>
      </div>
    </div>

    {{-- Interactions 24h --}}
    <div class="glass" style="padding:24px">
      <div class="sec-title" style="margin-bottom:20px">
        <span style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#11998e,#38ef7d);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-chart-bar" style="color:white;font-size:13px"></i>
        </span>
        Interactions (24h)
      </div>
      <div id="inter-body">
        <div style="text-align:center;padding:20px;color:#94a3b8"><i class="fas fa-circle-notch spin"></i></div>
      </div>
    </div>

    {{-- Top Hashtags --}}
    <div class="glass" style="padding:24px">
      <div class="sec-title" style="margin-bottom:20px">
        <span style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-hashtag" style="color:white;font-size:13px"></i>
        </span>
        Top Hashtags
      </div>
      <div id="tags-body">
        <div style="text-align:center;padding:20px;color:#94a3b8"><i class="fas fa-circle-notch spin"></i></div>
      </div>
    </div>

  </div>

  {{-- ══ ROW 3: Top Posts ══ --}}
  <div class="glass" style="padding:24px;margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
      <div class="sec-title">
        <span style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#f7971e,#ffd200);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-trophy" style="color:white;font-size:13px"></i>
        </span>
        Top Posts by Algorithm Score
      </div>
      <span id="posts-badge" style="background:#f0f4ff;color:#667eea;font-size:12px;font-weight:700;padding:4px 14px;border-radius:20px">— posts</span>
    </div>
    <div class="table-wrap">
      <table class="algo-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Post</th>
            <th>Author</th>
            <th>Type</th>
            <th>Final Score</th>
            <th>Engagement</th>
            <th>Velocity</th>
            <th>Viral</th>
            <th>Quality</th>
            <th>Views</th>
            <th>Impressions</th>
            <th>Eng. Rate</th>
          </tr>
        </thead>
        <tbody id="posts-tbody">
          <tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8">
            <i class="fas fa-circle-notch spin" style="margin-right:8px"></i>Loading posts…
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- ══ ROW 4: Top Users + Posts by Type ══ --}}
  <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:16px;margin-bottom:24px">

    {{-- Top Users --}}
    <div class="glass" style="padding:24px">
      <div class="sec-title" style="margin-bottom:20px">
        <span style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#00b4db,#0083b0);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-crown" style="color:white;font-size:13px"></i>
        </span>
        Most Active Users
      </div>
      <div id="users-body">
        <div style="text-align:center;padding:20px;color:#94a3b8"><i class="fas fa-circle-notch spin"></i></div>
      </div>
    </div>

    {{-- Posts by Type + Feed Pools --}}
    <div class="glass" style="padding:24px">
      <div class="sec-title" style="margin-bottom:20px">
        <span style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#8360c3,#2ebf91);display:flex;align-items:center;justify-content:center">
          <i class="fas fa-layer-group" style="color:white;font-size:13px"></i>
        </span>
        Content Breakdown
      </div>
      <div id="type-body">
        <div style="text-align:center;padding:20px;color:#94a3b8"><i class="fas fa-circle-notch spin"></i></div>
      </div>

      <div style="border-top:1px solid #f1f5f9;margin-top:20px;padding-top:20px">
        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:12px">Feed Pools · 15 posts/page</div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:#475569"><i class="fas fa-user-friends" style="color:#667eea;margin-right:6px"></i>Following</span>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:80px;height:6px;background:#f1f5f9;border-radius:3px"><div style="width:50%;height:100%;background:#667eea;border-radius:3px"></div></div>
              <span style="font-size:11px;font-weight:700;color:#667eea">50%</span>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:#475569"><i class="fas fa-thumbs-up" style="color:#22c55e;margin-right:6px"></i>Recommended</span>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:80px;height:6px;background:#f1f5f9;border-radius:3px"><div style="width:30%;height:100%;background:#22c55e;border-radius:3px"></div></div>
              <span style="font-size:11px;font-weight:700;color:#22c55e">30%</span>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:#475569"><i class="fas fa-fire" style="color:#f59e0b;margin-right:6px"></i>Trending</span>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:80px;height:6px;background:#f1f5f9;border-radius:3px"><div style="width:20%;height:100%;background:#f59e0b;border-radius:3px"></div></div>
              <span style="font-size:11px;font-weight:700;color:#f59e0b">20%</span>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:#475569"><i class="fas fa-seedling" style="color:#06b6d4;margin-right:6px"></i>New Creators</span>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:80px;height:6px;background:#f1f5f9;border-radius:3px"><div style="width:10%;height:100%;background:#06b6d4;border-radius:3px"></div></div>
              <span style="font-size:11px;font-weight:700;color:#06b6d4">10%</span>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:#475569"><i class="fas fa-random" style="color:#a855f7;margin-right:6px"></i>Random</span>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:80px;height:6px;background:#f1f5f9;border-radius:3px"><div style="width:5%;height:100%;background:#a855f7;border-radius:3px"></div></div>
              <span style="font-size:11px;font-weight:700;color:#a855f7">5%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- ══ SCORE FORMULA CARDS ══ --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">

    <div class="glass" style="padding:20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-chart-bar" style="color:white;font-size:14px"></i>
        </div>
        <span style="font-weight:700;font-size:14px;color:#1e293b">Engagement Score</span>
      </div>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.6">
        Likes ×1 + Comments ×3 + Shares ×4 + Saves ×3.5<br>
        Normalized against all posts in the pool.
      </p>
      <div style="margin-top:10px;display:inline-block;background:#f0f4ff;color:#667eea;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">engagement_score</div>
    </div>

    <div class="glass" style="padding:20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#11998e,#38ef7d);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-bolt" style="color:white;font-size:14px"></i>
        </div>
        <span style="font-weight:700;font-size:14px;color:#1e293b">Velocity Score</span>
      </div>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.6">
        Interactions gained per hour since posting.<br>
        Rewards fast-growing, freshly viral content.
      </p>
      <div style="margin-top:10px;display:inline-block;background:#f0fff4;color:#11998e;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">velocity_score</div>
    </div>

    <div class="glass" style="padding:20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#f7971e,#ffd200);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-check-double" style="color:white;font-size:14px"></i>
        </div>
        <span style="font-weight:700;font-size:14px;color:#1e293b">Quality Score</span>
      </div>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.6">
        Engagement-to-impression ratio.<br>
        High quality = people interact when they see it.
      </p>
      <div style="margin-top:10px;display:inline-block;background:#fff8f0;color:#f7971e;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">quality_score</div>
    </div>

    <div class="glass" style="padding:20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#f953c6,#b91d73);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-rocket" style="color:white;font-size:14px"></i>
        </div>
        <span style="font-weight:700;font-size:14px;color:#1e293b">Viral Score</span>
      </div>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.6">
        Velocity + shares spike detector.<br>
        Posts going viral get a temporary boost multiplier.
      </p>
      <div style="margin-top:10px;display:inline-block;background:#fff0f8;color:#b91d73;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">viral_score</div>
    </div>

    <div class="glass" style="padding:20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#00b4db,#0083b0);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-star" style="color:white;font-size:14px"></i>
        </div>
        <span style="font-weight:700;font-size:14px;color:#1e293b">Final Score</span>
      </div>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.6">
        Weighted combination of all 4 signals.<br>
        Personal affinity +40% for followed creators.
      </p>
      <div style="margin-top:10px;display:inline-block;background:#f0faff;color:#0083b0;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">final_score</div>
    </div>

  </div>

</div>

<script>
const DATA_URL = '{{ route("admin.community.algorithm.data") }}';

const TYPE_ICONS  = {video:'🎥',image:'🖼️',reel:'🎬',text:'📝',poll:'📊',audio:'🎵',share:'🔁',document:'📄'};
const TYPE_COLORS = {video:'#8b5cf6',image:'#06b6d4',reel:'#f59e0b',text:'#22c55e',poll:'#3b82f6',audio:'#f97316',share:'#ec4899',document:'#64748b'};
const INTER_META  = {
  view:    {icon:'👁',  label:'Views',    color:'#06b6d4'},
  like:    {icon:'❤️',  label:'Likes',    color:'#f43f5e'},
  comment: {icon:'💬',  label:'Comments', color:'#8b5cf6'},
  share:   {icon:'🔁',  label:'Shares',   color:'#f59e0b'},
  save:    {icon:'🔖',  label:'Saves',    color:'#22c55e'},
  watch:   {icon:'🎬',  label:'Watch',    color:'#3b82f6'},
  skip:    {icon:'⏭',  label:'Skips',    color:'#94a3b8'},
};

const RANK_COLORS = ['#667eea','#22c55e','#f59e0b','#f43f5e','#06b6d4','#8b5cf6','#f97316','#ec4899','#0083b0','#38ef7d'];

function n(v)  { return Number(v||0).toLocaleString(); }
function r(v)  { return Math.round(v||0); }
function pct(v){ return Number(v||0).toFixed(1)+'%'; }

function scoreColor(v) {
  if (v >= 80) return '#ef4444';
  if (v >= 50) return '#f59e0b';
  if (v >= 20) return '#3b82f6';
  return '#94a3b8';
}

function scoreBadge(v) {
  const c = scoreColor(v);
  return `<span class="score-badge" style="background:${c}18;color:${c};border:1px solid ${c}30">${r(v)}</span>`;
}

function miniScoreBar(val, max, color) {
  const pct = max > 0 ? Math.min(100, (val/max)*100) : 0;
  return `<div style="display:flex;align-items:center;gap:8px">
    <div style="flex:1;height:5px;background:#f1f5f9;border-radius:3px;min-width:55px">
      <div style="width:${pct.toFixed(1)}%;height:100%;background:${color};border-radius:3px;transition:width .6s"></div>
    </div>
    <span style="font-size:11px;font-weight:700;color:${color};min-width:26px">${r(val)}</span>
  </div>`;
}

function since(d) {
  const s = Math.floor((Date.now() - new Date(d)) / 1000);
  if (s < 60)   return s + 's ago';
  if (s < 3600) return Math.floor(s/60) + 'm ago';
  if (s < 86400)return Math.floor(s/3600) + 'h ago';
  return Math.floor(s/86400) + 'd ago';
}

function fetchData(manual = false) {
  if (manual) {
    const icon = document.getElementById('spin-icon');
    icon.classList.add('spin');
    setTimeout(() => icon.classList.remove('spin'), 800);
  }
  document.getElementById('last-updated').textContent = 'Refreshing…';

  fetch(DATA_URL)
    .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
    .then(d => {
      document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

      // ── Stat cards ──
      document.getElementById('s-total').textContent  = n(d.total_posts);
      document.getElementById('s-scored').textContent = n(d.posts_with_score);
      document.getElementById('s-avg').textContent    = d.avg_score;
      document.getElementById('s-max').textContent    = d.max_score;
      document.getElementById('s-active').textContent = n(d.active_feed_users);
      const totalI = Object.values(d.interactions_24h||{}).reduce((a,b)=>a+parseInt(b),0);
      document.getElementById('s-inter').textContent  = n(totalI);

      const scoredPct = d.total_posts > 0 ? ((d.posts_with_score/d.total_posts)*100).toFixed(0) : 0;
      document.getElementById('s-scored-sub').textContent = scoredPct + '% of all posts';

      // ── Score Distribution ──
      const dist  = d.score_distribution || {};
      const dTotal= (parseInt(dist.hot)||0)+(parseInt(dist.warm)||0)+(parseInt(dist.cool)||0)+(parseInt(dist.cold)||0)||1;
      const distRows = [
        {k:'hot',  label:'🔥 Hot',  sub:'score ≥ 80', color:'#ef4444', bg:'#fef2f2'},
        {k:'warm', label:'🌡 Warm', sub:'50–79',       color:'#f59e0b', bg:'#fffbeb'},
        {k:'cool', label:'❄ Cool',  sub:'20–49',       color:'#3b82f6', bg:'#eff6ff'},
        {k:'cold', label:'🧊 Cold', sub:'< 20',        color:'#94a3b8', bg:'#f8fafc'},
      ];
      document.getElementById('score-dist').innerHTML = distRows.map(row => {
        const v = parseInt(dist[row.k])||0;
        const p = ((v/dTotal)*100).toFixed(1);
        return `<div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:13px;font-weight:600;color:#334155">${row.label} <span style="font-weight:400;color:#94a3b8;font-size:11px">${row.sub}</span></span>
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-size:13px;font-weight:700;color:${row.color}">${v}</span>
              <span style="font-size:10px;background:${row.bg};color:${row.color};padding:2px 7px;border-radius:10px;font-weight:600">${p}%</span>
            </div>
          </div>
          <div class="tbar"><div class="tbar-fill" style="width:${p}%;background:${row.color}"></div></div>
        </div>`;
      }).join('');

      // ── Interactions 24h ──
      const interEntries = Object.entries(d.interactions_24h||{});
      const maxInter = interEntries.length ? Math.max(...interEntries.map(([,v])=>parseInt(v))) : 1;
      document.getElementById('inter-body').innerHTML = interEntries.length
        ? interEntries.sort((a,b)=>parseInt(b[1])-parseInt(a[1])).map(([type, count]) => {
            const m = INTER_META[type] || {icon:'•', label:type, color:'#94a3b8'};
            const p = ((parseInt(count)/maxInter)*100).toFixed(1);
            return `<div style="margin-bottom:14px">
              <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:13px;font-weight:600;color:#334155">${m.icon} ${m.label}</span>
                <span style="font-size:13px;font-weight:700;color:${m.color}">${n(count)}</span>
              </div>
              <div class="tbar"><div class="tbar-fill" style="width:${p}%;background:${m.color}"></div></div>
            </div>`;
          }).join('')
        : '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px">No interactions recorded today</div>';

      // ── Hashtags ──
      const tags = d.top_hashtags || [];
      const maxTag = tags.length ? Math.max(...tags.map(t=>t.posts_count)) : 1;
      document.getElementById('tags-body').innerHTML = tags.length
        ? tags.map((tag, i) => `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <span style="font-size:11px;font-weight:700;color:#cbd5e1;min-width:16px">${i+1}</span>
              <div style="flex:1">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                  <span style="font-size:13px;font-weight:600;color:#334155">#${tag.name}</span>
                  <span style="font-size:12px;font-weight:700;color:#667eea">${n(tag.posts_count)}</span>
                </div>
                <div class="tbar"><div class="tbar-fill" style="width:${((tag.posts_count/maxTag)*100).toFixed(1)}%;background:linear-gradient(90deg,#667eea,#764ba2)"></div></div>
              </div>
            </div>`).join('')
        : '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px">No hashtags yet</div>';

      // ── Top Posts table ──
      const posts = d.top_posts || [];
      document.getElementById('posts-badge').textContent = posts.length + ' posts';
      const maxScore = posts.reduce((m,p)=>Math.max(m,p.final_score),1);
      document.getElementById('posts-tbody').innerHTML = posts.length
        ? posts.map((p, i) => {
            const txt  = (p.content||'').substring(0,40) + ((p.content||'').length>40?'…':'');
            const ic   = TYPE_ICONS[p.type] || '📄';
            const tc   = TYPE_COLORS[p.type] || '#94a3b8';
            const sc   = scoreColor(p.final_score);
            return `<tr class="algo-row">
              <td style="padding-left:20px">
                <div class="rank-circle" style="background:${RANK_COLORS[i%10]}">${i+1}</div>
              </td>
              <td style="max-width:200px">
                <div style="font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${ic} ${txt||'[no text]'}</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px">${since(p.created_at)}</div>
              </td>
              <td>
                <span style="background:#f8faff;color:#334155;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;border:1px solid #e2e8f0">${p.author||'—'}</span>
              </td>
              <td>
                <span style="background:${tc}18;color:${tc};font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px">${(p.type||'').toUpperCase()}</span>
              </td>
              <td style="min-width:130px">${miniScoreBar(p.final_score, maxScore, sc)}</td>
              <td style="min-width:90px">${miniScoreBar(p.engagement_score, 100, '#667eea')}</td>
              <td style="min-width:90px">${miniScoreBar(p.velocity_score, 100, '#22c55e')}</td>
              <td style="min-width:90px">${miniScoreBar(p.viral_score, 100, '#f43f5e')}</td>
              <td style="min-width:90px">${miniScoreBar(p.quality_score, 100, '#f59e0b')}</td>
              <td style="text-align:center;font-weight:600;color:#334155">${n(p.views_count)}</td>
              <td style="text-align:center;font-weight:600;color:#334155">${n(p.impression_count)}</td>
              <td style="text-align:center">
                <span style="font-size:12px;font-weight:700;color:#8b5cf6">${Number(p.engagement_rate||0).toFixed(2)}%</span>
              </td>
            </tr>`;
          }).join('')
        : `<tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8">No scored posts yet</td></tr>`;

      // ── Top Users ──
      const users = d.top_users || [];
      document.getElementById('users-body').innerHTML = users.length
        ? users.map((u, i) => `
            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;${i>0?'border-top:1px solid #f1f5f9':''}">
              <div class="rank-circle" style="background:${RANK_COLORS[i%10]};width:34px;height:34px;font-size:13px">${i+1}</div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:700;font-size:13px;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${u.name}</div>
                <div style="font-size:11px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${u.username ? '@'+u.username : ''}</div>
                <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap">
                  <span style="font-size:11px;color:#64748b"><i class="fas fa-users" style="margin-right:3px;color:#06b6d4"></i>${n(u.followers_count)}</span>
                  <span style="font-size:11px;color:#64748b"><i class="fas fa-file-alt" style="margin-right:3px;color:#8b5cf6"></i>${n(u.post_count)} posts</span>
                  <span style="font-size:11px;color:#64748b"><i class="fas fa-bolt" style="margin-right:3px;color:#f59e0b"></i>${n(u.interactions_7d)}/7d</span>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <div style="font-size:16px;font-weight:800;color:#1e293b">${n(u.total_score)}</div>
                <div style="font-size:10px;color:#94a3b8;font-weight:500">total pts</div>
              </div>
            </div>`).join('')
        : '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px">No user data available</div>';

      // ── Posts by Type ──
      const types = d.posts_by_type || {};
      const totalTypes = Object.values(types).reduce((a,b)=>a+parseInt(b),0)||1;
      document.getElementById('type-body').innerHTML = Object.entries(types).length
        ? Object.entries(types).sort((a,b)=>parseInt(b[1])-parseInt(a[1])).map(([type, count]) => {
            const ic = TYPE_ICONS[type]||'📄';
            const tc = TYPE_COLORS[type]||'#94a3b8';
            const p  = ((parseInt(count)/totalTypes)*100).toFixed(1);
            return `<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <span style="font-size:16px">${ic}</span>
              <div style="flex:1">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                  <span style="font-size:12px;font-weight:600;color:#334155;text-transform:capitalize">${type}</span>
                  <span style="font-size:12px;font-weight:700;color:${tc}">${count} <span style="color:#94a3b8;font-weight:400">(${p}%)</span></span>
                </div>
                <div class="tbar"><div class="tbar-fill" style="width:${p}%;background:${tc}"></div></div>
              </div>
            </div>`;
          }).join('')
        : '<div style="text-align:center;padding:10px;color:#94a3b8;font-size:12px">No data</div>';
    })
    .catch(err => {
      document.getElementById('last-updated').textContent = '⚠ Error — retrying…';
      console.error('Algorithm fetch:', err);
    });
}

fetchData();
setInterval(fetchData, 15000);
</script>
@endsection
