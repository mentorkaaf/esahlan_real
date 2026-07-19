'use client';
import { useEffect, useRef } from 'react';
import { REVERB, TOKEN_KEY } from '@/lib/constants';

interface RealtimeOptions {
  channel: string;
  events: Record<string, (data: unknown) => void>;
  enabled?: boolean;
}

export function useRealtime({ channel, events, enabled = true }: RealtimeOptions) {
  const echoRef = useRef<unknown>(null);

  useEffect(() => {
    if (!enabled || typeof window === 'undefined') return;
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) return;

    let cleanup: (() => void) | null = null;

    import('pusher-js').then(({ default: Pusher }) => {
      const pusher = new Pusher(REVERB.appKey, {
        wsHost: REVERB.host,
        wsPort: REVERB.port,
        wssPort: REVERB.port,
        forceTLS: REVERB.tls,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: REVERB.authUrl,
        auth: { headers: { Authorization: `Bearer ${token}` } },
        cluster: 'mt1',
      });

      echoRef.current = pusher;
      const ch = channel.startsWith('private-') || channel.startsWith('presence-')
        ? pusher.subscribe(channel)
        : pusher.subscribe(channel);

      for (const [event, handler] of Object.entries(events)) {
        ch.bind(event, handler);
      }

      cleanup = () => {
        pusher.unsubscribe(channel);
        pusher.disconnect();
      };
    });

    return () => { cleanup?.(); };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [channel, enabled]);
}
