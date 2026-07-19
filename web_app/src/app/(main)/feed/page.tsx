'use client';
import { useEffect, useRef, useCallback } from 'react';
import { useFeed } from '@/hooks/useFeed';
import { CommunityPost } from '@/types';
import { mediaUrl } from '@/lib/api';
import { formatDistanceToNow } from 'date-fns';
import StoriesBar from '@/components/StoriesBar';

export default function FeedPage() {
  const { posts, loading, error, hasMore, load, likePost } = useFeed();
  const sentinel = useRef<HTMLDivElement>(null);

  useEffect(() => { load(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Infinite scroll
  useEffect(() => {
    if (!sentinel.current) return;
    const obs = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting && hasMore && !loading) load();
    }, { threshold: 0.1 });
    obs.observe(sentinel.current);
    return () => obs.disconnect();
  }, [hasMore, loading, load]);

  return (
    <div className="max-w-2xl mx-auto">
      <StoriesBar />
      <div className="px-4 py-6">
      <h1 className="text-xl font-black text-gray-900 dark:text-white mb-6">Feed</h1>

      {error && (
        <div className="bg-red-50 dark:bg-red-950/20 text-red-600 rounded-xl p-4 mb-4 text-sm">{error}</div>
      )}

      <div className="space-y-4">
        {posts.map(post => (
          <PostCard key={post.id} post={post} onLike={() => likePost(post.id)} />
        ))}
      </div>

      {loading && (
        <div className="flex justify-center py-8">
          <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
        </div>
      )}

      <div ref={sentinel} className="h-4" />
      </div>
    </div>
  );
}

function safeDate(val: string | null | undefined): string {
  if (!val) return '';
  const d = new Date(val);
  return isNaN(d.getTime()) ? '' : formatDistanceToNow(d, { addSuffix: true });
}

function PostCard({ post, onLike }: { post: CommunityPost; onLike: () => void }) {
  // backend returns `user`, some endpoints return `profile` — normalise
  const profile = post.user ?? post.profile ?? null;
  const displayName = profile?.name ?? profile?.display_name ?? 'Unknown';
  const username   = profile?.username ?? 'unknown';
  const avatar = mediaUrl(profile?.avatar);
  const timeAgo = safeDate(post.created_at);
  const media = post.media ?? [];
  const hashtags = post.hashtags ?? [];
  const liked = post.is_liked ?? false;
  const saved = post.is_saved ?? false;

  return (
    <article className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">
      {/* Header */}
      <div className="flex items-center gap-3 px-4 py-3">
        <div className="w-9 h-9 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0">
          {avatar
            ? <img src={avatar} alt={displayName} className="w-full h-full object-cover" />
            : <div className="w-full h-full flex items-center justify-center text-sm font-bold text-gray-500">
                {displayName[0] ?? '?'}
              </div>
          }
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-bold text-gray-900 dark:text-white truncate">{displayName}</p>
          <p className="text-xs text-gray-400">@{username}{timeAgo ? ` · ${timeAgo}` : ''}</p>
        </div>
        {post.is_ad && (
          <span className="text-[10px] font-bold bg-[#FF8A00]/10 text-[#FF8A00] px-2 py-0.5 rounded-full">Ad</span>
        )}
      </div>

      {/* Content */}
      {post.content && (
        <p className="px-4 pb-3 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{post.content}</p>
      )}

      {/* Media */}
      {media.length > 0 && <MediaGrid media={media} />}

      {/* Hashtags */}
      {hashtags.length > 0 && (
        <div className="px-4 pt-2 pb-1 flex flex-wrap gap-1">
          {hashtags.slice(0, 5).map(tag => (
            <span key={tag} className="text-xs text-[#FF8A00] font-semibold">#{tag}</span>
          ))}
        </div>
      )}

      {/* Actions */}
      <div className="flex items-center gap-1 px-3 py-2 border-t border-gray-50 dark:border-gray-800">
        <ActionBtn
          onClick={onLike}
          active={liked}
          activeColor="text-red-500"
          icon={<HeartIcon filled={liked} />}
          count={post.likes_count ?? 0}
        />
        <ActionBtn
          icon={<CommentIcon />}
          count={post.comments_count ?? 0}
        />
        <ActionBtn
          icon={<ShareIcon />}
          count={post.shares_count ?? 0}
        />
        <div className="flex-1" />
        <ActionBtn
          active={saved}
          activeColor="text-[#FF8A00]"
          icon={<SaveIcon filled={saved} />}
          count={post.saves_count ?? 0}
        />
        <span className="text-xs text-gray-300 mx-2">·</span>
        <span className="text-xs text-gray-400">{fmtCount(post.views_count ?? 0)} views</span>
      </div>
    </article>
  );
}

