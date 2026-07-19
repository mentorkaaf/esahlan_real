'use client';
import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { Message, Chat } from '@/types';
import { useAuthStore } from '@/store/auth';
import { useRealtime } from '@/hooks/useRealtime';
import { format } from 'date-fns';

export default function ChatPage() {
  const { chatId } = useParams<{ chatId: string }>();
  const [chat, setChat] = useState<Chat | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [text, setText] = useState('');
  const [sending, setSending] = useState(false);
  const [isTyping, setIsTyping] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);
  const typingTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const { user } = useAuthStore();
  const router = useRouter();

  const load = useCallback(async () => {
    try {
      const [chatsRes, msgsRes] = await Promise.all([
        api.get<{ data: Chat[] }>('/community/chats'),
        api.get<{ data: Message[] }>(`/community/chats/${chatId}/messages`),
      ]);
      const found = chatsRes.data.find(c => String(c.id) === chatId);
      if (found) setChat(found);
      setMessages(msgsRes.data.reverse());
      // Mark read
      api.post(`/community/chats/${chatId}/read`).catch(() => {});
    } catch {}
  }, [chatId]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Real-time new messages
  useRealtime({
    channel: `private-chat.${chatId}`,
    enabled: !!chatId,
    events: {
      'new_message': (data: unknown) => {
        const msg = data as Message;
        setMessages(prev => [...prev, msg]);
        api.post(`/community/chats/${chatId}/read`).catch(() => {});
      },
      'typing': (data: unknown) => {
        const d = data as { user_id: number };
        if (d.user_id !== user?.id) {
          setIsTyping(true);
          if (typingTimer.current) clearTimeout(typingTimer.current);
          typingTimer.current = setTimeout(() => setIsTyping(false), 3000);
        }
      },
    },
  });

  async function sendTyping() {
    api.post(`/community/chats/${chatId}/typing`).catch(() => {});
  }

  async function sendMessage(e: React.FormEvent) {
    e.preventDefault();
    if (!text.trim() || sending) return;
    const content = text.trim();
    setText('');
    setSending(true);
    // Optimistic
    const optimistic: Message = {
      id: Date.now(),
      chat_id: Number(chatId),
      sender_id: user?.id ?? 0,
      content,
      created_at: new Date().toISOString(),
    };
    setMessages(prev => [...prev, optimistic]);
    try {
      await api.post(`/community/chats/${chatId}/messages`, { content });
    } catch {
      setMessages(prev => prev.filter(m => m.id !== optimistic.id));
      setText(content);
    } finally {
      setSending(false);
    }
  }

  const otherUser = chat?.other_user;

  return (
    <div className="flex flex-col h-screen md:h-full max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shrink-0">
        <button onClick={() => router.back()} className="text-gray-400 hover:text-gray-600 mr-1">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        {otherUser && (
          <>
            <div className="w-9 h-9 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0">
              {mediaUrl(otherUser.avatar)
                ? <img src={mediaUrl(otherUser.avatar)} alt={otherUser.display_name} className="w-full h-full object-cover" />
                : <div className="w-full h-full flex items-center justify-center text-sm font-bold text-gray-500">{(otherUser.display_name ?? otherUser.name ?? '?')[0]}</div>
              }
            </div>
            <div>
              <p className="font-bold text-sm text-gray-900 dark:text-white">{otherUser.display_name}</p>
              <p className="text-xs text-gray-400">@{otherUser.username}</p>
            </div>
          </>
        )}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        {messages.map(msg => {
          const mine = msg.sender_id === user?.id;
          return (
            <div key={msg.id} className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
              <div className={`max-w-[75%] px-3.5 py-2.5 rounded-2xl text-sm ${
                mine
                  ? 'bg-[#07003B] text-white rounded-br-sm'
                  : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-bl-sm'
              }`}>
                {msg.content && <p className="leading-relaxed">{msg.content}</p>}
                {msg.media_url && (
                  <img src={mediaUrl(msg.media_url)} alt="" className="rounded-xl max-w-full mt-1" />
                )}
                <p className={`text-[10px] mt-1 ${mine ? 'text-white/50' : 'text-gray-400'} text-right`}>
                  {format(new Date(msg.created_at), 'HH:mm')}
                </p>
              </div>
            </div>
          );
        })}
        {isTyping && (
          <div className="flex justify-start">
            <div className="bg-gray-100 dark:bg-gray-800 px-4 py-3 rounded-2xl rounded-bl-sm flex gap-1">
              {[0,1,2].map(i => (
                <div key={i} className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: `${i * 0.15}s` }} />
              ))}
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <form onSubmit={sendMessage} className="flex items-center gap-2 px-4 py-3 border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shrink-0">
        <input
          type="text"
          value={text}
          onChange={e => { setText(e.target.value); sendTyping(); }}
          placeholder="Message…"
          className="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-full text-sm focus:outline-none text-gray-900 dark:text-white placeholder-gray-400"
        />
        <button
          type="submit"
          disabled={!text.trim() || sending}
          className="w-9 h-9 bg-[#FF8A00] disabled:opacity-40 rounded-full flex items-center justify-center shrink-0 transition-opacity"
        >
          <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
        </button>
      </form>
    </div>
  );
}
