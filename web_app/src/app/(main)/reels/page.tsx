'use client';
import { useState, useEffect, useRef, useCallback } from 'react';
import { api, mediaUrl } from '@/lib/api';
import { CommunityPost } from '@/types';

export default function ReelsPage() {
  const [reels, setReels] = useState<CommunityPost[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(false);
  const [activeIdx, setActiveIdx] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  const load = useCallback(async (p = 1) => {
    if (loading) return;
    setLoading(true);
    try {
      const res = await api.get<{ data: CommunityPost[]; meta: { current_page: number; last_page: number } }>(
        `/community/feed?page=${p}&per_page=10&type=reel`
      );
      setReels(prev => p === 1 ? res.data : [...prev, ...res.data]);
      setHasMore(res.meta.current_page < res.meta.last_page);
      setPage(p + 1);
    } catch {}
    setLoading(false);
  }, [loading]);

  useEffect(() => { load(1); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // IntersectionObserver per-card for active tracking
  useEffect(() => {
    const cards = containerRef.current?.querySelectorAll('[data-reel]');
    if (!cards) return;
    const obs = new IntersectionObserver(entries => {
      for (const e of entries) {
        if (e.isIntersecting && e.intersectionRatio >= 0.6) {
          const idx = Number((e.target as HTMLElement).dataset.reel);
          setActiveIdx(idx);
          // Load more near end
          if (idx >= reels.length - 3 && hasMore && !loading) load(page);
        }
      }
    }, { threshold: 0.6 });
    cards.forEach(c => obs.observe(c));
    return () => obs.disconnect();
  }, [reels, hasMore, loading, load, page]);

  return (
    <div
      ref={containerRef}
      className="h-screen overflow-y-scroll snap-y snap-mandatory scrollbar-none"
      style={{ scrollbarWidth: 'none' }}
    >
      {reels.map((reel, i) => (
        <ReelCard key={reel.id} reel={reel} index={i} active={i === activeIdx} />
      ))}
      {loading && (
        <div className="h-screen flex items-center justify-center">
          <div className="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin" />
        </div>
      )}
    </div>
  );
}

function ReelCard({ reel, index, active }: { reel: CommunityPost; index: number; active: boolean }) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [muted, setMuted] = useState(true);
  const [liked, setLiked] = useState(reel.is_liked);
  const [likes, setLikes] = useState(reel.likes_count);
  const media = reel.media[0];
  const videoUrl = media?.mp4_direct_url ?? media?.hls_url ?? media?.url ?? '';

  useEffect(() => {
    const v = videoRef.current;
    if (!v) return;
    if (active) {
      v.currentTime = 0;
      v.play().catch(() => {});
    } else {
      v.pause();
    }
  }, [active]);

  useEffect(() => {
    const v = videoRef.current;
    if (!v || !videoUrl) return;
    if (videoUrl.includes('.m3u8')) {
      import('hls.js').then(({ default: Hls }) => {
        if (Hls.isSupported()) {
          const hls = new Hls();
          hls.loadSource(mediaUrl(videoUrl));
          hls.attachMedia(v);
        } else { v.src = mediaUrl(videoUrl); }
      });
    } else {
      v.src = mediaUrl(videoUrl);
    }
  }, [videoUrl]);

  async function toggleLike() {
    setLiked(l => !l);
    setLikes(n => liked ? n - 1 : n + 1);
    try { await api.post(`/community/posts/${reel.id}/like`); } catch {}
  }

  return (
    <div
      data-reel={index}
      className="relative h-screen w-full snap-start snap-always overflow-hidden bg-black"
    >
      {/* Video */}
      {media?.type === 'video' ? (
        <video
          ref={videoRef}
          loop
          playsInline
          muted={muted}
          poster={media.thumbnail_url ? mediaUrl(media.thumbnail_url) : undefined}
          className="absolute inset-0 w-full h-full object-cover"
        />
      ) : media ? (
        <img src={mediaUrl(media.url)} alt="" className="absolute inset-0 w-full h-full object-cover" />
      ) : null}

      {/* Gradient overlay */}
      <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent pointer-events-none" />

      {/* Right side actions */}
      <div className="absolute right-3 bottom-32 flex flex-col items-center gap-5">
        {/* Avatar */}
        <div className="relative">
          <div className="w-10 h-10 rounded-full overflow-hidden border-2 border-white bg-gray-800">
            {reel.profile.avatar
              ? <img src={mediaUrl(reel.profile.avatar)} alt="" className="w-full h-full object-cover" />
              : <div className="w-full h-full flex items-center justify-center text-white font-bold text-sm">{reel.profile.display_name[0]}</div>
            }
          </div>
          <div className="absolute -bottom-2 left-1/2 -translate-x-1/2 w-5 h-5 bg-[#FF8A00] rounded-full flex items-center justify-center">
            <svg className="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
            </svg>
          </div>
        </div>

        {/* Like */}
        <button onClick={toggleLike} className="flex flex-col items-center gap-1">
          <div className={`w-11 h-11 rounded-full bg-black/30 flex items-center justify-center ${liked ? 'text-red-500' : 'text-white'}`}>
            <svg className="w-6 h-6" viewBox="0 0 24 24" fill={liked ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </div>
          <span className="text-white text-xs font-bold">{fmtN(likes)}</span>
        </button>

        {/* Comment */}
        <button className="flex flex-col items-center gap-1">
          <div className="w-11 h-11 rounded-full bg-black/30 flex items-center justify-center text-white">
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
          <span className="text-white text-xs font-bold">{fmtN(reel.comments_count)}</span>
        </button>

        {/* Share */}
        <button className="flex flex-col items-center gap-1">
          <div className="w-11 h-11 rounded-full bg-black/30 flex items-center justify-center text-white">
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
          </div>
          <span className="text-white text-xs font-bold">{fmtN(reel.shares_count)}</span>
        </button>

        {/* Mute toggle */}
        <button
          onClick={() => setMuted(m => !m)}
          className="w-11 h-11 rounded-full bg-black/30 flex items-center justify-center text-white"
        >
          {muted ? (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clipRule="evenodd" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
            </svg>
          ) : (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15.536 8.464a5 5 0 010 7.072M12 6v12m-3.536-9.536a5 5 0 000 7.072" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
            </svg>
          )}
        </button>
      </div>

      {/* Bottom info */}
      <div className="absolute bottom-6 left-4 right-16">
        <p className="text-white font-bold text-sm">@{reel.profile.username}</p>
        {reel.content && <p className="text-white/80 text-sm mt-1 line-clamp-2">{reel.content}</p>}
        {reel.hashtags.length > 0 && (
          <p className="text-[#FF8A00] text-xs mt-1 font-semibold">
            {reel.hashtags.slice(0, 3).map(t => `#${t}`).join(' ')}
          </p>
        )}
      </div>
    </div>
  );
}

function fmtN(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${(n / 1000).toFixed(1)}K`;
  return String(n);
}