function MediaGrid({ media }: { media: CommunityPost['media'] }) {
  const first = media[0];
  if (!first) return null;

  if (first.type === 'video') {
    return (
      <VideoPlayer
        url={first.mp4_direct_url ?? first.hls_url ?? first.url}
        thumbnail={first.thumbnail ?? first.thumbnail_url}
      />
    );
  }

  if (media.length === 1) {
    return (
      <img
        src={mediaUrl(first.url)}
        alt=""
        className="w-full max-h-[500px] object-cover"
        loading="lazy"
      />
    );
  }

  return (
    <div className={`grid gap-0.5 ${media.length === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}>
      {media.slice(0, media.length > 3 ? 3 : undefined).map((m, i) => (
        <div key={m.id} className="relative aspect-square overflow-hidden bg-gray-100">
          <img src={mediaUrl(m.url)} alt="" className="w-full h-full object-cover" loading="lazy" />
          {i === 2 && media.length > 3 && (
            <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
              <span className="text-white font-bold text-xl">+{media.length - 3}</span>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

function VideoPlayer({ url, thumbnail }: { url: string; thumbnail?: string }) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const resolvedUrl = mediaUrl(url);

  const setupHls = useCallback(async (el: HTMLVideoElement, src: string) => {
    if (src.includes('.m3u8')) {
      const { default: Hls } = await import('hls.js');
      if (Hls.isSupported()) {
        const hls = new Hls({ maxBufferLength: 30 });
        hls.loadSource(src);
        hls.attachMedia(el);
      } else if (el.canPlayType('application/vnd.apple.mpegurl')) {
        el.src = src;
      }
    } else {
      el.src = src;
    }
  }, []);

  useEffect(() => {
    const el = videoRef.current;
    if (el && resolvedUrl) setupHls(el, resolvedUrl);
  }, [resolvedUrl, setupHls]);

  return (
    <div className="relative bg-black aspect-[4/5] max-h-[500px]">
      <video
        ref={videoRef}
        poster={thumbnail ? mediaUrl(thumbnail) : undefined}
        controls
        loop
        playsInline
        className="w-full h-full object-contain"
      />
    </div>
  );
}

function ActionBtn({
  icon, count, onClick, active, activeColor,
}: {
  icon: React.ReactNode;
  count?: number;
  onClick?: () => void;
  active?: boolean;
  activeColor?: string;
}) {
  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-semibold transition-colors hover:bg-gray-50 dark:hover:bg-gray-800 ${
        active ? activeColor : 'text-gray-400'
      }`}
    >
      {icon}
      {count !== undefined && <span>{fmtCount(count)}</span>}
    </button>
  );
}

function fmtCount(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${(n / 1000).toFixed(1)}K`;
  return String(n);
}

function HeartIcon({ filled }: { filled: boolean }) {
  return (
    <svg className="w-4 h-4" viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
    </svg>
  );
}
function CommentIcon() {
  return (
    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  );
}
function ShareIcon() {
  return (
    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
    </svg>
  );
}
function SaveIcon({ filled }: { filled: boolean }) {
  return (
    <svg className="w-4 h-4" viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
    </svg>
  );
}
