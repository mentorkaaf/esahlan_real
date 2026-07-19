'use client';
import { useState, useEffect, useRef } from 'react';
import { api, mediaUrl } from '@/lib/api';
import { useAuthStore } from '@/store/auth';
import { formatDistanceToNow } from 'date-fns';

interface Comment {
  id: number;
  content: string;
  user: { id: number; name: string; avatar?: string; username?: string };
  created_at: string;
  likes_count: number;
  is_liked: boolean;
}

interface Props {
  postId: number;
  commentsCount: number;
  onClose: () => void;
  onCountChange?: (n: number) => void;
  postTitle?: string;
  postImage?: string;
  likesCount?: number;
  sharesCount?: number;
}

export default function CommentsModal({
  postId, commentsCount, onClose, onCountChange,
  postTitle, postImage, likesCount = 0, sharesCount = 0,
}: Props) {
  const [comments, setComments] = useState<Comment[]>([]);
  const [loading, setLoading]   = useState(true);
  const [text, setText]         = useState('');
  const [sending, setSending]   = useState(false);
  const [count, setCount]       = useState(commentsCount);
  const { user }                = useAuthStore();
  const inputRef                = useRef<HTMLInputElement>(null);
  const listRef                 = useRef<HTMLDivElement>(null);

  useEffect(() => {
    api.get<{ data: { data: Comment[] } | Comment[] }>(`/community/posts/${postId}/comments`)
      .then(res => {
        const raw = res.data;
        const list = Array.isArray(raw) ? raw : (raw as { data: Comment[] }).data ?? [];
        setComments(Array.isArray(list) ? list : []);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
    setTimeout(() => inputRef.current?.focus(), 200);
  }, [postId]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!text.trim() || sending) return;
    const body = text.trim();
    setText('');
    setSending(true);
    const optimistic: Comment = {
      id: Date.now(),
      content: body,
      user: { id: user?.id ?? 0, name: user?.name ?? 'You', avatar: user?.avatar, username: '' },
      created_at: new Date().toISOString(),
      likes_count: 0,
      is_liked: false,
    };
    setComments(prev => [...prev, optimistic]);
    setCount(c => { const n = c + 1; onCountChange?.(n); return n; });
    setTimeout(() => listRef.current?.scrollTo({ top: listRef.current.scrollHeight, behavior: 'smooth' }), 50);
    try {
      const res = await api.post<{ data: Comment }>(`/community/posts/${postId}/comments`, { content: body });
      setComments(prev => prev.map(c => c.id === optimistic.id ? (res.data ?? optimistic) : c));
    } catch {
      setComments(prev => prev.filter(c => c.id !== optimistic.id));
      setCount(c => { const n = c - 1; onCountChange?.(n); return n; });
      setText(body);
    }
    setSending(false);
  }

  async function likeComment(id: number) {
    setComments(prev => prev.map(c =>
      c.id === id
        ? { ...c, is_liked: !c.is_liked, likes_count: c.is_liked ? c.likes_count - 1 : c.likes_count + 1 }
        : c
    ));
    await api.post(`/community/comments/${id}/like`).catch(() => {});
  }

  const myAv   = mediaUrl((user as unknown as { avatar?: string })?.avatar ?? '');
  const myName = user?.name ?? 'You';

  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 50, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
      {/* Backdrop */}
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.75)', backdropFilter: 'blur(6px)' }} onClick={onClose} />

      {/* Modal card */}
      <div style={{
        position: 'relative', zIndex: 1,
        width: '100%', maxWidth: 560,
        maxHeight: '92vh',
        background: 'var(--card)',
        borderRadius: 'var(--r12)',
        boxShadow: '0 12px 48px rgba(0,0,0,0.45)',
        display: 'flex', flexDirection: 'column',
        overflow: 'hidden',
      }}>

        {/* ── HEADER ── */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          padding: '14px 16px',
          borderBottom: '1px solid var(--border)',
          flexShrink: 0, position: 'relative',
        }}>
          <span style={{ fontSize: 17, fontWeight: 800, color: 'var(--t1)' }}>
            {postTitle ? `${postTitle}'s post` : 'Comments'}
          </span>
          <button onClick={onClose} style={{
            position: 'absolute', right: 12,
            width: 32, height: 32, borderRadius: '50%',
            background: 'var(--input)', border: 'none', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--t1)',
          }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.5} strokeLinecap="round">
              <path d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Scrollable body */}
        <div style={{ flex: 1, overflowY: 'auto', display: 'flex', flexDirection: 'column' }}>

          {/* ── POST IMAGE ── */}
          {postImage && (
            <div style={{ background: '#000', flexShrink: 0 }}>
              <img src={postImage} alt="post" style={{ width: '100%', maxHeight: 320, objectFit: 'cover', display: 'block' }} />
            </div>
          )}

          {/* ── ENGAGEMENT ROW ── */}
          <div style={{
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            padding: '10px 16px',
            borderBottom: '1px solid var(--border)',
            flexShrink: 0,
          }}>
            {/* Left: like icon + count · comment icon + count · share icon + count */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
              {likesCount > 0 && (
                <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 13, color: 'var(--t2)' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><path strokeLinecap="round" strokeLinejoin="round" d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3H14z"/><path strokeLinecap="round" strokeLinejoin="round" d="M7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg>
                  {likesCount.toLocaleString()}
                </span>
              )}
              <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 13, color: 'var(--t2)' }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><path strokeLinecap="round" strokeLinejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                {count.toLocaleString()}
              </span>
              {sharesCount > 0 && (
                <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 13, color: 'var(--t2)' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><path strokeLinecap="round" strokeLinejoin="round" d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
                  {sharesCount.toLocaleString()}
                </span>
              )}
            </div>
            {/* Right: reaction stack */}
            <div style={{ fontSize: 18, letterSpacing: -3 }}>👍❤️😮</div>
          </div>

          {/* ── COMMENTS LIST ── */}
          <div ref={listRef} style={{ flex: 1, padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 14 }}>
            {loading ? (
              <div style={{ display: 'flex', justifyContent: 'center', padding: '48px 0' }}>
                <div style={{ width: 28, height: 28, border: '3px solid var(--orange)', borderTopColor: 'transparent', borderRadius: '50%', animation: 'cmspin .7s linear infinite' }} />
              </div>
            ) : comments.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '48px 0', color: 'var(--t3)', fontSize: 14 }}>
                No comments yet. Be the first!
              </div>
            ) : (
              comments.map(c => <CommentRow key={c.id} comment={c} onLike={likeComment} />)
            )}
          </div>
        </div>

        {/* ── INPUT AREA ── */}
        <div style={{ borderTop: '1px solid var(--border)', padding: '10px 14px 14px', flexShrink: 0, background: 'var(--card)' }}>
          <form onSubmit={submit}>
            {/* Avatar + text field row */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              {/* Avatar */}
              <div style={{ width: 36, height: 36, borderRadius: '50%', overflow: 'hidden', background: 'var(--input)', flexShrink: 0 }}>
                {myAv
                  ? <img src={myAv} alt={myName} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  : <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: 14, color: '#fff', background: 'var(--orange)' }}>{myName[0]}</div>
                }
              </div>

              {/* Input */}
              <div style={{ flex: 1, display: 'flex', alignItems: 'center', background: 'var(--input)', borderRadius: 22, padding: '9px 14px', gap: 8 }}>
                <input
                  ref={inputRef}
                  type="text"
                  value={text}
                  onChange={e => setText(e.target.value)}
                  placeholder={`Comment as ${myName}…`}
                  style={{ flex: 1, background: 'transparent', border: 'none', outline: 'none', fontSize: 14, color: 'var(--t1)', minWidth: 0 }}
                />
                {text.trim() && (
                  <button type="submit" disabled={sending} style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 0, flexShrink: 0 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="var(--orange)">
                      <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                    </svg>
                  </button>
                )}
              </div>
            </div>

            {/* Icon row — indented to align with input */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 2, paddingLeft: 46, paddingTop: 6 }}>
              {[
                <svg key="e" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><circle cx="12" cy="12" r="10"/><path strokeLinecap="round" d="M8 13s1.5 2 4 2 4-2 4-2"/><circle cx="9" cy="9" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="9" r="1" fill="currentColor" stroke="none"/></svg>,
                <svg key="g" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><rect x="3" y="6" width="18" height="12" rx="2"/><text x="6.5" y="16" fontSize="6.5" fontWeight="800" fill="currentColor" stroke="none">GIF</text></svg>,
                <svg key="a" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><path strokeLinecap="round" strokeLinejoin="round" d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>,
                <svg key="i" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor" stroke="none"/><path strokeLinecap="round" strokeLinejoin="round" d="M21 15l-5-5L5 21"/></svg>,
                <svg key="c" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}><path strokeLinecap="round" strokeLinejoin="round" d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>,
              ].map((ico, i) => (
                <button key={i} type="button"
                  style={{ width: 34, height: 34, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--t3)', borderRadius: 8, transition: 'color 120ms' }}
                  onMouseEnter={e => (e.currentTarget.style.color = 'var(--orange)')}
                  onMouseLeave={e => (e.currentTarget.style.color = 'var(--t3)')}
                >{ico}</button>
              ))}
            </div>
          </form>
        </div>
      </div>

      <style>{`@keyframes cmspin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}

function CommentRow({ comment, onLike }: { comment: Comment; onLike: (id: number) => void }) {
  const name    = comment.user?.name ?? comment.user?.username ?? 'User';
  const av      = mediaUrl(comment.user?.avatar);
  const timeAgo = (() => {
    try { return formatDistanceToNow(new Date(comment.created_at), { addSuffix: true }); }
    catch { return ''; }
  })();

  return (
    <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
      {/* Avatar */}
      <div style={{ width: 36, height: 36, borderRadius: '50%', overflow: 'hidden', background: 'var(--input)', flexShrink: 0 }}>
        {av
          ? <img src={av} alt={name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
          : <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: 14, color: '#fff', background: 'var(--orange)' }}>{name[0]}</div>
        }
      </div>

      <div style={{ flex: 1, minWidth: 0 }}>
        {/* Name + time — OUTSIDE bubble */}
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, marginBottom: 4 }}>
          <span style={{ fontSize: 13, fontWeight: 800, color: 'var(--t1)' }}>{name}</span>
          <span style={{ fontSize: 11, color: 'var(--t3)' }}>{timeAgo}</span>
        </div>

        {/* Comment bubble */}
        <div style={{ background: 'var(--input)', borderRadius: '0 14px 14px 14px', padding: '8px 12px', display: 'inline-block', maxWidth: '100%' }}>
          <span style={{ fontSize: 14, color: 'var(--t1)', lineHeight: 1.45, wordBreak: 'break-word' }}>{comment.content}</span>
        </div>

        {/* Like · Reply */}
        <div style={{ display: 'flex', gap: 14, marginTop: 5, paddingLeft: 4 }}>
          <button
            onClick={() => onLike(comment.id)}
            style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 12, fontWeight: 800, color: comment.is_liked ? '#E41E1E' : 'var(--t3)', display: 'flex', alignItems: 'center', gap: 4 }}
          >
            {comment.is_liked ? '❤️' : '♡'} {comment.likes_count > 0 ? comment.likes_count : 'Like'}
          </button>
          <button style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 12, fontWeight: 800, color: 'var(--t3)' }}>
            Reply
          </button>
        </div>
      </div>
    </div>
  );
}
