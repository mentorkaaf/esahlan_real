'use client';
import { useState, useEffect, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { CommunityPost, CommunityProfile } from '@/types';

type Tab = 'posts' | 'people';

export default function ExplorePage() {
  const [tab, setTab] = useState<Tab>('posts');
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [posts, setPosts] = useState<CommunityPost[]>([]);
  const [people, setPeople] = useState<CommunityProfile[]>([]);
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const router = useRouter();

  // Load trending posts on mount
  useEffect(() => {
    setLoading(true);
    api.get<{ data: CommunityPost[] }>('/community/feed?page=1&per_page=30&type=trending')
      .then(res => setPosts(Array.isArray(res.data) ? res.data : []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  // Debounce search
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => setDebouncedQuery(query), 400);
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, [query]);

  const search = useCallback(async (q: string) => {
    if (!q.trim()) return;
    setLoading(true);
    try {
      const [postsRes, peopleRes] = await Promise.all([
        api.get<{ data: CommunityPost[] }>(`/community/search?q=${encodeURIComponent(q)}&type=posts`),
        api.get<{ data: CommunityProfile[] }>(`/community/search?q=${encodeURIComponent(q)}&type=users`),
      ]);
      setPosts(Array.isArray(postsRes.data) ? postsRes.data : []);
      setPeople(Array.isArray(peopleRes.data) ? peopleRes.data : []);
    } catch {}
    setLoading(false);
  }, []);

  useEffect(() => {
    if (debouncedQuery) search(debouncedQuery);
  }, [debouncedQuery, search]);

  const isSearching = !!debouncedQuery;

  return (
    <div className="max-w-2xl mx-auto pb-10">
      {/* Search bar */}
      <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 py-3 z-10">
        <div className="relative">
          <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            type="text"
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Search posts, people, hashtags…"
            className="w-full pl-9 pr-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-xl text-sm focus:outline-none text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#FF8A00]/30"
          />
          {query && (
            <button onClick={() => { setQuery(''); setDebouncedQuery(''); }} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          )}
        </div>

        {/* Tabs (only when searching) */}
        {isSearching && (
          <div className="flex gap-1 mt-2">
            {(['posts', 'people'] as Tab[]).map(t => (
              <button
                key={t}
                onClick={() => setTab(t)}
                className={`px-4 py-1.5 rounded-full text-sm font-bold capitalize transition-colors ${
                  tab === t ? 'bg-[#FF8A00] text-white' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                }`}
              >
                {t}
              </button>
            ))}
          </div>
        )}
      </div>

      {loading ? (
        <div className="flex justify-center py-12">
          <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
        </div>
      ) : isSearching && tab === 'people' ? (
        <div className="divide-y divide-gray-50 dark:divide-gray-800">
          {people.length === 0
            ? <div className="text-center py-16 text-gray-400 text-sm">No people found</div>
            : people.map(p => <PersonRow key={p.id} profile={p} onClick={() => router.push(`/profile/${p.id}`)} />)
          }
        </div>
      ) : (
        <>
          {!isSearching && (
            <div className="px-4 pt-4 pb-2">
              <h2 className="text-sm font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trending</h2>
            </div>
          )}
          {posts.length === 0
            ? <div className="text-center py-16 text-gray-400 text-sm">No posts found</div>
            : <PostGrid posts={posts} />
          }
        </>
      )}
    </div>
  );
}

function PostGrid({ posts }: { posts: CommunityPost[] }) {
  return (
    <div className="grid grid-cols-3 gap-0.5">
      {posts.map(post => {
        const media = (post.media ?? [])[0];
        const thumb = media?.thumbnail ?? media?.thumbnail_url ?? media?.url;
        return (
          <div key={post.id} className="aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800 relative cursor-pointer group">
            {thumb
              ? <img src={mediaUrl(thumb)} alt="" className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" loading="lazy" />
              : <div className="w-full h-full flex items-center justify-center">
                  <svg className="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
            }
            {media?.type === 'video' && (
              <div className="absolute top-1.5 right-1.5 text-white drop-shadow">
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
              </div>
            )}
            {/* Hover overlay */}
            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
              <div className="flex gap-3 text-white text-xs font-bold">
                <span>♥ {post.likes_count ?? 0}</span>
                <span>💬 {post.comments_count ?? 0}</span>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function PersonRow({ profile, onClick }: { profile: CommunityProfile; onClick: () => void }) {
  const name = profile.name ?? profile.display_name ?? 'User';
  const [following, setFollowing] = useState(profile.is_following);

  async function toggleFollow(e: React.MouseEvent) {
    e.stopPropagation();
    setFollowing(f => !f);
    await api.post(`/community/follow/${profile.id}`).catch(() => setFollowing(f => !f));
  }

  return (
    <div onClick={onClick} className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
      <div className="w-11 h-11 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0">
        {mediaUrl(profile.avatar)
          ? <img src={mediaUrl(profile.avatar)} alt={name} className="w-full h-full object-cover" />
          : <div className="w-full h-full flex items-center justify-center font-black text-gray-500">{name[0]}</div>
        }
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-bold text-sm text-gray-900 dark:text-white truncate">{name}</p>
        <p className="text-xs text-gray-400">@{profile.username} · {fmtN(profile.followers_count ?? 0)} followers</p>
      </div>
      <button
        onClick={toggleFollow}
        className={`px-3 py-1.5 rounded-full text-xs font-bold shrink-0 transition-colors ${
          following ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200' : 'bg-[#FF8A00] text-white'
        }`}
      >
        {following ? 'Following' : 'Follow'}
      </button>
    </div>
  );
}

function fmtN(n: number) {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${(n / 1000).toFixed(1)}K`;
  return String(n);
}
