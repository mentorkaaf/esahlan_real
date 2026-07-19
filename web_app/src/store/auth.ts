'use client';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { API_BASE, TOKEN_KEY } from '@/lib/constants';
import { User } from '@/types';

interface AuthState {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  loginWithPhone: (phone: string, pin: string) => Promise<void>;
  loginWithEmail: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

async function apiLogin(body: Record<string, string>): Promise<{ token: string; user: User }> {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
    credentials: 'include',
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json?.message ?? 'Login failed');
  const token = json.data?.token as string;
  // fetch /me for full user object
  const meRes = await fetch(`${API_BASE}/auth/me`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    credentials: 'include',
  });
  const me = await meRes.json();
  const user = (me.data ?? me) as User;
  return { token, user };
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      isAuthenticated: false,

      loginWithPhone: async (phone, pin) => {
        const { token, user } = await apiLogin({ phone, password: pin });
        localStorage.setItem(TOKEN_KEY, token);
        set({ token, user, isAuthenticated: true });
      },

      loginWithEmail: async (email, password) => {
        const { token, user } = await apiLogin({ email, password });
        localStorage.setItem(TOKEN_KEY, token);
        set({ token, user, isAuthenticated: true });
      },

      logout: async () => {
        const token = localStorage.getItem(TOKEN_KEY);
        if (token) {
          fetch(`${API_BASE}/auth/logout`, {
            method: 'POST',
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
          }).catch(() => {});
        }
        localStorage.removeItem(TOKEN_KEY);
        set({ token: null, user: null, isAuthenticated: false });
        window.location.href = '/login';
      },
    }),
    {
      name: 'esahlan-auth',
      partialize: (s) => ({ token: s.token, user: s.user, isAuthenticated: s.isAuthenticated }),
    },
  ),
);
