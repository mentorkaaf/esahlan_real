'use client';
import { useEffect, useRef, useCallback, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useFeed } from '@/hooks/useFeed';
import { CommunityPost } from '@/types';
import { mediaUrl, api } from '@/lib/api';
import { formatDistanceToNow } from 'date-fns';
import CommentsModal from '@/components/CommentsModal';
import StoriesBar from '@/components/StoriesBar';
import { useAuthStore } from '@/store/auth';

function ago(v?: string | null) {
  if (!v) return '';
  const d = new Date(v);
  return isNaN(d.getTime()) ? '' : formatDistanceToNow(d, { addSuffix: true });
}
function fmt(n: number) {
  if (n >= 1e6) return `${(n / 1e6).toFixed(1)}M`;
  if (n >= 1e3) return `${(n / 1e3).toFixed(1)}K`;
  return String(n);
}

const SERVICES = [
  { label: 'eFood',      href: '/shop/efood',      bg: '#FFF1F0', color: '#EF4444', em: '🍽️' },
  { label: 'eShop',      href: '/shop/eshop',       bg: '#EFF6FF', color: '#3B82F6', em: '🛍️' },
  { label: 'eParcel',    href: '/shop/eparcel',     bg: '#FFFBEB', color: '#F59E0B', em: '📦' },
  { label: 'eGrocery',   href: '/shop/egrocery',    bg: '#F0FDF4', color: '#22C55E', em: '🛒' },
  { label: 'eWholesale', href: '/shop/ewholesale',  bg: '#ECFEFF', color: '#06B6D4', em: '🏪' },
  { label: 'eLaundry',   href: '/shop/elaundry',    bg: '#F5F3FF', color: '#8B5CF6', em: '👕' },
  { label: 'eMoving',    href: '/shop/emoving',     bg: '#FDF2F8', color: '#EC4899', em: '🚚' },
  { label: 'eHealth',    href: '/shop/ehealth',     bg: '#FFF1F0', color: '#EF4444', em: '❤️' },
  { label: 'eTicket',    href: '/shop/eticket',     bg: '#EFF6FF', color: '#3B82F6', em: '✈️' },
  { label: 'eData',      href: '/shop/edata',       bg: '#F0FDF4', color: '#10B981', em: '📶' },
];

