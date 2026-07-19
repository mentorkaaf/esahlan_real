'use client';
import { useState, useCallback, useRef } from 'react';
import { api } from '@/lib/api';
import { CommunityPost, FeedResponse } from '@/types';

export function useFeed() {
  const [posts, setPosts] = useState<CommunityPost[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const loadingRef = useRef(false);
  const pageRef = useRef(1);

  const load = useCallback(async (reset = false) => {
    if (loadingRef.current) return;
    loadingRef.current = true;
    setLoading(true);
    setError(null);
    try {
      const p = reset ? 1 : pageRef.current;
      const res = await api.get<FeedResponse>(`/community/feed?page=${p}&per_page=20`);
      const items: CommunityPost[] = Array.isArray(res.data) ? res.data : [];
      setPosts(prev => reset ? items : [...prev, ...items]);
      const meta = res.meta ?? { current_page: p, last_page: 1, total: items.length };
      setHasMore(meta.current_page < meta.last_page);
      pageRef.current = p + 1;
      setPage(p + 1);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to load feed');
    } finally {
      loadingRef.current = false;
      setLoading(false);
    }
  }, []);

  const refresh = useCallback(() => {
    setPage(1);
    setHasMore(true);
    load(true);
  }, [load]);

  const likePost = useCallback(async (postId: number) => {
    setPosts(prev => prev.map(p =>
      p.id === postId
        ? { ...p, is_liked: !p.is_liked, likes_count: p.is_liked ? p.likes_count - 1 : p.likes_count + 1 }
        : p
    ));
    try {
      await api.post(`/community/posts/${postId}/like`);
    } catch {
      // revert optimistic update
      setPosts(prev => prev.map(p =>
        p.id === postId
          ? { ...p, is_liked: !p.is_liked, likes_count: p.is_liked ? p.likes_count - 1 : p.likes_count + 1 }
          : p
      ));
    }
  }, []);

  return { posts, loading, error, hasMore, load, refresh, likePost };
}
