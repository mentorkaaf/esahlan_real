'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { Chat } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { useRealtime } from '@/hooks/useRealtime';
import { useAuthStore } from '@/store/auth';

export default function ChatListPage() {
  const [chats, setChats] = useState<Chat[]>([]);
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const { user } = useAuthStore();

  async function load() {
    try {
      const res = await api.get<{ data: Chat[] }>('/community/chats');
      setChats(res.data);
    } catch {}
    setLoading(false);
  }

  useEffect(() => { load(); }, []);

  // Listen for inbox updates via Reverb
  useRealtime({
    channel: user ? `private-user.${user.id}` : '',
    enabled: !!user,
    events: {
      'chat.inbox_update': () => load(),
    },
  });

  if (loading) return (
    <div className="flex items-center justify-center h-full">
      <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto">
      <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 py-4 z-10">
        <h1 className="text-xl font-black text-gray-900 dark:text-white">Messages</h1>
      </div>

      {chats.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-24 text-center px-6">
          <div className="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
            <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
          <p className="font-bold text-gray-700 dark:text-gray-300">No messages yet</p>
          <p className="text-sm text-gray-400 mt-1">Start a conversation from someone&apos;s profile</p>
        </div>
      ) : (
        <div className="divide-y divide-gray-50 dark:divide-gray-800">
          {chats.map(chat => (
            <button
              key={chat.id}
              onClick={() => router.push(`/chat/${chat.id}`)}
              className="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors text-left"
            >
              <div className="relative shrink-0">
                <Avatar profile={chat.other_user} size={48} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between gap-2">
                  <p className="font-bold text-sm text-gray-900 dark:text-white truncate">
                    {chat.other_user.display_name}
                  </p>
                  {chat.last_message && (
                    <span className="text-[11px] text-gray-400 shrink-0">
                      {formatDistanceToNow(new Date(chat.last_message.created_at), { addSuffix: false })}
                    </span>
                  )}
                </div>
                <div className="flex items-center justify-between gap-2 mt-0.5">
                  <p className="text-xs text-gray-400 truncate">
                    {chat.last_message?.content ?? 'Media'}
                  </p>
                  {chat.unread_count > 0 && (
                    <span className="shrink-0 w-5 h-5 bg-[#FF8A00] rounded-full text-white text-[10px] font-bold flex items-center justify-center">
                      {chat.unread_count > 9 ? '9+' : chat.unread_count}
                    </span>
                  )}
                </div>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function Avatar({ profile, size = 40 }: { profile: { avatar?: string; display_name: string }; size?: number }) {
  const src = mediaUrl(profile.avatar);
  const s = `${size}px`;
  return (
    <div style={{ width: s, height: s }} className="rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0">
      {src
        ? <img src={src} alt={profile.display_name} className="w-full h-full object-cover" />
        : <div className="w-full h-full flex items-center justify-center text-sm font-bold text-gray-500">
            {profile.display_name[0]?.toUpperCase()}
          </div>
      }
    </div>
  );
}
