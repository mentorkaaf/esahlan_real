'use client';
import { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';
import { useAuthStore } from '@/store/auth';

const NAV = [
  { href: '/feed',          label: 'Feed',    icon: HomeIcon },
  { href: '/explore',       label: 'Explore', icon: SearchIcon },
  { href: '/reels',         label: 'Reels',   icon: ReelIcon },
  { href: '/create',        label: 'Create',  icon: CreateIcon },
  { href: '/chat',          label: 'Chat',    icon: ChatIcon },
  { href: '/notifications', label: 'Notifs',  icon: BellIcon },
  { href: '/epay',          label: 'ePay',    icon: WalletIcon },
  { href: '/profile',       label: 'Profile', icon: ProfileIcon },
];

// Mobile nav shows only 5 key items
const MOBILE_NAV = [
  { href: '/feed',          label: 'Feed',    icon: HomeIcon },
  { href: '/explore',       label: 'Explore', icon: SearchIcon },
  { href: '/create',        label: 'Create',  icon: CreateIcon },
  { href: '/notifications', label: 'Notifs',  icon: BellIcon },
  { href: '/profile',       label: 'Profile', icon: ProfileIcon },
];

export default function MainLayout({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthStore();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!isAuthenticated) router.replace('/login');
  }, [isAuthenticated, router]);

  if (!isAuthenticated) return null;

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
      {/* Left sidebar — desktop */}
      <aside className="hidden md:flex flex-col w-64 border-r border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shrink-0">
        {/* Logo */}
        <div className="flex items-center gap-2.5 px-2 mb-8">
          <div className="w-9 h-9 bg-[#FF8A00] rounded-xl flex items-center justify-center shrink-0">
            <span className="text-white font-black text-lg">e</span>
          </div>
          <span className="font-black text-xl text-[#07003B] dark:text-white">eSahlan</span>
        </div>

        <nav className="flex flex-col gap-1">
          {NAV.map(({ href, label, icon: Icon }) => {
            const active = pathname.startsWith(href);
            return (
              <Link
                key={href}
                href={href}
                className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors ${
                  active
                    ? 'bg-[#FF8A00]/10 text-[#FF8A00]'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                }`}
              >
                <Icon className="w-5 h-5" />
                {label}
              </Link>
            );
          })}
        </nav>

        <div className="mt-auto">
          <LogoutButton />
        </div>
      </aside>

      {/* Main content */}
      <main className="flex-1 overflow-y-auto pb-16 md:pb-0">
        {children}
      </main>

      {/* Bottom nav — mobile (5 items only) */}
      <nav className="md:hidden fixed bottom-0 inset-x-0 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 flex z-50">
        {MOBILE_NAV.map(({ href, label, icon: Icon }) => {
          const active = pathname.startsWith(href);
          const isCreate = href === '/create';
          return (
            <Link
              key={href}
              href={href}
              className={`flex-1 flex flex-col items-center justify-center py-2 gap-0.5 text-[10px] font-semibold transition-colors ${
                isCreate ? '' : active ? 'text-[#FF8A00]' : 'text-gray-400'
              }`}
            >
              {isCreate ? (
                <div className="w-9 h-9 bg-[#FF8A00] rounded-xl flex items-center justify-center -mt-1">
                  <Icon className="w-5 h-5 text-white" />
                </div>
              ) : (
                <>
                  <Icon className="w-5 h-5" />
                  {label}
                </>
              )}
            </Link>
          );
        })}
      </nav>
    </div>
  );
}

function LogoutButton() {
  const { logout, user } = useAuthStore();
  return (
    <div className="border-t border-gray-100 dark:border-gray-800 pt-3">
      <div className="px-2 mb-2">
        <p className="text-xs font-bold text-gray-900 dark:text-white truncate">{user?.name ?? 'User'}</p>
        <p className="text-xs text-gray-400 truncate">{user?.phone}</p>
      </div>
      <button
        onClick={logout}
        className="w-full text-left px-3 py-2 text-xs font-semibold text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-colors"
      >
        Sign out
      </button>
    </div>
  );
}

// ── Icons ─────────────────────────────────────────────────────────────────────
function HomeIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
  );
}
function ReelIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
    </svg>
  );
}
function ChatIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  );
}
function WalletIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
    </svg>
  );
}
function ProfileIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
  );
}
function SearchIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
  );
}
function CreateIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
    </svg>
  );
}
function BellIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
  );
}
