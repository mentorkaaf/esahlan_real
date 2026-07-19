'use client';
import { useState, useCallback } from 'react';
import { api } from '@/lib/api';
import { CommunityPost, FeedResponse } from '@/types';

export function useFeed() {
  const [posts, setPosts] = useState<CommunityPost[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (reset = false) => {
    if (loading) return;
    setLoading(true);
    setError(null);
    try {
      const p = reset ? 1 : page;
      const res = await api.get<FeedResponse>(`/community/feed?page=${p}&per_page=20`);
      setPosts(prev => reset ? res.data : [...prev, ...res.data]);
      setHasMore(res.meta.current_page < res.meta.last_page);
      setPage(p + 1);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to load feed');
    } finally {
      setLoading(false);
    }
  }, [loading, page]);

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
