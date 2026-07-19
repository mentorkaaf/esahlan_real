'use client';
import { useState, useEffect } from 'react';
import { api, mediaUrl } from '@/lib/api';
import { Notification } from '@/types';
import { formatDistanceToNow } from 'date-fns';

export default function NotificationsPage() {
  const [notifs, setNotifs] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get<{ data: Notification[] }>('/community/notifications')
      .then(res => setNotifs(Array.isArray(res.data) ? res.data : []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  async function markAllRead() {
    await api.post('/community/notifications/read-all').catch(() => {});
    setNotifs(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })));
  }

  const unread = notifs.filter(n => !n.read_at).length;

  if (loading) return (
    <div className="flex items-center justify-center h-full">
      <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 py-4 z-10 flex items-center justify-between">
        <h1 className="text-xl font-black text-gray-900 dark:text-white">
          Notifications {unread > 0 && <span className="ml-2 text-sm bg-[#FF8A00] text-white px-2 py-0.5 rounded-full">{unread}</span>}
        </h1>
        {unread > 0 && (
          <button onClick={markAllRead} className="text-xs text-[#FF8A00] font-bold">Mark all read</button>
        )}
      </div>

      {notifs.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-24 text-center px-6">
          <div className="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
            <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </div>
          <p className="font-bold text-gray-700 dark:text-gray-300">No notifications yet</p>
        </div>
      ) : (
        <div className="divide-y divide-gray-50 dark:divide-gray-800">
          {notifs.map(n => (
            <NotifRow key={n.id} notif={n} />
          ))}
        </div>
      )}
    </div>
  );
}

function NotifRow({ notif }: { notif: Notification }) {
  const read = !!notif.read_at;
  const timeAgo = notif.created_at ? (() => {
    const d = new Date(notif.created_at);
    return isNaN(d.getTime()) ? '' : formatDistanceToNow(d, { addSuffix: true });
  })() : '';

  const avatar = (notif as unknown as Record<string, string>)['actor_avatar'];
  const actorName = (notif as unknown as Record<string, string>)['actor_name'];

  return (
    <div className={`flex items-start gap-3 px-4 py-3.5 ${read ? '' : 'bg-[#FF8A00]/5 dark:bg-[#FF8A00]/5'}`}>
      {/* Icon or avatar */}
      <div className="shrink-0 mt-0.5">
        {avatar ? (
          <div className="w-10 h-10 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
            <img src={mediaUrl(avatar)} alt="" className="w-full h-full object-cover" />
          </div>
        ) : (
          <div className={`w-10 h-10 rounded-full flex items-center justify-center ${notifColor(notif.type)}`}>
            <NotifIcon type={notif.type} />
          </div>
        )}
      </div>

      <div className="flex-1 min-w-0">
        <p className={`text-sm leading-snug ${read ? 'text-gray-600 dark:text-gray-400' : 'text-gray-900 dark:text-white font-medium'}`}>
          {actorName && <span className="font-bold">{actorName} </span>}
          {notif.body}
        </p>
        {timeAgo && <p className="text-xs text-gray-400 mt-0.5">{timeAgo}</p>}
      </div>

      {!read && <div className="w-2 h-2 bg-[#FF8A00] rounded-full mt-1.5 shrink-0" />}
    </div>
  );
}

function notifColor(type: string) {
  if (type.includes('like'))    return 'bg-red-100 dark:bg-red-900/30 text-red-500';
  if (type.includes('comment')) return 'bg-blue-100 dark:bg-blue-900/30 text-blue-500';
  if (type.includes('follow'))  return 'bg-purple-100 dark:bg-purple-900/30 text-purple-500';
  if (type.includes('share'))   return 'bg-green-100 dark:bg-green-900/30 text-green-500';
  return 'bg-gray-100 dark:bg-gray-800 text-gray-500';
}

function NotifIcon({ type }: { type: string }) {
  if (type.includes('like')) return (
    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
      <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
    </svg>
  );
  if (type.includes('comment')) return (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  );
  if (type.includes('follow')) return (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
  );
  return (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
  );
}
