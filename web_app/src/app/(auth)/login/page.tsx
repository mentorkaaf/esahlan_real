'use client';
import { useState, useRef, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/auth';

type Mode = 'phone' | 'email';

export default function LoginPage() {
  const [mode, setMode] = useState<Mode>('phone');
  // Phone + PIN
  const [phone, setPhone] = useState('');
  const [pin, setPin] = useState(['', '', '', '']);
  const pinRefs = [
    useRef<HTMLInputElement>(null),
    useRef<HTMLInputElement>(null),
    useRef<HTMLInputElement>(null),
    useRef<HTMLInputElement>(null),
  ];
  // Email + Password
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPw, setShowPw] = useState(false);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const { loginWithPhone, loginWithEmail } = useAuthStore();
  const router = useRouter();

  // Auto-focus first PIN box when switching to phone mode
  useEffect(() => {
    if (mode === 'phone') setPin(['', '', '', '']);
  }, [mode]);

  function handlePinChange(i: number, val: string) {
    const digit = val.replace(/\D/g, '').slice(-1);
    const next = [...pin];
    next[i] = digit;
    setPin(next);
    if (digit && i < 3) pinRefs[i + 1].current?.focus();
  }

  function handlePinKeyDown(i: number, e: React.KeyboardEvent) {
    if (e.key === 'Backspace' && !pin[i] && i > 0) {
      pinRefs[i - 1].current?.focus();
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (mode === 'phone') {
      if (!phone.trim()) { setError('Enter your phone number'); return; }
      if (pin.join('').length < 4) { setError('Enter your 4-digit PIN'); return; }
    } else {
      if (!email.trim()) { setError('Enter your email address'); return; }
      if (!password)     { setError('Enter your password'); return; }
    }
    setLoading(true);
    try {
      if (mode === 'phone') {
        await loginWithPhone(phone.trim(), pin.join(''));
      } else {
        await loginWithEmail(email.trim(), password);
      }
      router.replace('/feed');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-[#07003B] px-4 relative overflow-hidden">
      {/* Background blobs */}
      <div className="absolute -top-20 -right-16 w-64 h-64 rounded-full bg-[#FF8A00]/10 pointer-events-none" />
      <div className="absolute -bottom-10 -left-10 w-48 h-48 rounded-full bg-white/4 pointer-events-none" />

      {/* Logo */}
      <div className="mb-8 text-center z-10">
        <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#FF8A00] mb-4 shadow-lg">
          <span className="text-white font-black text-2xl">e</span>
        </div>
        <h1 className="text-white font-black text-3xl tracking-tight">eSahlan</h1>
        <p className="text-white/50 text-sm mt-1">Everything You Need, Simplified</p>
      </div>

      {/* Card */}
      <div className="w-full max-w-sm bg-white dark:bg-gray-900 rounded-3xl shadow-2xl overflow-hidden z-10">
        {/* Header */}
        <div className="px-6 pt-7 pb-2">
          <h2 className="text-2xl font-black text-gray-900 dark:text-white">Welcome back 👋</h2>
          <p className="text-sm text-gray-400 mt-1">Sign in to your eSahlan account</p>
        </div>

        {/* Mode toggle */}
        <div className="px-6 pt-5">
          <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-2xl border border-[#FF8A00]/20">
            <button
              type="button"
              onClick={() => setMode('phone')}
              className={`flex-1 py-2.5 rounded-xl text-xs font-bold transition-all duration-150 ${
                mode === 'phone'
                  ? 'bg-[#FF8A00] text-white shadow-sm'
                  : 'text-gray-400 hover:text-gray-600'
              }`}
            >
              📱 Phone & PIN
            </button>
            <button
              type="button"
              onClick={() => setMode('email')}
              className={`flex-1 py-2.5 rounded-xl text-xs font-bold transition-all duration-150 ${
                mode === 'email'
                  ? 'bg-[#FF8A00] text-white shadow-sm'
                  : 'text-gray-400 hover:text-gray-600'
              }`}
            >
              ✉️ Email & Password
            </button>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="px-6 pt-6 pb-7 space-y-5">
          {mode === 'phone' ? (
            <>
              {/* Phone field */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">
                  Phone Number
                </label>
                <div className="flex items-center border-[1.5px] border-[#FF8A00]/35 rounded-2xl bg-gray-50 dark:bg-gray-800 focus-within:border-[#FF8A00] transition-colors">
                  <span className="pl-4 text-gray-400 text-sm">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                  </span>
                  <input
                    type="tel"
                    value={phone}
                    onChange={e => setPhone(e.target.value)}
                    placeholder="e.g. 61XXXXXXX"
                    className="flex-1 px-3 py-3.5 bg-transparent text-sm font-semibold text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none"
                    autoFocus
                  />
                </div>
              </div>

              {/* PIN boxes */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs font-bold text-gray-700 dark:text-gray-300">PIN Code</label>
                  <span className="text-xs text-gray-400">4 digits</span>
                </div>
                <div className="flex gap-3 justify-between">
                  {pin.map((digit, i) => (
                    <input
                      key={i}
                      ref={pinRefs[i]}
                      type="password"
                      inputMode="numeric"
                      maxLength={1}
                      value={digit}
                      onChange={e => handlePinChange(i, e.target.value)}
                      onKeyDown={e => handlePinKeyDown(i, e)}
                      className={`w-16 h-16 text-center text-2xl font-black rounded-2xl border-[1.5px] bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none transition-all duration-150 ${
                        digit
                          ? 'border-[#FF8A00]/60 bg-[#FF8A00]/5'
                          : 'border-[#FF8A00]/30 focus:border-[#FF8A00] focus:bg-[#FF8A00]/5 focus:shadow-[0_0_0_3px_rgba(255,138,0,0.15)]'
                      }`}
                    />
                  ))}
                </div>
              </div>
            </>
          ) : (
            <>
              {/* Email field */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">
                  Email Address
                </label>
                <div className="flex items-center border-[1.5px] border-[#FF8A00]/35 rounded-2xl bg-gray-50 dark:bg-gray-800 focus-within:border-[#FF8A00] transition-colors">
                  <span className="pl-4 text-gray-400">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </span>
                  <input
                    type="email"
                    value={email}
                    onChange={e => setEmail(e.target.value)}
                    placeholder="you@example.com"
                    className="flex-1 px-3 py-3.5 bg-transparent text-sm font-semibold text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none"
                    autoFocus
                  />
                </div>
              </div>

              {/* Password field */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">
                  Password
                </label>
                <div className="flex items-center border-[1.5px] border-[#FF8A00]/35 rounded-2xl bg-gray-50 dark:bg-gray-800 focus-within:border-[#FF8A00] transition-colors">
                  <span className="pl-4 text-gray-400">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </span>
                  <input
                    type={showPw ? 'text' : 'password'}
                    value={password}
                    onChange={e => setPassword(e.target.value)}
                    placeholder="Your password"
                    className="flex-1 px-3 py-3.5 bg-transparent text-sm font-semibold text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPw(v => !v)}
                    className="pr-4 text-gray-400 hover:text-gray-600"
                  >
                    {showPw ? (
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                    ) : (
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    )}
                  </button>
                </div>
              </div>
            </>
          )}

          {error && (
            <p className="text-red-500 text-xs font-semibold bg-red-50 dark:bg-red-950/20 px-3 py-2 rounded-xl">
              {error}
            </p>
          )}

          {/* Submit */}
          <button
            type="submit"
            disabled={loading}
            className="w-full h-14 bg-[#07003B] hover:bg-[#0d0060] disabled:opacity-50 text-white font-black rounded-2xl text-base transition-colors flex items-center justify-center gap-3"
          >
            {loading ? (
              <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            ) : (
              <>
                <span>Sign In</span>
                <span className="w-7 h-7 bg-white/15 rounded-xl flex items-center justify-center">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </span>
              </>
            )}
          </button>

          <p className="text-center text-sm text-gray-400">
            Don&apos;t have an account?{' '}
            <a href="/register" className="text-[#FF8A00] font-bold hover:underline">Sign Up</a>
          </p>
        </form>
      </div>
    </div>
  );
}
