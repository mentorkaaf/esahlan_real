'use client';
import { useState, useEffect } from 'react';
import { api, mediaUrl } from '@/lib/api';
import { StoryGroup } from '@/types';
import { useAuthStore } from '@/store/auth';

const W   = 100;  // story card width
const H   = 160;  // story card height (portrait)
const PAD = 3;    // ring border thickness
const R   = 14;   // border-radius

const GRAD = 'linear-gradient(135deg,#F9A825 0%,#E91E8C 40%,#9C27B0 70%,#3F51B5 100%)';

export default function StoriesBar() {
  const [groups, setGroups] = useState<StoryGroup[]>([]);
  const [viewer, setViewer] = useState<{ g: number; s: number } | null>(null);
  const { user } = useAuthStore();

  useEffect(() => {
    api.get<{ data: StoryGroup[] }>('/community/stories')
      .then(r => {
        const data = Array.isArray(r.data) ? r.data : [];
        setGroups(data);
      })
      .catch(() => {});
  }, []);

  function open(g: number) { setViewer({ g, s: 0 }); }
  function close() { setViewer(null); }
  function next() {
    if (!viewer) return;
    const grp = groups[viewer.g];
    if (viewer.s < (grp?.stories?.length ?? 0) - 1) setViewer({ ...viewer, s: viewer.s + 1 });
    else if (viewer.g < groups.length - 1) setViewer({ g: viewer.g + 1, s: 0 });
    else close();
  }
  function prev() {
    if (!viewer) return;
    if (viewer.s > 0) setViewer({ ...viewer, s: viewer.s - 1 });
    else if (viewer.g > 0) {
      const p = groups[viewer.g - 1];
      setViewer({ g: viewer.g - 1, s: (p?.stories?.length ?? 1) - 1 });
    }
  }

  const activeGrp   = viewer ? groups[viewer.g] : null;
  const activeStory = activeGrp?.stories[viewer?.s ?? 0];
  const myAvatar    = mediaUrl((user as unknown as { avatar?: string })?.avatar ?? '');
  const myName      = user?.name?.split(' ')[0] ?? 'You';

  return (
    <>
      {/* ── Story row ── */}
      <div
        className="no-scroll overflow-x-auto flex gap-3"
        style={{ padding: '16px 20px 12px' }}
      >
        {/* ── Create Story card ── */}
        <button
          onClick={() => {}}
          style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 0, flexShrink: 0 }}
        >
          <div
            style={{
              width: W, height: H, borderRadius: R,
              overflow: 'hidden', position: 'relative',
              background: 'var(--input)',
            }}
          >
            {/* dimmed avatar bg */}
            {myAvatar && (
              <img
                src={myAvatar} alt=""
                style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', opacity: 0.4 }}
              />
            )}
            {/* gradient overlay bottom */}
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 60%)' }} />
            {/* + circle */}
            <div
              style={{
                position: 'absolute', top: '50%', left: '50%',
                transform: 'translate(-50%, -60%)',
                width: 36, height: 36, borderRadius: '50%',
                background: 'var(--orange)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                boxShadow: '0 2px 8px rgba(24,119,242,0.5)',
                border: '3px solid var(--card)',
              }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={3} strokeLinecap="round">
                <path d="M12 4v16m8-8H4" />
              </svg>
            </div>
            {/* label */}
            <span
              style={{
                position: 'absolute', bottom: 10, left: 0, right: 0,
                textAlign: 'center', color: '#fff', fontSize: 11, fontWeight: 700,
                lineHeight: 1.3,
              }}
            >
              Create Story
            </span>
          </div>
        </button>

        {/* ── User story cards ── */}
        {groups.map((grp, i) => {
          // API returns 'user' key, StoryGroup type uses 'profile' — handle both
          const p         = (grp as unknown as { user?: typeof grp.profile }).user ?? grp.profile;
          const nm        = p?.username ?? p?.name ?? p?.display_name ?? 'User';
          const av        = mediaUrl(p?.avatar);
          // API returns 'all_viewed', type uses 'has_unseen' — handle both
          const seen      = (grp as unknown as { all_viewed?: boolean }).all_viewed ?? !grp.has_unseen;
          const firstStory = grp.stories?.[0];
          const previewUrl = firstStory
            ? mediaUrl(firstStory.media_type === 'video'
                ? ((firstStory as unknown as { thumbnail_url?: string }).thumbnail_url ?? firstStory.media_url)
                : firstStory.media_url)
            : null;
          return (
            <button
              key={p?.id ?? i}
              onClick={() => open(i)}
              style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 0, flexShrink: 0 }}
            >
              {/* Gradient ring */}
              <div
                style={{
                  width: W + PAD * 2, height: H + PAD * 2,
                  borderRadius: R + PAD,
                  background: seen ? 'var(--border)' : GRAD,
                  padding: PAD,
                }}
              >
                {/* Card */}
                <div
                  style={{
                    width: W, height: H, borderRadius: R,
                    overflow: 'hidden', position: 'relative',
                    background: 'var(--input)',
                    border: '2px solid var(--card)',
                  }}
                >
                  {/* Story preview background */}
                  {previewUrl
                    ? <img src={previewUrl} alt={nm} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }} />
                    : firstStory?.media_type === 'text'
                    ? <div style={{ position: 'absolute', inset: 0, background: firstStory.bg_color ?? 'linear-gradient(135deg,#FF8A00,#FF5200)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 8 }}>
                        <span style={{ color: '#fff', fontSize: 10, fontWeight: 700, textAlign: 'center', lineHeight: 1.4 }}>{firstStory.text_content}</span>
                      </div>
                    : av
                    ? <img src={av} alt={nm} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }} />
                    : <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(135deg,#FF8A00,#FF5200)' }} />
                  }
                  {/* Bottom gradient overlay */}
                  <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.1) 50%, transparent 100%)' }} />
                  {/* Avatar circle top-left — only if user has avatar */}
                  {av && (
                    <div
                      style={{
                        position: 'absolute', top: 8, left: 8,
                        width: 30, height: 30, borderRadius: '50%',
                        overflow: 'hidden', border: '2.5px solid var(--orange)',
                        background: '#333', flexShrink: 0,
                      }}
                    >
                      <img src={av} alt={nm} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>
                  )}
                  {/* Name bottom */}
                  <span
                    style={{
                      position: 'absolute', bottom: 8, left: 6, right: 6,
                      color: '#fff', fontSize: 11, fontWeight: 700,
                      lineHeight: 1.3, textAlign: 'left',
                      overflow: 'hidden', display: '-webkit-box',
                      WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
                    }}
                  >
                    {nm}
                  </span>
                </div>
              </div>
            </button>
          );
        })}

        {/* Skeletons */}
        {groups.length === 0 && Array.from({ length: 6 }).map((_, i) => (
          <div key={i} style={{ flexShrink: 0 }}>
            <div className="skeleton" style={{ width: W, height: H, borderRadius: R }} />
          </div>
        ))}
      </div>

      {/* ── Story viewer — Facebook style ── */}
      {viewer && activeGrp && activeStory && (() => {
        const vp    = (activeGrp as unknown as { user?: typeof activeGrp.profile }).user ?? activeGrp.profile;
        const vName = vp?.username ?? vp?.name ?? vp?.display_name ?? 'User';
        const vAv   = mediaUrl(vp?.avatar);
        return (
        <div
          style={{ position: 'fixed', inset: 0, zIndex: 60, background: '#18191A', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
        >
          {/* Close button — top left */}
          <button
            onClick={close}
            style={{ position: 'absolute', top: 16, left: 16, zIndex: 20, width: 40, height: 40, borderRadius: '50%', background: 'rgba(255,255,255,0.12)', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={2.5} strokeLinecap="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
          </button>

          {/* Prev arrow */}
          {(viewer.s > 0 || viewer.g > 0) && (
            <button
              onClick={prev}
              style={{ position: 'absolute', left: 20, zIndex: 20, width: 44, height: 44, borderRadius: '50%', background: 'rgba(255,255,255,0.15)', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
            </button>
          )}

          {/* Next arrow */}
          <button
            onClick={next}
            style={{ position: 'absolute', right: 20, zIndex: 20, width: 44, height: 44, borderRadius: '50%', background: 'rgba(255,255,255,0.15)', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6" /></svg>
          </button>

          {/* Story card */}
          <div
            style={{
              width: '100%', maxWidth: 440,
              height: '100vh', maxHeight: '100vh',
              position: 'relative', display: 'flex', flexDirection: 'column',
              borderRadius: 0, overflow: 'hidden',
              background: '#000',
            }}
          >
            {/* Story content — fills card */}
            <div style={{ flex: 1, position: 'relative', overflow: 'hidden', background: '#000' }}>
              {activeStory.media_type === 'video'
                ? <video key={activeStory.id} src={mediaUrl(activeStory.media_url)} autoPlay playsInline onEnded={next} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                : activeStory.media_type === 'text'
                ? <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 32, background: activeStory.bg_color ?? 'linear-gradient(135deg,#07003B,#1A0099)' }}>
                    <p style={{ color: '#fff', fontSize: 28, fontWeight: 800, textAlign: 'center', lineHeight: 1.4 }}>{activeStory.text_content}</p>
                  </div>
                : <img key={activeStory.id} src={mediaUrl(activeStory.media_url)} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
              }

              {/* Top gradient overlay */}
              <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 120, background: 'linear-gradient(to bottom, rgba(0,0,0,0.6) 0%, transparent 100%)', pointerEvents: 'none' }} />

              {/* Progress bars */}
              <div style={{ position: 'absolute', top: 12, left: 12, right: 12, display: 'flex', gap: 4 }}>
                {activeGrp.stories.map((_, si) => (
                  <div key={si} style={{ flex: 1, height: 3, borderRadius: 99, background: 'rgba(255,255,255,0.35)', overflow: 'hidden' }}>
                    <div style={{ height: '100%', background: '#fff', borderRadius: 99, width: si < viewer.s ? '100%' : si === viewer.s ? '60%' : '0%', transition: si === viewer.s ? 'width 5000ms linear' : 'none' }} />
                  </div>
                ))}
              </div>

              {/* User info row */}
              <div style={{ position: 'absolute', top: 26, left: 12, right: 12, display: 'flex', alignItems: 'center', gap: 10 }}>
                <div style={{ width: 38, height: 38, borderRadius: '50%', overflow: 'hidden', border: '2px solid #fff', background: '#333', flexShrink: 0 }}>
                  {vAv
                    ? <img src={vAv} alt={vName} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    : <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><span style={{ color: '#fff', fontWeight: 800, fontSize: 15 }}>{vName[0]}</span></div>
                  }
                </div>
                <div style={{ flex: 1 }}>
                  <p style={{ color: '#fff', fontWeight: 700, fontSize: 14, lineHeight: 1.2 }}>{vName}</p>
                  <p style={{ color: 'rgba(255,255,255,0.65)', fontSize: 12 }}>{viewer.s + 1} / {activeGrp.stories.length}</p>
                </div>
                {/* Pause / more */}
                <button style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 4 }}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </button>
                <button style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 4 }}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                </button>
              </div>

              {/* Bottom gradient */}
              <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: 100, background: 'linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 100%)', pointerEvents: 'none' }} />
            </div>

            {/* Bottom bar — message + reactions */}
            <div style={{ background: '#242526', padding: '12px 16px', display: 'flex', alignItems: 'center', gap: 10, flexShrink: 0 }}>
              <div style={{ flex: 1, height: 40, borderRadius: 20, background: 'rgba(255,255,255,0.1)', border: '1px solid rgba(255,255,255,0.15)', display: 'flex', alignItems: 'center', padding: '0 16px' }}>
                <span style={{ color: 'rgba(255,255,255,0.5)', fontSize: 14 }}>Send message...</span>
              </div>
              {['👍','❤️','😮','😂','😢','😡'].map(e => (
                <button key={e} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 22, lineHeight: 1, padding: 2 }}>{e}</button>
              ))}
            </div>
          </div>
        </div>
        );
      })()}
    </>
  );
}