// ── Section wrapper ────────────────────────────────────────────────────────────
function Section({ children, noPad }: { children: React.ReactNode; noPad?: boolean }) {
  return (
    <div className="card" style={{ overflow: 'hidden', padding: noPad ? 0 : 20 }}>
      {children}
    </div>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────
export default function FeedPage() {
  const { posts, loading, error, hasMore, load, likePost } = useFeed();
  const sentinel  = useRef<HTMLDivElement>(null);
  const { user }  = useAuthStore();
  const router    = useRouter();
  const avatar    = mediaUrl((user as unknown as { avatar?: string })?.avatar ?? '');
  const firstName = user?.name?.split(' ')[0] ?? 'there';

  useEffect(() => { load(); }, []); // eslint-disable-line

  useEffect(() => {
    if (!sentinel.current) return;
    const obs = new IntersectionObserver(
      e => { if (e[0].isIntersecting && hasMore && !loading) load(); },
      { threshold: 0.1 }
    );
    obs.observe(sentinel.current);
    return () => obs.disconnect();
  }, [hasMore, loading, load]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, padding: '16px 16px 40px' }}>

      {/* ── Stories ──────────────────────────────────────────── */}
      <Section noPad>
        <StoriesBar />
      </Section>

      {/* ── Create Post ──────────────────────────────────────── */}
      <Section>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 14 }}>
          {/* Avatar */}
          <button onClick={() => router.push(`/profile/${user?.id}`)} style={{ flexShrink: 0 }}>
            <div style={{
              width: 44, height: 44, borderRadius: '50%', overflow: 'hidden', flexShrink: 0,
              background: 'linear-gradient(135deg,#FF8A00,#FF5200)',
            }}>
              {avatar
                ? <img src={avatar} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                : <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <span style={{ color: '#fff', fontWeight: 800, fontSize: 16 }}>{user?.name?.[0] ?? '?'}</span>
                  </div>
              }
            </div>
          </button>
          {/* Input */}
          <button
            onClick={() => router.push('/create')}
            style={{
              flex: 1, height: 48, borderRadius: 24,
              background: 'var(--input)', border: '1.5px solid var(--border)',
              padding: '0 20px', textAlign: 'left', cursor: 'pointer',
              color: 'var(--t3)', fontSize: 15, fontWeight: 500,
              transition: 'border-color 150ms',
            }}
            onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.borderColor = 'var(--orange)')}
            onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.borderColor = 'var(--border)')}
          >
            What&apos;s on your mind, {firstName}?
          </button>
        </div>

        {/* Action buttons */}
        <div style={{ borderTop: '1px solid var(--border)', paddingTop: 4, display: 'flex', gap: 2 }}>
          {([
            { em: '📷', label: 'Photo',   col: '#22C55E' },
            { em: '🎬', label: 'Video',   col: '#FF8A00' },
            { em: '🎙️', label: 'Podcast', col: '#8B5CF6' },
            { em: '📊', label: 'Poll',    col: '#3B82F6' },
            { em: '😊', label: 'Feeling', col: '#F59E0B' },
            { em: '🔴', label: 'Live',    col: '#EF4444' },
          ]).map(a => (
            <button
              key={a.label}
              onClick={() => router.push('/create')}
              style={{
                flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center',
                gap: 6, height: 44, borderRadius: 'var(--r8)', border: 'none',
                background: 'transparent', cursor: 'pointer', fontSize: 13, fontWeight: 600,
                color: 'var(--t2)',
              }}
              onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.background = 'var(--bg)')}
              onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.background = 'transparent')}
            >
              <span style={{ fontSize: 16 }}>{a.em}</span>
              <span className="hidden sm:inline">{a.label}</span>
            </button>
          ))}
        </div>
      </Section>

      {/* ── Services ─────────────────────────────────────────── */}
      <Section>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
          <h3 style={{ fontWeight: 800, fontSize: 16, color: 'var(--t1)' }}>eSahlan Services</h3>
          <Link href="/shop" style={{ fontWeight: 700, fontSize: 13, color: 'var(--orange)', textDecoration: 'none' }}>View all</Link>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 8 }}>
          {SERVICES.map(s => (
            <Link
              key={s.href} href={s.href}
              style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10, padding: '12px 4px', borderRadius: 'var(--r8)', textDecoration: 'none', transition: 'background 130ms' }}
              onMouseEnter={e => ((e.currentTarget as HTMLElement).style.background = 'var(--bg)')}
              onMouseLeave={e => ((e.currentTarget as HTMLElement).style.background = 'transparent')}
            >
              <div style={{ width: 52, height: 52, borderRadius: 16, background: s.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 24 }}>
                {s.em}
              </div>
              <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--t2)', textAlign: 'center' }}>{s.label}</span>
            </Link>
          ))}
        </div>
      </Section>

      {/* ── Error ────────────────────────────────────────────── */}
      {error && (
        <div style={{ borderRadius: 'var(--r8)', padding: '14px 18px', background: '#FFF1F0', color: '#EF4444', fontSize: 14, border: '1px solid #FECACA' }}>
          ⚠️ {error}
        </div>
      )}

      {/* ── Feed posts ───────────────────────────────────────── */}
      {posts.map((post, i) => (
        <PostCard key={post.id} post={post} onLike={() => likePost(post.id)} index={i} />
      ))}

      {/* Loading */}
      {loading && (
        <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 8, padding: '32px 0' }}>
          {[0, 1, 2].map(i => (
            <div
              key={i}
              className="pulse"
              style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--orange)', animationDelay: `${i * 0.22}s` }}
            />
          ))}
        </div>
      )}

      {!loading && !hasMore && posts.length > 0 && (
        <p style={{ textAlign: 'center', color: 'var(--t3)', fontSize: 13, padding: '20px 0 8px' }}>
          ✓ You&apos;re all caught up
        </p>
      )}

      <div ref={sentinel} style={{ height: 8 }} />
    </div>
  );
}

