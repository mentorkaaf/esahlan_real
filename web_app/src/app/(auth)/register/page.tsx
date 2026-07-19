'use client';
import { useState, useRef, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { TOKEN_KEY } from '@/lib/constants';
import { useAuthStore } from '@/store/auth';
import { User } from '@/types';

type CredMode = 'pin' | 'email';

interface District { id: number; name: string; }

export default function RegisterPage() {
  const [credMode, setCredMode] = useState<CredMode>('pin');
  const [name, setName]     = useState('');
  const [phone, setPhone]   = useState('');
  const [districtId, setDistrictId] = useState('');
  const [districts, setDistricts]   = useState<District[]>([]);

  // PIN
  const [pin, setPin] = useState(['', '', '', '']);
  const pinRefs = [useRef<HTMLInputElement>(null), useRef<HTMLInputElement>(null),
                   useRef<HTMLInputElement>(null), useRef<HTMLInputElement>(null)];

  // Email + password
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm]   = useState('');
  const [showPw, setShowPw]     = useState(false);

  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');

  const router = useRouter();
  const { loginWithPhone, loginWithEmail } = useAuthStore();

  useEffect(() => {
    api.get<{ data: District[] }>('/districts').then(r => setDistricts(r.data)).catch(() => {});
  }, []);

  function handlePinChange(i: number, val: string) {
    const digit = val.replace(/\D/g, '').slice(-1);
    const next = [...pin]; next[i] = digit; setPin(next);
    if (digit && i < 3) pinRefs[i + 1].current?.focus();
  }
  function handlePinKeyDown(i: number, e: React.KeyboardEvent) {
    if (e.key === 'Backspace' && !pin[i] && i > 0) pinRefs[i - 1].current?.focus();
  }

  function pwStrength(pw: string) {
    return {
      length:  pw.length >= 8,
      upper:   /[A-Z]/.test(pw),
      lower:   /[a-z]/.test(pw),
      digit:   /[0-9]/.test(pw),
      special: /[@$!%*#?&^_\-]/.test(pw),
    };
  }
  const strength = pwStrength(password);
  const strengthScore = Object.values(strength).filter(Boolean).length;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (!name.trim())     { setError('Enter your full name'); return; }
    if (!phone.trim())    { setError('Enter your phone number'); return; }
    if (!districtId)      { setError('Select your district'); return; }

    let pw = '';
    let emailVal: string | undefined;

    if (credMode === 'pin') {
      if (pin.join('').length < 4) { setError('Create a 4-digit PIN'); return; }
      pw = pin.join('');
    } else {
      if (!email.trim())  { setError('Enter your email address'); return; }
      if (!strength.length)  { setError('Password must be at least 8 characters'); return; }
      if (!strength.upper)   { setError('Password needs an uppercase letter'); return; }
      if (!strength.lower)   { setError('Password needs a lowercase letter'); return; }
      if (!strength.digit)   { setError('Password needs a number'); return; }
      if (!strength.special) { setError('Password needs a special character (@$!%*#?&)'); return; }
      if (password !== confirm) { setError('Passwords do not match'); return; }
      emailVal = email.trim();
      pw = password;
    }

    setLoading(true);
    try {
      const res = await api.post<{ data: { token: string } }>('/auth/register', {
        name: name.trim(),
        phone: phone.trim(),
        password: pw,
        password_confirmation: pw,
        ...(emailVal ? { email: emailVal } : {}),
        district_id: Number(districtId),
      });
      const token = res.data.token;
      localStorage.setItem(TOKEN_KEY, token);

      // Fetch user then set auth state
      const me = await api.get<{ data: User }>('/auth/me');
      const user = me.data;
      useAuthStore.setState({ token, user, isAuthenticated: true });

      router.replace('/feed');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Registration failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-[#07003B] px-4 py-10 relative overflow-hidden">
      <div className="absolute -top-20 -right-16 w-64 h-64 rounded-full bg-[#FF8A00]/10 pointer-events-none" />
      <div className="absolute -bottom-10 -left-10 w-48 h-48 rounded-full bg-white/4 pointer-events-none" />

      {/* Logo */}
      <div className="mb-6 text-center z-10">
        <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-[#FF8A00] mb-3 shadow-lg">
          <span className="text-white font-black text-xl">e</span>
        </div>
        <h1 className="text-white font-black text-2xl tracking-tight">eSahlan</h1>
      </div>

      <div className="w-full max-w-sm bg-white dark:bg-gray-900 rounded-3xl shadow-2xl overflow-hidden z-10">
        <div className="px-6 pt-7 pb-2">
          <h2 className="text-2xl font-black text-gray-900 dark:text-white">Create account</h2>
          <p className="text-sm text-gray-400 mt-1">Join eSahlan today</p>
        </div>

        {/* Credential mode toggle */}
        <div className="px-6 pt-5">
          <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-2xl border border-[#FF8A00]/20">
            <button type="button" onClick={() => setCredMode('pin')}
              className={`flex-1 py-2.5 rounded-xl text-xs font-bold transition-all ${credMode === 'pin' ? 'bg-[#FF8A00] text-white shadow-sm' : 'text-gray-400'}`}>
              📱 Phone & PIN
            </button>
            <button type="button" onClick={() => setCredMode('email')}
              className={`flex-1 py-2.5 rounded-xl text-xs font-bold transition-all ${credMode === 'email' ? 'bg-[#FF8A00] text-white shadow-sm' : 'text-gray-400'}`}>
              ✉️ Email & Password
            </button>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="px-6 pt-5 pb-7 space-y-4">
          {/* Name */}
          <Field label="Full Name">
            <input type="text" value={name} onChange={e => setName(e.target.value)}
              placeholder="Your full name"
              className="field-input" autoFocus />
          </Field>

          {/* Phone */}
          <Field label="Phone Number">
            <input type="tel" value={phone} onChange={e => setPhone(e.target.value)}
              placeholder="e.g. 61XXXXXXX"
              className="field-input" />
          </Field>

          {/* District */}
          <Field label="District">
            <select value={districtId} onChange={e => setDistrictId(e.target.value)}
              className="field-input">
              <option value="">Select district…</option>
              {districts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
            </select>
          </Field>

          {credMode === 'pin' ? (
            <div>
              <div className="flex justify-between mb-2">
                <span className="text-xs font-bold text-gray-700 dark:text-gray-300">Create PIN</span>
                <span className="text-xs text-gray-400">4 digits</span>
              </div>
              <div className="flex gap-3 justify-between">
                {pin.map((digit, i) => (
                  <input key={i} ref={pinRefs[i]} type="password" inputMode="numeric"
                    maxLength={1} value={digit}
                    onChange={e => handlePinChange(i, e.target.value)}
                    onKeyDown={e => handlePinKeyDown(i, e)}
                    className={`w-16 h-16 text-center text-2xl font-black rounded-2xl border-[1.5px] bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none transition-all ${digit ? 'border-[#FF8A00]/60 bg-[#FF8A00]/5' : 'border-[#FF8A00]/30 focus:border-[#FF8A00] focus:bg-[#FF8A00]/5'}`}
                  />
                ))}
              </div>
            </div>
          ) : (
            <>
              <Field label="Email Address">
                <input type="email" value={email} onChange={e => setEmail(e.target.value)}
                  placeholder="you@example.com" className="field-input" />
              </Field>
              <Field label="Password">
                <div className="relative">
                  <input type={showPw ? 'text' : 'password'} value={password}
                    onChange={e => setPassword(e.target.value)}
                    placeholder="Min 8 chars, uppercase, number, symbol"
                    className="field-input pr-10" />
                  <button type="button" onClick={() => setShowPw(v => !v)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <EyeIcon show={showPw} />
                  </button>
                </div>
                {/* Strength bar */}
                {password && (
                  <div className="mt-2 flex gap-1">
                    {[1,2,3,4,5].map(n => (
                      <div key={n} className={`h-1 flex-1 rounded-full transition-colors ${n <= strengthScore ? strengthScore <= 2 ? 'bg-red-400' : strengthScore <= 3 ? 'bg-yellow-400' : 'bg-green-400' : 'bg-gray-200'}`} />
                    ))}
                  </div>
                )}
              </Field>
              <Field label="Confirm Password">
                <input type="password" value={confirm} onChange={e => setConfirm(e.target.value)}
                  placeholder="Repeat your password" className="field-input" />
              </Field>
            </>
          )}

          {error && (
            <p className="text-red-500 text-xs font-semibold bg-red-50 dark:bg-red-950/20 px-3 py-2 rounded-xl">{error}</p>
          )}

          <button type="submit" disabled={loading}
            className="w-full h-14 bg-[#07003B] hover:bg-[#0d0060] disabled:opacity-50 text-white font-black rounded-2xl text-base transition-colors flex items-center justify-center gap-3">
            {loading
              ? <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              : <><span>Create Account</span><span className="w-7 h-7 bg-white/15 rounded-xl flex items-center justify-center"><ArrowIcon /></span></>
            }
          </button>

          <p className="text-center text-sm text-gray-400">
            Already have an account?{' '}
            <a href="/login" className="text-[#FF8A00] font-bold hover:underline">Sign In</a>
          </p>
        </form>
      </div>

      <style>{`
        .field-input {
          width: 100%;
          padding: 0.875rem 1rem;
          border: 1.5px solid rgba(255,138,0,0.35);
          border-radius: 1rem;
          background: #f9fafb;
          font-size: 0.875rem;
          font-weight: 600;
          color: #111827;
          outline: none;
          transition: border-color 0.15s;
        }
        .field-input:focus { border-color: #FF8A00; }
        @media (prefers-color-scheme: dark) {
          .field-input { background: #1f2937; color: #f9fafb; }
        }
      `}</style>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">{label}</label>
      {children}
    </div>
  );
}

function EyeIcon({ show }: { show: boolean }) {
  return show ? (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
    </svg>
  ) : (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
  );
}

function ArrowIcon() {
  return (
    <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
    </svg>
  );
}
