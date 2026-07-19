'use client';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { api } from '@/lib/api';
import { TOKEN_KEY, USER_KEY } from '@/lib/constants';
import { User } from '@/types';

interface AuthState {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  // actions
  sendOtp: (phone: string) => Promise<void>;
  verifyOtp: (phone: string, otp: string) => Promise<void>;
  logout: () => void;
  loadFromStorage: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      isAuthenticated: false,

      sendOtp: async (phone: string) => {
        await api.post('/auth/send-otp', { phone });
      },

      verifyOtp: async (phone: string, otp: string) => {
        const res = await api.post<{ token: string; user: User }>('/auth/verify-otp', { phone, otp });
        localStorage.setItem(TOKEN_KEY, res.token);
        localStorage.setItem(USER_KEY, JSON.stringify(res.user));
        set({ token: res.token, user: res.user, isAuthenticated: true });
      },

      logout: () => {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        set({ token: null, user: null, isAuthenticated: false });
        window.location.href = '/login';
      },

      loadFromStorage: () => {
        const token = localStorage.getItem(TOKEN_KEY);
        const raw = localStorage.getItem(USER_KEY);
        if (token && raw) {
          try {
            const user = JSON.parse(raw) as User;
            set({ token, user, isAuthenticated: true });
          } catch {}
        }
      },
    }),
    {
      name: 'esahlan-auth',
      partialize: (s) => ({ token: s.token, user: s.user, isAuthenticated: s.isAuthenticated }),
    },
  ),
);