// ── PostCard ──────────────────────────────────────────────────────────────────
function PostCard({ post, onLike, index }: { post: CommunityPost; onLike: () => void; index: number }) {
  const [commentsOpen, setCommentsOpen]   = useState(false);
  const [commentsCount, setCommentsCount] = useState(post.comments_count ?? 0);
  const [liked, setLiked]                 = useState(post.is_liked ?? false);
  const [likesCount, setLikesCount]       = useState(post.likes_count ?? 0);
  const [saved, setSaved]                 = useState(post.is_saved ?? false);
  const [menuOpen, setMenuOpen]           = useState(false);
  const [showReact, setShowReact]         = useState(false);
  const reactTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const router = useRouter();

  const profile  = post.user ?? post.profile ?? null;
  const name     = profile?.name ?? profile?.display_name ?? 'Unknown';
  const username = profile?.username ?? '';
  const avatar   = mediaUrl(profile?.avatar);
  const timeAgo  = ago(post.created_at);
  const media    = post.media ?? [];
  const tags     = post.hashtags ?? [];
  const pid      = profile?.user_id ?? (post.user as unknown as { id?: number })?.id ?? '';

  function handleLike() {
    setLiked(l => !l);
    setLikesCount(c => liked ? c - 1 : c + 1);
    onLike();
  }
  async function handleSave() {
    setSaved(s => !s);
    await api.post(`/community/posts/${post.id}/save`).catch(() => setSaved(s => !s));
  }

  const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '😡'];

  return (
    <article className="card anim-fade-up" style={{ overflow: 'hidden', animationDelay: `${index * 25}ms` }}>

      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '18px 20px 14px' }}>
        <button onClick={() => pid && router.push(`/profile/${pid}`)} style={{ flexShrink: 0 }}>
          <div style={{
            width: 46, height: 46, borderRadius: '50%', overflow: 'hidden',
            background: 'linear-gradient(135deg,#FF8A00,#FF5200)',
            transition: 'transform 150ms',
          }}
            onMouseEnter={e => ((e.currentTarget as HTMLElement).style.transform = 'scale(1.06)')}
            onMouseLeave={e => ((e.currentTarget as HTMLElement).style.transform = 'scale(1)')}
          >
            {avatar
              ? <img src={avatar} alt={name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
              : <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <span style={{ color: '#fff', fontWeight: 800, fontSize: 17 }}>{name[0]}</span>
                </div>
            }
          </div>
        </button>

        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
            <button
              onClick={() => pid && router.push(`/profile/${pid}`)}
              style={{ fontWeight: 700, fontSize: 15.5, color: 'var(--t1)', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}
              onMouseEnter={e => ((e.currentTarget as HTMLElement).style.textDecoration = 'underline')}
              onMouseLeave={e => ((e.currentTarget as HTMLElement).style.textDecoration = 'none')}
            >
              {name}
            </button>
            {profile?.is_verified && <VerifiedBadge />}
            {post.is_ad && (
              <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 'var(--pill)', background: 'var(--orange-soft)', color: 'var(--orange)' }}>
                Sponsored
              </span>
            )}
            {post.type === 'reel' && (
              <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 'var(--pill)', background: 'rgba(236,72,153,0.1)', color: '#EC4899' }}>
                Reel
              </span>
            )}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 2 }}>
            {username && <span style={{ fontSize: 13, color: 'var(--t3)' }}>@{username}</span>}
            {username && timeAgo && <span style={{ fontSize: 13, color: 'var(--t3)' }}>·</span>}
            {timeAgo  && <span style={{ fontSize: 13, color: 'var(--t3)' }}>{timeAgo}</span>}
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexShrink: 0 }}>
          <button
            style={{
              height: 34, padding: '0 16px', borderRadius: 'var(--pill)',
              border: '1.5px solid var(--border)', background: 'transparent',
              fontSize: 13, fontWeight: 700, color: 'var(--t2)', cursor: 'pointer',
            }}
            onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.borderColor = 'var(--orange)'; (e.currentTarget as HTMLButtonElement).style.color = 'var(--orange)'; }}
            onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.borderColor = 'var(--border)'; (e.currentTarget as HTMLButtonElement).style.color = 'var(--t2)'; }}
          >
            Follow
          </button>

          <div style={{ position: 'relative' }}>
            <button
              onClick={() => setMenuOpen(m => !m)}
              style={{ width: 34, height: 34, borderRadius: 'var(--r4)', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--t3)' }}
              onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.background = 'var(--bg)')}
              onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.background = 'transparent')}
            >
              <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 6.75a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM12 13.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM12 20.25a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
              </svg>
            </button>
            {menuOpen && (
              <>
                <div style={{ position: 'fixed', inset: 0, zIndex: 10 }} onClick={() => setMenuOpen(false)} />
                <div className="card anim-scale" style={{ position: 'absolute', right: 0, top: 38, zIndex: 20, width: 200, borderRadius: 'var(--r8)', overflow: 'hidden', boxShadow: 'var(--s3)' }}>
                  {[
                    { label: saved ? 'Unsave post' : 'Save post', em: '🔖', fn: handleSave },
                    { label: 'Copy link', em: '🔗', fn: () => navigator.clipboard.writeText(`${window.location.origin}/post/${post.id}`) },
                    { label: 'Report post', em: '🚩', fn: () => {} },
                  ].map(item => (
                    <button
                      key={item.label}
                      onClick={() => { item.fn(); setMenuOpen(false); }}
                      style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', fontSize: 14, color: 'var(--t1)', background: 'transparent', border: 'none', cursor: 'pointer', textAlign: 'left' }}
                      onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.background = 'var(--bg)')}
                      onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.background = 'transparent')}
                    >
                      <span>{item.em}</span><span>{item.label}</span>
                    </button>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      {post.content && (
        <div style={{ padding: '0 20px 14px' }}>
          <p className="clamp-3" style={{ fontSize: 15, lineHeight: 1.6, color: 'var(--t1)', whiteSpace: 'pre-wrap' }}>
            {post.content}
          </p>
        </div>
      )}

      {/* Hashtags */}
      {tags.length > 0 && (
        <div style={{ padding: '0 20px 12px', display: 'flex', flexWrap: 'wrap', gap: '4px 8px' }}>
          {tags.slice(0, 5).map(t => (
            <span key={t} style={{ fontSize: 14, fontWeight: 600, color: 'var(--orange)', cursor: 'pointer' }}
              onMouseEnter={e => ((e.currentTarget as HTMLElement).style.textDecoration = 'underline')}
              onMouseLeave={e => ((e.currentTarget as HTMLElement).style.textDecoration = 'none')}
            >
              #{t}
            </span>
          ))}
        </div>
      )}

      {/* Media */}
      {media.length > 0 && <MediaGrid media={media} />}

      {/* Counts */}
      {(likesCount > 0 || commentsCount > 0 || (post.views_count ?? 0) > 0) && (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 20px', borderBottom: '1px solid var(--border)' }}>
          {likesCount > 0 ? (
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <div style={{ display: 'flex', gap: -2 }}>
                <span style={{ fontSize: 15 }}>👍</span>
                <span style={{ fontSize: 15 }}>❤️</span>
                <span style={{ fontSize: 15 }}>😂</span>
              </div>
              <span style={{ fontSize: 13, color: 'var(--t2)' }}>{fmt(likesCount)}</span>
            </div>
          ) : <span />}
          <div style={{ display: 'flex', gap: 16 }}>
            {commentsCount > 0 && (
              <button
                onClick={() => setCommentsOpen(true)}
                style={{ fontSize: 13, color: 'var(--t2)', background: 'none', border: 'none', cursor: 'pointer' }}
                onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'underline')}
                onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.textDecoration = 'none')}
              >
                {fmt(commentsCount)} comments
              </button>
            )}
            {(post.views_count ?? 0) > 0 && (
              <span style={{ fontSize: 13, color: 'var(--t3)', display: 'flex', alignItems: 'center', gap: 4 }}>
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {fmt(post.views_count ?? 0)}
              </span>
            )}
          </div>
        </div>
      )}

      {/* Actions */}
      <div style={{ display: 'flex', padding: '4px 8px' }}>
        {/* Like + reaction hover */}
        <div
          style={{ flex: 1, position: 'relative' }}
          onMouseEnter={() => { reactTimer.current = setTimeout(() => setShowReact(true), 480); }}
          onMouseLeave={() => { if (reactTimer.current) clearTimeout(reactTimer.current); setShowReact(false); }}
        >
          {showReact && (
            <div
              className="anim-scale"
              style={{
                position: 'absolute', bottom: 'calc(100% + 8px)', left: 0, zIndex: 20,
                display: 'flex', gap: 2, padding: '8px 12px', borderRadius: 'var(--pill)',
                background: 'var(--card)', boxShadow: 'var(--s4)', border: '1px solid var(--border)',
              }}
            >
              {REACTIONS.map(r => (
                <button
                  key={r}
                  onClick={() => { handleLike(); setShowReact(false); }}
                  style={{
                    fontSize: 24, background: 'none', border: 'none', cursor: 'pointer',
                    width: 40, height: 40, display: 'flex', alignItems: 'center', justifyContent: 'center',
                    borderRadius: '50%', transition: 'transform 130ms',
                  }}
                  onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.transform = 'scale(1.5) translateY(-4px)')}
                  onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.transform = 'scale(1)')}
                >
                  {r}
                </button>
              ))}
            </div>
          )}
          <ActBtn
            onClick={handleLike} active={liked}
            label={liked ? 'Liked' : 'Like'}
            icon={liked
              ? <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" /></svg>
              : <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.9}><path strokeLinecap="round" strokeLinejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>
            }
          />
        </div>

        <ActBtn
          onClick={() => setCommentsOpen(true)} label="Comment"
          icon={<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.9}><path strokeLinecap="round" strokeLinejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z" /></svg>}
        />
        <ActBtn
          onClick={() => navigator.share?.({ url: `${window.location.origin}/post/${post.id}` }).catch(() => {})} label="Share"
          icon={<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.9}><path strokeLinecap="round" strokeLinejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" /></svg>}
        />

        {/* Save */}
        <button
          onClick={handleSave}
          title="Save"
          style={{
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            width: 44, height: 44, borderRadius: 'var(--r8)',
            background: 'transparent', border: 'none', cursor: 'pointer',
            color: saved ? 'var(--orange)' : 'var(--t3)',
          }}
          onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.background = 'var(--bg)')}
          onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.background = 'transparent')}
        >
          <svg className="w-5 h-5" viewBox="0 0 24 24" fill={saved ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={1.9}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" />
          </svg>
        </button>
      </div>

      {commentsOpen && (
        <CommentsModal
          postId={post.id}
          commentsCount={commentsCount}
          onClose={() => setCommentsOpen(false)}
          onCountChange={setCommentsCount}
          postTitle={name}
          postImage={media[0] ? mediaUrl(
            media[0].type === 'video'
              ? (media[0].thumbnail ?? media[0].thumbnail_url ?? '')
              : media[0].url
          ) : undefined}
          likesCount={likesCount}
          sharesCount={post.shares_count ?? 0}
        />
      )}
    </article>
  );
}

