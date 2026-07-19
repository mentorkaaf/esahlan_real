'use client';
import { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';
import { useAuthStore } from '@/store/auth';
import { mediaUrl } from '@/lib/api';
import RightSidebar from '@/components/RightSidebar';

// ── Dark-mode hook — NEVER follows system preference ──────────────────────────
function useDarkMode() {
  const [dark, setDark] = useState(false);
  useEffect(() => {
    const on = localStorage.getItem('theme') === 'dark';
    setDark(on);
    document.documentElement.classList.toggle('dark', on);
  }, []);
  const toggle = () =>
    setDark(d => {
      const next = !d;
      document.documentElement.classList.toggle('dark', next);
      localStorage.setItem('theme', next ? 'dark' : 'light');
      return next;
    });
  return { dark, toggle };
}

const RIGHT_ROUTES = ['/feed', '/explore', '/reels'];

export default function MainLayout({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, user } = useAuthStore();
  const router = useRouter();
  const pathname = usePathname();
  const { dark, toggle } = useDarkMode();
  const showRight = RIGHT_ROUTES.some(r => pathname.startsWith(r));

  // Wait for Zustand to rehydrate from localStorage before checking auth.
  // Zustand's persist effect runs before this one, so by the time `ready`
  // becomes true, isAuthenticated already reflects the persisted value.
  const [ready, setReady] = useState(false);
  useEffect(() => { setReady(true); }, []);
  useEffect(() => { if (ready && !isAuthenticated) router.replace('/login'); }, [ready, isAuthenticated, router]);
  if (!ready || !isAuthenticated) return null;

  const avatar = mediaUrl((user as unknown as { avatar?: string })?.avatar ?? '');
  const name   = user?.name ?? 'User';
  const uid    = user?.id;

  function active(href: string) {
    return href === '/feed' ? pathname === '/feed' : pathname.startsWith(href);
  }

  // Outer max-width shifts so the feed column always stays ~680 px
  const outerMax = showRight ? 1260 : 940;

  return (
    <div style={{ minHeight: '100vh', background: 'var(--bg)' }}>

      {/* ════════════════════════════════════════
          HEADER
      ════════════════════════════════════════ */}
      <header
        className="glass fixed top-0 left-0 right-0 z-50"
        style={{ height: 'var(--header-h)', borderBottom: '1px solid var(--border)' }}
      >
        <div
          style={{
            height: '100%',
            maxWidth: outerMax,
            margin: '0 auto',
            width: '100%',
            display: 'grid',
            gridTemplateColumns: '260px 1fr auto',
            alignItems: 'center',
            padding: '0 24px',
            gap: 16,
          }}
        >
          {/* ── COL 1: Logo ─────────────────────────────── */}
          <Link href="/feed" style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' }}>
            <div
              style={{
                width: 36, height: 36, borderRadius: 10, flexShrink: 0,
                background: 'linear-gradient(140deg,#07003B,#1A0099)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}
            >
              <span style={{ color: '#fff', fontWeight: 900, fontSize: 18, lineHeight: 1 }}>e</span>
            </div>
            <span style={{ color: 'var(--navy)', fontWeight: 800, fontSize: 18, letterSpacing: '-0.3px' }}>
              eSahlan
            </span>
          </Link>

          {/* ── COL 2: Search (fills all remaining space) ── */}
          <div style={{ width: '100%', padding: '0 16px' }}>
            <label
              style={{
                display: 'flex', alignItems: 'center', gap: 12,
                width: '100%', height: 40, borderRadius: 'var(--pill)',
                background: 'var(--input)', border: '1.5px solid transparent',
                padding: '0 16px', cursor: 'text',
              }}
            >
              <svg style={{ color: 'var(--t3)', flexShrink: 0 }} className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0015.803 15.803z" />
              </svg>
              <input
                placeholder="Search eSahlan…"
                onKeyDown={e => {
                  const v = (e.target as HTMLInputElement).value;
                  if (e.key === 'Enter' && v.trim()) router.push(`/explore?q=${encodeURIComponent(v.trim())}`);
                }}
                style={{ flex: 1, background: 'transparent', outline: 'none', fontSize: 14, color: 'var(--t1)', minWidth: 0 }}
              />
            </label>
          </div>

          {/* Actions */}
          <div className="flex items-center" style={{ gap: 4 }}>
            {/* Create */}
            <Link
              href="/create"
              className="flex items-center gap-2"
              style={{
                height: 38, padding: '0 16px', borderRadius: 'var(--pill)',
                background: 'var(--orange)', color: '#fff',
                fontWeight: 700, fontSize: 14, boxShadow: '0 2px 8px rgba(255,138,0,0.32)',
              }}
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span className="hidden sm:inline">Create</span>
            </Link>

            {/* Icon buttons */}
            {([
              { href: '/chat',          badge: 5,  title: 'Messages',      ico: <IcoMsg   className="w-5 h-5" /> },
              { href: '/notifications', badge: 12, title: 'Notifications', ico: <IcoBell  className="w-5 h-5" /> },
              { href: '/shop',          badge: 2,  title: 'Cart',          ico: <IcoBag   className="w-5 h-5" /> },
            ] as const).map(b => (
              <Link
                key={b.href} href={b.href} title={b.title}
                className="relative flex items-center justify-center"
                style={{ width: 40, height: 40, borderRadius: 'var(--r4)', color: 'var(--t2)' }}
                onMouseEnter={e => (e.currentTarget.style.background = 'var(--input)')}
                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
              >
                {b.ico}
                {b.badge > 0 && (
                  <span
                    className="absolute top-0.5 right-0.5 flex items-center justify-center tabular"
                    style={{
                      minWidth: 17, height: 17, borderRadius: 'var(--pill)',
                      background: '#E41E1E', color: '#fff', fontSize: 10, fontWeight: 800, padding: '0 4px',
                    }}
                  >
                    {b.badge}
                  </span>
                )}
              </Link>
            ))}

            {/* Wallet */}
            <Link
              href="/epay"
              className="hidden md:flex items-center gap-2"
              style={{ height: 38, padding: '0 12px', borderRadius: 'var(--r4)', color: 'var(--t2)' }}
              onMouseEnter={e => (e.currentTarget.style.background = 'var(--input)')}
              onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
            >
              <div className="flex items-center justify-center" style={{ width: 26, height: 26, borderRadius: 'var(--r2)', background: 'rgba(7,0,59,0.07)' }}>
                <IcoWallet className="w-3.5 h-3.5" style={{ color: 'var(--navy)' }} />
              </div>
              <span className="hidden lg:block tabular" style={{ fontWeight: 700, fontSize: 14, color: 'var(--t1)' }}>$125.50</span>
            </Link>

            {/* Profile */}
            <Link
              href={`/profile/${uid}`}
              className="flex items-center gap-2 pl-2 pr-3"
              style={{ height: 38, borderRadius: 'var(--r4)' }}
              onMouseEnter={e => (e.currentTarget.style.background = 'var(--input)')}
              onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
            >
              <div
                className="flex items-center justify-center overflow-hidden"
                style={{ width: 30, height: 30, borderRadius: 'var(--r4)', background: 'linear-gradient(135deg,#FF8A00,#FF5200)', flexShrink: 0 }}
              >
                {avatar
                  ? <img src={avatar} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  : <span style={{ color: '#fff', fontWeight: 800, fontSize: 13 }}>{name[0]}</span>
                }
              </div>
              <div className="hidden lg:block" style={{ lineHeight: 1.2 }}>
                <p style={{ fontWeight: 600, fontSize: 13, color: 'var(--t1)' }}>{name.split(' ')[0]}</p>
                <p style={{ fontSize: 11, color: 'var(--t3)' }}>View Profile</p>
              </div>
              <svg className="hidden lg:block w-3.5 h-3.5" style={{ color: 'var(--t3)' }} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </Link>
          </div>
        </div>
      </header>

      {/* ════════════════════════════════════════
          BODY
      ════════════════════════════════════════ */}
      <div className="flex justify-center" style={{ paddingTop: 'var(--header-h)' }}>
        <div className="flex w-full" style={{ maxWidth: outerMax }}>

          {/* ── LEFT SIDEBAR ── */}
          <aside
            className="hidden md:block shrink-0"
            style={{ width: 'var(--sidebar-l)', background: 'var(--bg)' }}
          >
            <div
              className="flex flex-col no-scroll overflow-y-auto"
              style={{
                position: 'sticky', top: 'var(--header-h)',
                height: 'calc(100vh - var(--header-h))',
                padding: '12px 8px 16px',
              }}
            >
              <div
                style={{
                  background: 'var(--card)',
                  borderRadius: 'var(--r12)',
                  boxShadow: 'var(--s1)',
                  padding: '8px',
                  display: 'flex', flexDirection: 'column', gap: 0,
                }}
              >

              {/* Main nav */}
              <nav style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                {NAV_MAIN.map(({ href, label, color, ico, badge }) => {
                  const on = active(href);
                  return (
                    <Link
                      key={href} href={href}
                      style={{
                        display: 'flex', alignItems: 'center', gap: 13,
                        padding: '0 12px', height: 48, borderRadius: 'var(--r8)',
                        background: on ? 'var(--orange-soft)' : 'transparent',
                        color: on ? 'var(--orange)' : 'var(--t2)',
                        fontWeight: on ? 700 : 500, fontSize: 15,
                        textDecoration: 'none',
                      }}
                      onMouseEnter={e => { if (!on) e.currentTarget.style.background = 'var(--bg)'; }}
                      onMouseLeave={e => { if (!on) e.currentTarget.style.background = 'transparent'; }}
                    >
                      <span style={{ color: on ? 'var(--orange)' : color, flexShrink: 0, display: 'flex' }}>{ico}</span>
                      <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{label}</span>
                      {badge && (
                        <span style={{
                          minWidth: 20, height: 20, borderRadius: 'var(--pill)',
                          background: '#E41E1E', color: '#fff', fontSize: 11, fontWeight: 800,
                          display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 5px',
                        }}>
                          {badge}
                        </span>
                      )}
                    </Link>
                  );
                })}
              </nav>

              {/* Divider + Services */}
              <Divider />
              <p style={{ padding: '0 12px 6px', fontSize: 11, fontWeight: 700, letterSpacing: '0.07em', textTransform: 'uppercase', color: 'var(--t3)' }}>
                eSahlan Services
              </p>
              <nav style={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                {NAV_SVC.map(({ href, label, color, ico }) => {
                  const on = active(href);
                  return (
                    <Link
                      key={href} href={href}
                      style={{
                        display: 'flex', alignItems: 'center', gap: 11,
                        padding: '0 12px', height: 42, borderRadius: 'var(--r8)',
                        background: on ? 'var(--orange-soft)' : 'transparent',
                        color: on ? 'var(--orange)' : 'var(--t2)',
                        fontWeight: on ? 600 : 400, fontSize: 14,
                        textDecoration: 'none',
                      }}
                      onMouseEnter={e => { if (!on) e.currentTarget.style.background = 'var(--bg)'; }}
                      onMouseLeave={e => { if (!on) e.currentTarget.style.background = 'transparent'; }}
                    >
                      <span style={{ color: on ? 'var(--orange)' : color, flexShrink: 0, display: 'flex' }}>{ico}</span>
                      <span>{label}</span>
                    </Link>
                  );
                })}
              </nav>

              {/* Divider + Bottom nav */}
              <Divider />
              <nav style={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                {NAV_BOT.map(({ href, label, ico }) => {
                  const on = active(href);
                  return (
                    <Link
                      key={href} href={href}
                      style={{
                        display: 'flex', alignItems: 'center', gap: 13,
                        padding: '0 12px', height: 46, borderRadius: 'var(--r8)',
                        background: on ? 'var(--orange-soft)' : 'transparent',
                        color: on ? 'var(--orange)' : 'var(--t2)',
                        fontWeight: on ? 700 : 500, fontSize: 14,
                        textDecoration: 'none',
                      }}
                      onMouseEnter={e => { if (!on) e.currentTarget.style.background = 'var(--bg)'; }}
                      onMouseLeave={e => { if (!on) e.currentTarget.style.background = 'transparent'; }}
                    >
                      <span style={{ color: on ? 'var(--orange)' : 'var(--t3)', flexShrink: 0, display: 'flex' }}>{ico}</span>
                      <span>{label}</span>
                    </Link>
                  );
                })}
              </nav>

              <div style={{ flex: 1 }} />

              {/* Dark-mode toggle */}
              <div style={{ padding: '12px 12px 4px' }}>
                <button
                  onClick={toggle}
                  className="flex items-center justify-between w-full"
                  style={{ padding: '10px 4px', cursor: 'pointer', background: 'transparent', border: 'none' }}
                >
                  <div className="flex items-center gap-3">
                    <svg className="w-[18px] h-[18px]" style={{ color: 'var(--t3)' }} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      {dark
                        ? <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        : <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                      }
                    </svg>
                    <span style={{ fontSize: 14, fontWeight: 500, color: 'var(--t2)' }}>Dark Mode</span>
                  </div>
                  {/* Toggle pill */}
                  <div
                    style={{
                      width: 40, height: 22, borderRadius: 'var(--pill)', flexShrink: 0,
                      background: dark ? 'var(--orange)' : 'var(--border)', position: 'relative', transition: 'background 200ms',
                    }}
                  >
                    <span
                      style={{
                        position: 'absolute', top: 3, left: 3,
                        width: 16, height: 16, borderRadius: 'var(--pill)',
                        background: '#fff', boxShadow: 'var(--s2)',
                        transform: dark ? 'translateX(18px)' : 'none', transition: 'transform 200ms',
                      }}
                    />
                  </div>
                </button>
              </div>

            </div>{/* end card wrapper */}
          </div>{/* end sticky */}
          </aside>

          {/* ── CENTER ── */}
          <main className="flex-1 min-w-0" style={{ minHeight: 'calc(100vh - var(--header-h))' }}>
            {children}
          </main>

          {/* ── RIGHT SIDEBAR ── */}
          {showRight && (
            <aside
              className="hidden xl:block shrink-0"
              style={{ width: 'var(--sidebar-r)', borderLeft: '1px solid var(--border)', background: 'var(--bg)' }}
            >
              <div
                className="no-scroll overflow-y-auto"
                style={{ position: 'sticky', top: 'var(--header-h)', height: 'calc(100vh - var(--header-h))' }}
              >
                <RightSidebar />
              </div>
            </aside>
          )}
        </div>
      </div>

      {/* ── Mobile bottom nav ── */}
      <nav
        className="md:hidden fixed bottom-0 inset-x-0 z-50 flex pb-safe"
        style={{ background: 'var(--card)', borderTop: '1px solid var(--border)' }}
      >
        {([
          { href: '/feed',                icon: IcoHome,  label: 'Home',    fab: false },
          { href: '/explore',             icon: IcoFeed,  label: 'Explore', fab: false },
          { href: '/create',              icon: IcoPlus,  label: 'Post',    fab: true  },
          { href: '/notifications',       icon: IcoBell,  label: 'Notifs',  fab: false },
          { href: `/profile/${uid}`,      icon: IcoUser,  label: 'Profile', fab: false },
        ]).map(({ href, icon: Icon, label, fab }) => {
          const on = active(href);
          return (
            <Link
              key={href} href={href}
              className="flex-1 flex flex-col items-center justify-center gap-1"
              style={{ padding: '10px 0', fontSize: 10, fontWeight: 600, color: on ? 'var(--orange)' : 'var(--t3)', textDecoration: 'none' }}
            >
              {fab
                ? (
                  <div style={{ width: 44, height: 44, borderRadius: 14, background: 'var(--orange)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginTop: -20 }}>
                    <Icon className="w-5 h-5" style={{ color: '#fff' }} />
                  </div>
                )
                : (
                  <>
                    <Icon className="w-6 h-6" />
                    <span>{label}</span>
                  </>
                )
              }
            </Link>
          );
        })}
      </nav>
    </div>
  );
}

// ── Small helpers ─────────────────────────────────────────────────────────────
function Divider() {
  return (
    <div style={{ height: 1, background: 'var(--border)', margin: '10px 8px 12px' }} />
  );
}

// ── Icons — each carries its own accent colour ────────────────────────────────
type I = { className?: string; style?: React.CSSProperties };

function mkSvg(d: string | string[], fill = false) {
  return function Ico({ className, style }: I) {
    const paths = Array.isArray(d) ? d : [d];
    return (
      <svg className={className} style={style} viewBox="0 0 24 24"
        fill={fill ? 'currentColor' : 'none'}
        stroke={fill ? 'none' : 'currentColor'} strokeWidth={1.9}
      >
        {paths.map((p, i) => <path key={i} strokeLinecap="round" strokeLinejoin="round" d={p} />)}
      </svg>
    );
  };
}

const IcoHome  = mkSvg('M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25');
const IcoFeed  = mkSvg('M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z');
const IcoReel  = mkSvg('M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z');
const IcoPod   = mkSvg('M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z');
const IcoChat  = mkSvg('M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z');
const IcoBell  = mkSvg('M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0');
const IcoStore = mkSvg('M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z');

// Service icons
const IcoFood  = mkSvg('M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75l-1.5.75a3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0L3 18m0-10.125c0-.621.504-1.125 1.125-1.125h15.75c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18V7.875z');
const IcoBag   = mkSvg('M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z');
const IcoPkg   = mkSvg('M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z');
const IcoCart  = mkSvg('M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z');
const IcoGrid  = mkSvg(['M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z','M13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z','M13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z']);
const IcoShirt = mkSvg('M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z');
const IcoTruck = mkSvg('M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12');
const IcoHeart = mkSvg('M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z');
const IcoPlane = mkSvg('M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5');
const IcoWifi  = mkSvg('M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z');

// Bottom + utility
const IcoWallet  = mkSvg('M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18-3a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6m18 0V6m0 0v3M3 6v3');
const IcoUser    = mkSvg('M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z');
const IcoSave    = mkSvg('M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z');
const IcoGear    = mkSvg(['M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z']);
const IcoSupport = mkSvg('M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z');
const IcoMsg     = mkSvg('M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z');
const IcoPlus    = mkSvg('M12 4v16m8-8H4');

// ── Nav data (after icons — avoids TDZ) ──────────────────────────────────────
const NAV_MAIN = [
  { href: '/feed',          label: 'Home',          color: '#FF8A00', badge: 0,  ico: <IcoHome  className="w-[22px] h-[22px]" /> },
  { href: '/explore',       label: 'eSpace Feed',   color: '#3B82F6', badge: 0,  ico: <IcoFeed  className="w-[22px] h-[22px]" /> },
  { href: '/reels',         label: 'Reels',         color: '#EC4899', badge: 0,  ico: <IcoReel  className="w-[22px] h-[22px]" /> },
  { href: '/podcasts',      label: 'Podcasts',      color: '#8B5CF6', badge: 0,  ico: <IcoPod   className="w-[22px] h-[22px]" /> },
  { href: '/chat',          label: 'Messages',      color: '#10B981', badge: 5,  ico: <IcoChat  className="w-[22px] h-[22px]" /> },
  { href: '/notifications', label: 'Notifications', color: '#F59E0B', badge: 12, ico: <IcoBell  className="w-[22px] h-[22px]" /> },
  { href: '/marketplace',   label: 'Marketplace',   color: '#06B6D4', badge: 0,  ico: <IcoStore className="w-[22px] h-[22px]" /> },
] as const;

const NAV_SVC = [
  { href: '/shop/efood',      label: 'eFood',      color: '#EF4444', ico: <IcoFood  className="w-5 h-5" /> },
  { href: '/shop/eshop',      label: 'eShop',      color: '#3B82F6', ico: <IcoBag   className="w-5 h-5" /> },
  { href: '/shop/eparcel',    label: 'eParcel',    color: '#F59E0B', ico: <IcoPkg   className="w-5 h-5" /> },
  { href: '/shop/egrocery',   label: 'eGrocery',   color: '#22C55E', ico: <IcoCart  className="w-5 h-5" /> },
  { href: '/shop/ewholesale', label: 'eWholesale', color: '#06B6D4', ico: <IcoGrid  className="w-5 h-5" /> },
  { href: '/shop/elaundry',   label: 'eLaundry',   color: '#8B5CF6', ico: <IcoShirt className="w-5 h-5" /> },
  { href: '/shop/emoving',    label: 'eMoving',    color: '#EC4899', ico: <IcoTruck className="w-5 h-5" /> },
  { href: '/shop/ehealth',    label: 'eHealth',    color: '#EF4444', ico: <IcoHeart className="w-5 h-5" /> },
  { href: '/shop/eticket',    label: 'eTicket',    color: '#3B82F6', ico: <IcoPlane className="w-5 h-5" /> },
  { href: '/shop/edata',      label: 'eData',      color: '#10B981', ico: <IcoWifi  className="w-5 h-5" /> },
] as const;

const NAV_BOT = [
  { href: '/epay',     label: 'Wallet',         ico: <IcoWallet  className="w-5 h-5" /> },
  { href: '/profile',  label: 'Profile',        ico: <IcoUser    className="w-5 h-5" /> },
  { href: '/saved',    label: 'Saved',          ico: <IcoSave    className="w-5 h-5" /> },
  { href: '/settings', label: 'Settings',       ico: <IcoGear    className="w-5 h-5" /> },
  { href: '/support',  label: 'Support Center', ico: <IcoSupport className="w-5 h-5" /> },
] as const;
