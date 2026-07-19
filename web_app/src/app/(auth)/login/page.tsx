'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/auth';

type Step = 'phone' | 'otp';

export default function LoginPage() {
  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('');
  const [otp, setOtp] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [resendCooldown, setResendCooldown] = useState(0);

  const { sendOtp, verifyOtp } = useAuthStore();
  const router = useRouter();

  async function handleSendOtp(e: React.FormEvent) {
    e.preventDefault();
    if (!phone.trim()) return;
    setLoading(true);
    setError('');
    try {
      await sendOtp(phone.trim());
      setStep('otp');
      startCooldown();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to send OTP');
    } finally {
      setLoading(false);
    }
  }

  async function handleVerifyOtp(e: React.FormEvent) {
    e.preventDefault();
    if (otp.length < 4) return;
    setLoading(true);
    setError('');
    try {
      await verifyOtp(phone.trim(), otp.trim());
      router.replace('/feed');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Invalid OTP');
    } finally {
      setLoading(false);
    }
  }

  function startCooldown() {
    setResendCooldown(60);
    const t = setInterval(() => {
      setResendCooldown(s => { if (s <= 1) { clearInterval(t); return 0; } return s - 1; });
    }, 1000);
  }

  async function handleResend() {
    if (resendCooldown > 0) return;
    setLoading(true);
    setError('');
    try {
      await sendOtp(phone.trim());
      startCooldown();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to resend');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-[#07003B] to-[#1E3A6E] px-4">
      {/* Logo */}
      <div className="mb-8 text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#FF8A00] mb-4 shadow-lg">
          <span className="text-white font-black text-2xl">e</span>
        </div>
        <h1 className="text-white font-black text-3xl tracking-tight">eSahlan</h1>
        <p className="text-white/60 text-sm mt-1">Everything You Need, Simplified</p>
      </div>

      {/* Card */}
      <div className="w-full max-w-sm bg-white dark:bg-gray-900 rounded-2xl shadow-2xl p-6">
        {step === 'phone' ? (
          <form onSubmit={handleSendOtp} className="space-y-4">
            <div>
              <h2 className="text-xl font-bold text-gray-900 dark:text-white">Sign in</h2>
              <p className="text-sm text-gray-500 mt-1">Enter your phone number to continue</p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                Phone Number
              </label>
              <input
                type="tel"
                value={phone}
                onChange={e => setPhone(e.target.value)}
                placeholder="e.g. 61XXXXXXX"
                className="w-full px-3.5 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl text-sm bg-gray-50 dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-[#FF8A00] focus:border-transparent"
                autoFocus
                required
              />
            </div>

            {error && <p className="text-red-500 text-xs">{error}</p>}

            <button
              type="submit"
              disabled={loading || !phone.trim()}
              className="w-full py-2.5 bg-[#FF8A00] hover:bg-[#e07a00] disabled:opacity-50 text-white font-bold rounded-xl text-sm transition-colors"
            >
              {loading ? 'Sending…' : 'Send OTP'}
            </button>
          </form>
        ) : (
          <form onSubmit={handleVerifyOtp} className="space-y-4">
            <div>
              <button
                type="button"
                onClick={() => setStep('phone')}
                className="text-xs text-[#FF8A00] font-semibold mb-2"
              >
                ← Back
              </button>
              <h2 className="text-xl font-bold text-gray-900 dark:text-white">Enter OTP</h2>
              <p className="text-sm text-gray-500 mt-1">
                Code sent to <span className="font-semibold text-gray-700">{phone}</span>
              </p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                OTP Code
              </label>
              <input
                type="number"
                value={otp}
                onChange={e => setOtp(e.target.value)}
                placeholder="Enter code"
                className="w-full px-3.5 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl text-sm bg-gray-50 dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-[#FF8A00] focus:border-transparent text-center text-xl tracking-widest font-bold"
                maxLength={6}
                autoFocus
                required
              />
            </div>

            {error && <p className="text-red-500 text-xs">{error}</p>}

            <button
              type="submit"
              disabled={loading || otp.length < 4}
              className="w-full py-2.5 bg-[#FF8A00] hover:bg-[#e07a00] disabled:opacity-50 text-white font-bold rounded-xl text-sm transition-colors"
            >
              {loading ? 'Verifying…' : 'Verify & Sign In'}
            </button>

            <button
              type="button"
              onClick={handleResend}
              disabled={resendCooldown > 0}
              className="w-full text-xs text-gray-500 disabled:opacity-50"
            >
              {resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend OTP'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