function ActBtn({ onClick, icon, label, active }: { onClick: () => void; icon: React.ReactNode; label: string; active?: boolean }) {
  return (
    <button
      onClick={onClick}
      style={{
        flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center',
        gap: 8, height: 44, borderRadius: 'var(--r8)',
        background: 'transparent', border: 'none', cursor: 'pointer',
        fontSize: 14, fontWeight: 700,
        color: active ? 'var(--orange)' : 'var(--t2)',
      }}
      onMouseEnter={e => ((e.currentTarget as HTMLButtonElement).style.background = 'var(--bg)')}
      onMouseLeave={e => ((e.currentTarget as HTMLButtonElement).style.background = 'transparent')}
    >
      {icon}
      <span className="hidden sm:inline">{label}</span>
    </button>
  );
}

// ── MediaGrid ─────────────────────────────────────────────────────────────────
function MediaGrid({ media }: { media: CommunityPost['media'] }) {
  const first = media[0];
  if (!first) return null;
  if (first.type === 'video') {
    return <VideoPlayer url={first.mp4_direct_url ?? first.hls_url ?? first.url} thumb={first.thumbnail ?? first.thumbnail_url} />;
  }
  const n = media.length;
  const imgStyle = { width: '100%', height: '100%', objectFit: 'cover' as const, display: 'block' };

  if (n === 1) return (
    <div style={{ background: '#000', maxHeight: 540, overflow: 'hidden' }}>
      <img src={mediaUrl(first.url)} alt="" style={{ ...imgStyle, maxHeight: 540, objectFit: 'contain' }} loading="lazy" />
    </div>
  );
  if (n === 2) return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2, background: '#000', height: 340 }}>
      {media.map(m => <div key={m.id} style={{ overflow: 'hidden' }}><img src={mediaUrl(m.url)} alt="" style={imgStyle} loading="lazy" /></div>)}
    </div>
  );
  if (n === 3) return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2, background: '#000', height: 380 }}>
      <div style={{ gridRow: 'span 2', overflow: 'hidden' }}><img src={mediaUrl(media[0].url)} alt="" style={imgStyle} loading="lazy" /></div>
      {media.slice(1).map(m => <div key={m.id} style={{ overflow: 'hidden' }}><img src={mediaUrl(m.url)} alt="" style={imgStyle} loading="lazy" /></div>)}
    </div>
  );
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2, background: '#000' }}>
      {media.slice(0, 4).map((m, i) => (
        <div key={m.id} style={{ position: 'relative', aspectRatio: '1', overflow: 'hidden' }}>
          <img src={mediaUrl(m.url)} alt="" style={imgStyle} loading="lazy" />
          {i === 3 && n > 4 && (
            <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.52)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <span style={{ color: '#fff', fontWeight: 900, fontSize: 32 }}>+{n - 4}</span>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

// ── VideoPlayer ───────────────────────────────────────────────────────────────
function VideoPlayer({ url, thumb }: { url: string; thumb?: string }) {
  const ref = useRef<HTMLVideoElement>(null);
  const [playing, setPlaying] = useState(false);
  const [progress, setProgress] = useState(0);
  const [muted, setMuted] = useState(true);
  const src = mediaUrl(url);

  const init = useCallback(async (el: HTMLVideoElement, s: string) => {
    if (s.includes('.m3u8')) {
      const { default: Hls } = await import('hls.js');
      if (Hls.isSupported()) { const h = new Hls(); h.loadSource(s); h.attachMedia(el); }
      else if (el.canPlayType('application/vnd.apple.mpegurl')) el.src = s;
    } else el.src = s;
  }, []);

  useEffect(() => { if (ref.current && src) init(ref.current, src); }, [src, init]);

  return (
    <div className="group" style={{ position: 'relative', background: '#000', aspectRatio: '16/9', overflow: 'hidden' }}>
      <video
        ref={ref}
        poster={thumb ? mediaUrl(thumb) : undefined}
        loop playsInline muted={muted}
        onPlay={() => setPlaying(true)}
        onPause={() => setPlaying(false)}
        onTimeUpdate={() => {
          const v = ref.current;
          if (v?.duration) setProgress((v.currentTime / v.duration) * 100);
        }}
        style={{ width: '100%', height: '100%', objectFit: 'contain' }}
      />
      <div
        style={{
          position: 'absolute', inset: 0,
          background: 'linear-gradient(to top, rgba(0,0,0,0.45) 0%, transparent 50%)',
          opacity: 0, transition: 'opacity 200ms',
        }}
        className="group-hover:opacity-100"
      />
      {!playing && (
        <button
          onClick={() => ref.current?.play()}
          style={{
            position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center',
            background: 'transparent', border: 'none', cursor: 'pointer',
          }}
        >
          <div style={{
            width: 60, height: 60, borderRadius: '50%',
            background: 'rgba(0,0,0,0.45)', backdropFilter: 'blur(8px)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            border: '2px solid rgba(255,255,255,0.25)',
          }}>
            <svg className="w-7 h-7" style={{ color: '#fff', marginLeft: 3 }} fill="currentColor" viewBox="0 0 24 24">
              <path d="M8 5v14l11-7z" />
            </svg>
          </div>
        </button>
      )}
      {/* Controls bar */}
      <div
        style={{
          position: 'absolute', bottom: 0, left: 0, right: 0,
          padding: '12px 14px', display: 'flex', alignItems: 'center', gap: 12,
          opacity: 0, transition: 'opacity 200ms',
        }}
        className="group-hover:opacity-100"
      >
        <button
          onClick={() => ref.current?.paused ? ref.current.play() : ref.current?.pause()}
          style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#fff', flexShrink: 0 }}
        >
          {playing
            ? <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" /></svg>
            : <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
          }
        </button>
        <div
          style={{ flex: 1, height: 4, borderRadius: 99, background: 'rgba(255,255,255,0.3)', cursor: 'pointer', overflow: 'hidden' }}
          onClick={e => {
            const v = ref.current;
            if (!v?.duration) return;
            const r = e.currentTarget.getBoundingClientRect();
            v.currentTime = ((e.clientX - r.left) / r.width) * v.duration;
          }}
        >
          <div style={{ height: '100%', borderRadius: 99, background: 'var(--orange)', width: `${progress}%`, transition: 'width 250ms' }} />
        </div>
        <button
          onClick={() => { const v = ref.current; if (v) { v.muted = !muted; setMuted(m => !m); } }}
          style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#fff', flexShrink: 0 }}
        >
          {muted
            ? <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
            : <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15.536 8.464a5 5 0 010 7.072M12 6v12m-6-9v6" /></svg>
          }
        </button>
      </div>
    </div>
  );
}

function VerifiedBadge() {
  return (
    <svg className="w-4 h-4 shrink-0" style={{ color: 'var(--orange)' }} viewBox="0 0 24 24" fill="currentColor">
      <path fillRule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clipRule="evenodd" />
    </svg>
  );
}
