'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ModuleHeader, Spinner } from '../efood/page';

interface Rate { from: string; to: string; rate: number; }
interface PreviewResult { received_amount: number; fee?: number; rate: number; }

const WALLETS = ['EVC Plus', 'eDahab', 'Jeep Money', 'Premier'];

export default function EExchangePage() {
  const [rates, setRates] = useState<Rate[]>([]);
  const [loading, setLoading] = useState(true);
  const [fromWallet, setFromWallet] = useState('EVC Plus');
  const [toWallet, setToWallet] = useState('eDahab');
  const [amount, setAmount] = useState('');
  const [phone, setPhone] = useState('');
  const [preview, setPreview] = useState<PreviewResult | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [sending, setSending] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: Rate[] }>('/eexchange/rates').then(r => setRates(Array.isArray(r.data) ? r.data : [])).catch(() => {}).finally(() => setLoading(false));
  }, []);

  const currentRate = rates.find(r => r.from === fromWallet && r.to === toWallet);

  async function getPreview() {
    if (!amount || !fromWallet || !toWallet || fromWallet === toWallet) return;
    setPreviewing(true);
    setPreview(null);
    setError('');
    try {
      const res = await api.post<{ data: PreviewResult }>('/eexchange/calculate', {
        from_wallet: fromWallet, to_wallet: toWallet, amount: parseFloat(amount),
      });
      setPreview(res.data);
    } catch (e: unknown) { setError(e instanceof Error ? e.message : 'Could not calculate.'); }
    setPreviewing(false);
  }

  async function confirm() {
    if (!preview || !phone) return;
    setSending(true);
    setError('');
    try {
      await api.post('/eexchange/transfer', {
        from_wallet: fromWallet, to_wallet: toWallet,
        amount: parseFloat(amount), recipient_phone: phone,
      });
      setSuccess(true);
    } catch (e: unknown) { setError(e instanceof Error ? e.message : 'Transfer failed.'); }
    setSending(false);
  }

  function swap() {
    setFromWallet(toWallet);
    setToWallet(fromWallet);
    setPreview(null);
    setAmount('');
  }

  if (success) return (
    <div className="max-w-2xl mx-auto">
      <ModuleHeader title="eExchange" emoji="💱" onBack={() => router.push('/shop')} />
      <div className="flex flex-col items-center py-20 px-8 text-center">
        <div className="text-5xl mb-4">✅</div>
        <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Transfer Sent!</h2>
        <p className="text-gray-400 text-sm mb-6">{preview?.received_amount} {toWallet} sent to {phone}</p>
        <button onClick={() => { setSuccess(false); setPreview(null); setAmount(''); setPhone(''); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">New Transfer</button>
      </div>
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eExchange" emoji="💱" onBack={() => router.push('/shop')} />
      {loading ? <Spinner /> : (
        <div className="px-4 space-y-5">
          {/* Live Rates */}
          {rates.length > 0 && (
            <div className="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-4">
              <p className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Live Rates</p>
              <div className="space-y-1.5">
                {rates.map(r => (
                  <div key={`${r.from}-${r.to}`} className="flex items-center justify-between text-sm">
                    <p className="text-gray-600 dark:text-gray-300">{r.from} → {r.to}</p>
                    <p className="font-black text-gray-900 dark:text-white">{r.rate}</p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* From / To */}
          <div className="relative">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">From</label>
                <select value={fromWallet} onChange={e => { setFromWallet(e.target.value); setPreview(null); }}
                  className="mt-1 w-full px-3 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                  {WALLETS.map(w => <option key={w} value={w} disabled={w === toWallet}>{w}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">To</label>
                <select value={toWallet} onChange={e => { setToWallet(e.target.value); setPreview(null); }}
                  className="mt-1 w-full px-3 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                  {WALLETS.map(w => <option key={w} value={w} disabled={w === fromWallet}>{w}</option>)}
                </select>
              </div>
            </div>
            <button onClick={swap} className="absolute top-7 left-1/2 -translate-x-1/2 w-8 h-8 bg-[#FF8A00] text-white rounded-full flex items-center justify-center shadow-md z-10 text-base">⇄</button>
          </div>

          {currentRate && (
            <p className="text-xs text-center text-gray-400">1 {fromWallet} = {currentRate.rate} {toWallet}</p>
          )}

          {/* Amount */}
          <div>
            <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount ({fromWallet})</label>
            <input type="number" value={amount} onChange={e => { setAmount(e.target.value); setPreview(null); }} placeholder="0.00" min="0"
              className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-lg font-black text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
          </div>

          <button onClick={getPreview} disabled={!amount || fromWallet === toWallet || previewing}
            className="w-full py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-2xl disabled:opacity-40">
            {previewing ? 'Calculating…' : 'Preview Transfer'}
          </button>

          {/* Preview */}
          {preview && (
            <div className="bg-[#FF8A00]/5 border border-[#FF8A00]/20 rounded-2xl p-4 space-y-3">
              <div className="flex justify-between text-sm">
                <p className="text-gray-500">You send</p>
                <p className="font-black text-gray-900 dark:text-white">{amount} {fromWallet}</p>
              </div>
              {preview.fee !== undefined && (
                <div className="flex justify-between text-sm">
                  <p className="text-gray-500">Fee</p>
                  <p className="font-bold text-gray-600 dark:text-gray-300">{preview.fee} {fromWallet}</p>
                </div>
              )}
              <div className="flex justify-between border-t border-[#FF8A00]/20 pt-3">
                <p className="text-gray-500 text-sm">Recipient gets</p>
                <p className="text-xl font-black text-[#FF8A00]">{preview.received_amount} {toWallet}</p>
              </div>

              {/* Phone */}
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient Phone</label>
                <input type="tel" value={phone} onChange={e => setPhone(e.target.value)} placeholder="+252…"
                  className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
              </div>
              <button onClick={confirm} disabled={!phone || sending}
                className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
                {sending ? 'Sending…' : 'Confirm & Send'}
              </button>
            </div>
          )}

          {error && <p className="text-sm text-red-500 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-xl">{error}</p>}
        </div>
      )}
    </div>
  );
}
