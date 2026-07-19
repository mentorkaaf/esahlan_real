'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';

interface SuggestedUser { id: number; name: string; username?: string; avatar?: string; is_verified?: boolean; }
interface TrendingTag  { tag: string; posts_count?: number; }

function fmt(n: number) {
  return n >= 1e6 ? `${(n / 1e6).toFixed(1)}M` : n >= 1e3 ? `${(n / 1e3).toFixed(1)}K` : String(n);
}

// Sparkline SVG — 72×28 viewport
function Sparkline({ color }: { color: string }) {
  const raw = [2, 6, 4, 9, 5, 8, 12, 7, 14, 10, 8, 15];
  const min = Math.min(...raw), max = Math.max(...raw), range = max - min || 1;
  const W = 72, H = 28;
  const pts = raw.map((v, i) => `${(i / (raw.length - 1)) * W},${H - ((v - min) / range) * (H - 4) - 2}`).join(' ');
  return (
    <svg width={W} height={H} viewBox={`0 0 ${W} ${H}`} style={{ flexShrink: 0, overflow: 'visible' }}>
      <polyline fill="none" stroke={color} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" points={pts} opacity="0.85" />
    </svg>
  );
}

// Card wrapper
function RCard({ title, action, onAction, children }: {
  title: string; action?: string; onAction?: () => void; children: React.ReactNode;
}) {
  return (
    <div style={{
      background: 'var(--card)', borderRadius: 'var(--r12)',
      boxShadow: 'var(--s1)', overflow: 'hidden',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '18px 20px 8px' }}>
        <h3 style={{ fontWeight: 800, fontSize: 16, color: 'var(--t1)' }}>{title}</h3>
        {action && (
          <button
            onClick={onAction}
            style={{ fontSize: 13, fontWeight: 700, color: 'var(--orange)', background: 'none', border: 'none', cursor: 'pointer' }}
            onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'underline')}
            onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'none')}
          >
            {action}
          </button>
        )}
      </div>
      <div style={{ padding: '4px 20px 16px' }}>{children}</div>
    </div>
  );
}

function Skel({ w, h, round }: { w: number | string; h: number; round?: number | string }) {
  return <div className="skeleton" style={{ width: w, height: h, borderRadius: round ?? 'var(--r4)' }} />;
}

