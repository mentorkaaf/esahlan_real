'use client';
import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { CommunityProfile, CommunityPost, PostMedia } from '@/types';
import { useAuthStore } from '@/store/auth';

interface ProfileResponse {
  data: CommunityProfile;
}

export default function ProfilePage() {
  const { userId } = useParams<{ userId: string }>();
  const [profile, setProfile] = useState<CommunityProfile | null>(null);
  const [posts, setPosts] = useState<CommunityPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [following, setFollowing] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [uploadingCover, setUploadingCover] = useState(false);
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(null);
  const avatarInputRef = useRef<HTMLInputElement>(null);
  const coverInputRef = useRef<HTMLInputElement>(null);
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
      setFollowing(profRes.data.is_following ?? false);
      setPosts(Array.isArray(postsRes.data) ? postsRes.data : []);
    } catch {}
    setLoading(false);
  }, [userId]);

  useEffect(() => { load(); }, [load]);

  async function toggleFollow() {
    if (!profile) return;
    setFollowing(f => !f);
    try { await api.post(`/community/follow/${userId}`); }
    catch { setFollowing(f => !f); }
  }

  async function uploadAvatar(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setAvatarPreview(URL.createObjectURL(file));
    setUploadingAvatar(true);
    try {
      const form = new FormData();
      form.append('avatar', file);
      await api.postForm('/community/profile/avatar', form);
    } catch {}
    setUploadingAvatar(false);
  }

  async function uploadCover(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setCoverPreview(URL.createObjectURL(file));
    setUploadingCover(true);
    try {
      const form = new FormData();
      form.append('cover_photo', file);
      await api.postForm('/community/profile', form);
    } catch {}
    setUploadingCover(false);
  }

  if (loading) return (
    <div className="flex items-center justify-center h-full">
      <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (!profile) return (
    <div className="flex items-center justify-center h-full text-gray-400">Profile not found</div>
  );

  const displayName = profile.name ?? profile.display_name ?? 'Unknown';
  const username    = profile.username ?? '';
  const avatarSrc   = avatarPreview ?? mediaUrl(profile.avatar) ?? '';
  const coverSrc    = coverPreview  ?? mediaUrl(profile.cover_photo) ?? '';

  return (
    <div className="max-w-2xl mx-auto pb-10">
      {/* Cover photo */}
      <div className="relative h-44 bg-gradient-to-br from-[#07003B] to-[#1E3A6E] overflow-hidden group">
        {coverSrc && <img src={coverSrc} alt="" className="w-full h-full object-cover" />}

        {/* Back button */}
        <button
          onClick={() => router.back()}
          className="absolute top-4 left-4 w-8 h-8 bg-black/40 rounded-full flex items-center justify-center text-white z-10"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        {/* Cover edit button (only me) */}
        {isMe && (
          <>
            <button
              onClick={() => coverInputRef.current?.click()}
              disabled={uploadingCover}
              className="absolute bottom-3 right-3 flex items-center gap-1.5 bg-black/50 hover:bg-black/70 text-white text-xs font-bold px-3 py-1.5 rounded-full backdrop-blur transition-colors"
            >
              {uploadingCover ? (
                <div className="w-3 h-3 border border-white/50 border-t-white rounded-full animate-spin" />
              ) : (
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              )}
              Change Cover
            </button>
            <input ref={coverInputRef} type="file" accept="image/*" className="hidden" onChange={uploadCover} />
          </>
        )}
      </div>

      {/* Avatar + action buttons row */}
      <div className="px-4 -mt-14 flex items-end justify-between mb-4">
        {/* Avatar */}
        <div className="relative shrink-0">
          <div className="w-24 h-24 rounded-full overflow-hidden border-4 border-white dark:border-gray-950 bg-gray-200 dark:bg-gray-700">
            {avatarSrc
              ? <img src={avatarSrc} alt={displayName} className="w-full h-full object-cover" />
              : <div className="w-full h-full flex items-center justify-center text-3xl font-black text-gray-400">{displayName[0] ?? '?'}</div>
            }
            {uploadingAvatar && (
              <div className="absolute inset-0 bg-black/40 flex items-center justify-center rounded-full">
                <div className="w-5 h-5 border-2 border-white/40 border-t-white rounded-full animate-spin" />
              </div>
            )}
          </div>
          {/* Camera button on avatar (only me) */}
          {isMe && (
            <>
              <button
                onClick={() => avatarInputRef.current?.click()}
                disabled={uploadingAvatar}
                className="absolute bottom-0 right-0 w-7 h-7 bg-[#FF8A00] border-2 border-white dark:border-gray-950 rounded-full flex items-center justify-center"
              >
                <svg className="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </button>
              <input ref={avatarInputRef} type="file" accept="image/*" className="hidden" onChange={uploadAvatar} />
            </>
          )}
        </div>

        {/* Actions */}
        <div className="flex gap-2 mt-16">
          {isMe ? (
            <button className="px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
              Edit Profile
            </button>
          ) : (
            <>
              <button
                onClick={toggleFollow}
                className={`px-5 py-2 rounded-xl text-sm font-bold transition-colors ${
                  following ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200' : 'bg-[#FF8A00] text-white'
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
        {username && <p className="text-sm text-gray-400">@{username}</p>}
        {profile.bio && <p className="text-sm text-gray-700 dark:text-gray-300 mt-2 leading-relaxed">{profile.bio}</p>}

        <div className="flex gap-6 mt-4">
          <Stat label="Posts" value={profile.posts_count ?? 0} />
          <Stat label="Followers" value={profile.followers_count ?? 0} />
          <Stat label="Following" value={profile.following_count ?? 0} />
        </div>
      </div>

      {/* Posts grid */}
      {posts.length === 0 ? (
        <div className="text-center py-16 text-gray-400 text-sm">No posts yet</div>
      ) : (
        <div className="grid grid-cols-3 gap-0.5">
          {posts.map(post => <PostThumb key={post.id} post={post} />)}
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
    <div className="aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800 relative group cursor-pointer">
      {thumb
        ? <img src={mediaUrl(thumb)} alt="" className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" loading="lazy" />
        : <div className="w-full h-full flex items-center justify-center">
            <svg className="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
      }
      {media?.type === 'video' && (
        <div className="absolute top-2 right-2">
          <svg className="w-4 h-4 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
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
  return String(n ?? 0);
}
