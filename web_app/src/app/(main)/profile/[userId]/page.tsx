'use client';
import { useState, useEffect, useCallback } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { CommunityProfile, CommunityPost, PostMedia } from '@/types';
import { useAuthStore } from '@/store/auth';

interface ProfileResponse {
  data: CommunityProfile & { posts?: CommunityPost[] };
}

export default function ProfilePage() {
  const { userId } = useParams<{ userId: string }>();
  const [profile, setProfile] = useState<CommunityProfile | null>(null);
  const [posts, setPosts] = useState<CommunityPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [following, setFollowing] = useState(false);
  const { user: me } = useAuthStore();
  const router = useRouter();
  const isMe = String(me?.id) === userId;

  const load = useCallback(async () => {
    try {
      const [profRes, postsRes] = await Promise.all([
        api.get<ProfileResponse>(`/community/profile/${userId}`),
        api.get<{ data: CommunityPost[] }>(`/community/profile/${userId}/posts?per_page=30`),
      ]);
      setProfile(profRes.data);
      setFollowing(profRes.data.is_following);
      setPosts(postsRes.data);
    } catch {}
    setLoading(false);
  }, [userId]);

  useEffect(() => { load(); }, [load]);

  async function toggleFollow() {
    if (!profile) return;
    setFollowing(f => !f);
    try {
      await api.post(`/community/follow/${userId}`);
    } catch {
      setFollowing(f => !f);
    }
  }

  if (loading) return (
    <div className="flex items-center justify-center h-full">
      <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (!profile) return (
    <div className="flex items-center justify-center h-full text-gray-400">Profile not found</div>
  );

  // backend transformUser() uses `name`, profile-endpoint may use `display_name`
  const displayName = profile.name ?? displayName ?? 'Unknown';
  const username    = username ?? '';
  const avatar = mediaUrl(profile.avatar);
  const cover  = mediaUrl(profile.cover_photo);

  return (
    <div className="max-w-2xl mx-auto pb-10">
      {/* Cover */}
      <div className="relative h-40 bg-gradient-to-br from-[#07003B] to-[#1E3A6E] overflow-hidden">
        {cover && <img src={cover} alt="" className="w-full h-full object-cover" />}
        <button onClick={() => router.back()} className="absolute top-4 left-4 w-8 h-8 bg-black/30 rounded-full flex items-center justify-center text-white">
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
      </div>

      {/* Avatar + actions */}
      <div className="px-4 -mt-12 flex items-end justify-between mb-4">
        <div className="w-24 h-24 rounded-full overflow-hidden border-4 border-white dark:border-gray-950 bg-gray-200 dark:bg-gray-700 shrink-0">
          {avatar
            ? <img src={avatar} alt={displayName} className="w-full h-full object-cover" />
            : <div className="w-full h-full flex items-center justify-center text-3xl font-black text-gray-400">{displayName[0]}</div>
          }
        </div>
        <div className="flex gap-2 mt-14">
          {isMe ? (
            <button className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
              Edit Profile
            </button>
          ) : (
            <>
              <button
                onClick={toggleFollow}
                className={`px-5 py-2 rounded-xl text-sm font-bold transition-colors ${
                  following
                    ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200'
                    : 'bg-[#FF8A00] text-white'
                }`}
              >
                {following ? 'Following' : 'Follow'}
              </button>
              <button
                onClick={() => router.push('/chat')}
                className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
              >
                Message
              </button>
            </>
          )}
        </div>
      </div>

      {/* Info */}
      <div className="px-4 mb-5">
        <div className="flex items-center gap-2">
          <h1 className="text-xl font-black text-gray-900 dark:text-white">{displayName}</h1>
          {profile.is_verified && (
            <svg className="w-5 h-5 text-[#FF8A00]" viewBox="0 0 24 24" fill="currentColor">
              <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          )}
        </div>
        <p className="text-sm text-gray-400">@{username}</p>
        {profile.bio && <p className="text-sm text-gray-700 dark:text-gray-300 mt-2 leading-relaxed">{profile.bio}</p>}

        {/* Stats */}
        <div className="flex gap-6 mt-4">
          <Stat label="Posts" value={profile.posts_count} />
          <Stat label="Followers" value={profile.followers_count} />
          <Stat label="Following" value={profile.following_count} />
        </div>
      </div>

      {/* Posts grid */}
      {posts.length === 0 ? (
        <div className="text-center py-16 text-gray-400 text-sm">No posts yet</div>
      ) : (
        <div className="grid grid-cols-3 gap-0.5">
          {posts.map(post => (
            <PostThumb key={post.id} post={post} />
          ))}
        </div>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="text-center">
      <p className="font-black text-gray-900 dark:text-white text-lg leading-tight">{fmtN(value)}</p>
      <p className="text-xs text-gray-400">{label}</p>
    </div>
  );
}

function PostThumb({ post }: { post: CommunityPost }) {
  const media: PostMedia | undefined = (post.media ?? [])[0];
  const thumb = media?.thumbnail ?? media?.thumbnail_url ?? media?.url;
  return (
    <div className="aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800 relative">
      {thumb
        ? <img src={mediaUrl(thumb)} alt="" className="w-full h-full object-cover" />
        : <div className="w-full h-full flex items-center justify-center">
            <svg className="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
      }
      {media?.type === 'video' && (
        <div className="absolute top-2 right-2">
          <svg className="w-4 h-4 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
      )}
      {(post.media ?? []).length > 1 && (
        <div className="absolute top-2 right-2">
          <svg className="w-4 h-4 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24">
            <path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z" />
          </svg>
        </div>
      )}
    </div>
  );
}

function fmtN(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${(n / 1000).toFixed(1)}K`;
  return String(n);
}