export default function RightSidebar() {
  const [suggestions, setSuggestions] = useState<SuggestedUser[]>([]);
  const [trending, setTrending]       = useState<TrendingTag[]>([]);
  const [following, setFollowing]     = useState<Record<number, boolean>>({});
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: SuggestedUser[] }>('/community/suggestions')
      .then(r => setSuggestions((Array.isArray(r.data) ? r.data : []).slice(0, 5)))
      .catch(() => {});
    api.get<{ data: TrendingTag[] }>('/community/trending/hashtags')
      .then(r => setTrending((Array.isArray(r.data) ? r.data : []).slice(0, 3)))
      .catch(() => {});
  }, []);

  async function toggleFollow(id: number) {
    setFollowing(f => ({ ...f, [id]: !f[id] }));
    await api.post(`/community/follow/${id}`).catch(() => setFollowing(f => ({ ...f, [id]: !f[id] })));
  }

  const TREND_COLORS = ['#FF8A00', '#3B82F6', '#22C55E'];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, padding: '16px 16px 32px' }}>

      {/* ── Trending ──────────────────────────────────────── */}
      <RCard title="Trending Now" action="View all" onAction={() => router.push('/explore')}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          {trending.length > 0
            ? trending.map((t, i) => (
              <button
                key={t.tag}
                onClick={() => router.push(`/explore?q=${encodeURIComponent(t.tag)}`)}
                style={{
                  display: 'flex', alignItems: 'center', gap: 14,
                  padding: '10px 8px', borderRadius: 'var(--r8)',
                  background: 'transparent', border: 'none', cursor: 'pointer', textAlign: 'left',
                }}
                onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.background = 'var(--bg)')}
                onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.background = 'transparent')}
              >
                <div style={{
                  width: 30, height: 30, borderRadius: 'var(--r4)',
                  background: 'var(--bg)', display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 12, fontWeight: 800, color: 'var(--t3)', flexShrink: 0,
                }}>
                  {i + 1}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <p style={{ fontWeight: 700, fontSize: 14, color: 'var(--t1)' }}>#{t.tag}</p>
                  {t.posts_count != null && <p style={{ fontSize: 12, color: 'var(--t3)', marginTop: 1 }}>{fmt(t.posts_count)} posts</p>}
                </div>
                <Sparkline color={TREND_COLORS[i] ?? '#FF8A00'} />
              </button>
            ))
            : [1,2,3].map(i => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '10px 8px' }}>
                <Skel w={30} h={30} round="var(--r4)" />
                <div style={{ flex: 1 }}>
                  <Skel w="65%" h={12} />
                  <div style={{ marginTop: 6 }}><Skel w="45%" h={10} /></div>
                </div>
                <Skel w={72} h={28} />
              </div>
            ))
          }
        </div>
      </RCard>

      {/* ── Who to Follow ─────────────────────────────────── */}
      <RCard title="Who to Follow" action="View all" onAction={() => router.push('/explore')}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          {suggestions.length > 0
            ? suggestions.map(u => {
              const isF = following[u.id] ?? false;
              const av  = mediaUrl(u.avatar);
              const nm  = u.name ?? 'User';
              return (
                <div key={u.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 0' }}>
                  <button onClick={() => router.push(`/profile/${u.id}`)} style={{ flexShrink: 0, background: 'none', border: 'none', cursor: 'pointer' }}>
                    <div style={{ width: 42, height: 42, borderRadius: '50%', overflow: 'hidden', background: 'linear-gradient(135deg,#FF8A00,#FF5200)' }}>
                      {av
                        ? <img src={av} alt={nm} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                        : <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><span style={{ color: '#fff', fontWeight: 800, fontSize: 16 }}>{nm[0]}</span></div>
                      }
                    </div>
                  </button>
                  <button onClick={() => router.push(`/profile/${u.id}`)} style={{ flex: 1, minWidth: 0, background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                      <span style={{ fontWeight: 700, fontSize: 14, color: 'var(--t1)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{nm}</span>
                      {u.is_verified && (
                        <svg className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--orange)' }} viewBox="0 0 24 24" fill="currentColor">
                          <path fillRule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clipRule="evenodd" />
                        </svg>
                      )}
                    </div>
                    {u.username && <span style={{ fontSize: 12, color: 'var(--t3)' }}>@{u.username}</span>}
                  </button>
                  <button
                    onClick={() => toggleFollow(u.id)}
                    style={{
                      flexShrink: 0, height: 32, padding: '0 16px',
                      borderRadius: 'var(--pill)', fontSize: 13, fontWeight: 700, cursor: 'pointer',
                      border: isF ? '1.5px solid var(--border)' : `1.5px solid var(--navy)`,
                      background: 'transparent',
                      color: isF ? 'var(--t2)' : 'var(--navy)',
                      transition: 'all 130ms',
                    }}
                    onMouseEnter={e => {
                      const b = e.currentTarget as HTMLButtonElement;
                      if (isF) { b.style.borderColor = '#EF4444'; b.style.color = '#EF4444'; }
                      else { b.style.background = 'var(--orange)'; b.style.borderColor = 'var(--orange)'; b.style.color = '#fff'; }
                    }}
                    onMouseLeave={e => {
                      const b = e.currentTarget as HTMLButtonElement;
                      b.style.background = 'transparent';
                      if (isF) { b.style.borderColor = 'var(--border)'; b.style.color = 'var(--t2)'; }
                      else { b.style.borderColor = 'var(--navy)'; b.style.color = 'var(--navy)'; }
                    }}
                  >
                    {isF ? 'Following' : 'Follow'}
                  </button>
                </div>
              );
            })
            : [1,2,3].map(i => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 0' }}>
                <Skel w={42} h={42} round="50%" />
                <div style={{ flex: 1 }}>
                  <Skel w="60%" h={12} />
                  <div style={{ marginTop: 6 }}><Skel w="42%" h={10} /></div>
                </div>
                <Skel w={68} h={32} round="var(--pill)" />
              </div>
            ))
          }
        </div>
      </RCard>

      {/* ── Your Activity ─────────────────────────────────── */}
      <RCard title="Your Activity">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          {([
            { em: '🛵', label: 'Active Order',   sub: 'Food from Hodan Star', badge: 'On the way', bc: '#16A34A', bb: 'rgba(22,163,74,0.1)'  },
            { em: '💰', label: 'Wallet Balance',  sub: '$125.50',              badge: 'Top up',    bc: '#3B82F6', bb: 'rgba(59,130,246,0.1)', href: '/epay' },
            { em: '⭐', label: 'ePoints',         sub: '2,450 PTS',            badge: 'Redeem',    bc: '#FF8A00', bb: 'rgba(255,138,0,0.1)'  },
            { em: '💬', label: 'Messages',        sub: '5 unread messages',    badge: 'Open',      bc: '#8B5CF6', bb: 'rgba(139,92,246,0.1)', href: '/chat' },
          ]).map(item => (
            <div key={item.label} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 8px', borderRadius: 'var(--r8)' }}
              onMouseEnter={e => ((e.currentTarget as HTMLElement).style.background = 'var(--bg)')}
              onMouseLeave={e => ((e.currentTarget as HTMLElement).style.background = 'transparent')}
            >
              <div style={{ width: 38, height: 38, borderRadius: 'var(--r8)', background: 'var(--bg)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18, flexShrink: 0 }}>
                {item.em}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <p style={{ fontWeight: 600, fontSize: 13.5, color: 'var(--t1)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.label}</p>
                <p style={{ fontSize: 12, color: 'var(--t3)', marginTop: 1 }}>{item.sub}</p>
              </div>
              <button
                onClick={() => item.href && router.push(item.href)}
                style={{ flexShrink: 0, padding: '4px 12px', borderRadius: 'var(--pill)', fontSize: 11.5, fontWeight: 800, color: item.bc, background: item.bb, border: 'none', cursor: item.href ? 'pointer' : 'default' }}
              >
                {item.badge}
              </button>
            </div>
          ))}
        </div>
      </RCard>

      {/* ── Events ────────────────────────────────────────── */}
      <RCard title="Events Near You" action="View all" onAction={() => {}}>
        <div style={{ display: 'flex', gap: 14, padding: '8px 0' }}>
          <div style={{
            width: 64, height: 64, borderRadius: 'var(--r8)', flexShrink: 0,
            background: 'linear-gradient(135deg,#FF8A00,#FF5200)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26,
          }}>
            🏢
          </div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <p style={{ fontWeight: 700, fontSize: 14, color: 'var(--t1)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              eSahlan Business Summit
            </p>
            <p style={{ fontSize: 12, color: 'var(--t3)', marginTop: 3 }}>Mogadishu, Somalia</p>
            <p style={{ fontSize: 12, fontWeight: 600, color: 'var(--orange)', marginTop: 3 }}>12 July 2024 · 09:00 AM</p>
          </div>
        </div>
      </RCard>

      {/* ── Live Support ──────────────────────────────────── */}
      <div style={{
        borderRadius: 'var(--r12)', padding: '18px 20px',
        background: 'linear-gradient(140deg,#07003B 0%,#1A0099 100%)',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 14 }}>
          <div style={{
            width: 44, height: 44, borderRadius: 'var(--r8)', flexShrink: 0,
            background: 'var(--orange)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            <svg className="w-5 h-5" style={{ color: '#fff' }} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.9}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
          </div>
          <div>
            <p style={{ fontWeight: 800, fontSize: 16, color: '#fff' }}>Live Support</p>
            <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginTop: 4 }}>
              <div className="pulse" style={{ width: 8, height: 8, borderRadius: '50%', background: '#4ADE80' }} />
              <span style={{ fontSize: 13, color: 'rgba(255,255,255,0.65)' }}>We are online!</span>
            </div>
          </div>
        </div>
        <button
          style={{
            width: '100%', height: 42, borderRadius: 'var(--pill)',
            background: 'var(--orange)', color: '#fff',
            fontWeight: 700, fontSize: 14, border: 'none', cursor: 'pointer',
            transition: 'opacity 150ms',
          }}
          onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.opacity = '0.88')}
          onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.opacity = '1')}
        >
          Start Chat
        </button>
      </div>

      <p style={{ fontSize: 12, color: 'var(--t3)', textAlign: 'center', paddingBottom: 8 }}>
        © 2026 eSahlan ·{' '}
        <button style={{ background: 'none', border: 'none', fontSize: 12, color: 'var(--t3)', cursor: 'pointer' }}
          onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'underline')}
          onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'none')}
        >Privacy</button>
        {' · '}
        <button style={{ background: 'none', border: 'none', fontSize: 12, color: 'var(--t3)', cursor: 'pointer' }}
          onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'underline')}
          onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'none')}
        >Terms</button>
      </p>
    </div>
  );
}
