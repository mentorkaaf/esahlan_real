'use client';
import { useState, useEffect } from 'react';
import { api } from '@/lib/api';

interface WalletData {
  balance: number;
  currency: string;
  transactions: Transaction[];
}

interface Transaction {
  id: number;
  type: 'topup' | 'payment' | 'withdrawal' | 'refund';
  amount: number;
  description: string;
  created_at: string;
  status: 'completed' | 'pending' | 'failed';
}

export default function EPayPage() {
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [loading, setLoading] = useState(true);
  const [topupAmount, setTopupAmount] = useState('');
  const [phone, setPhone] = useState('');
  const [topupLoading, setTopupLoading] = useState(false);
  const [topupMsg, setTopupMsg] = useState('');

  useEffect(() => {
    api.get<{ data: WalletData }>('/wallet')
      .then(res => setWallet(res.data))
      .catch(() => setWallet({ balance: 0, currency: 'USD', transactions: [] }))
      .finally(() => setLoading(false));
  }, []);

  async function handleTopup(e: React.FormEvent) {
    e.preventDefault();
    const amt = parseFloat(topupAmount);
    if (!amt || amt < 1 || !phone) return;
    setTopupLoading(true);
    setTopupMsg('');
    try {
      await api.post('/wallet/topup', { amount: amt, phone });
      setTopupMsg('Top-up request sent! Check your phone for WaafiPay prompt.');
      setTopupAmount('');
      const res = await api.get<{ data: WalletData }>('/wallet');
      setWallet(res.data);
    } catch {
      setTopupMsg('Top-up failed. Please try again.');
    }
    setTopupLoading(false);
  }

  if (loading) return (
    <div className="flex items-center justify-center h-full">
      <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
    </div>
  );

  const txs = wallet?.transactions ?? [];

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 py-4 z-10">
        <h1 className="text-xl font-black text-gray-900 dark:text-white">ePay</h1>
      </div>

      <div className="px-4 py-5 space-y-5">
        {/* Balance card */}
        <div className="rounded-2xl bg-gradient-to-br from-[#07003B] to-[#1E3A6E] p-6 text-white relative overflow-hidden">
          <div className="absolute right-0 top-0 w-40 h-40 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2" />
          <div className="absolute right-10 bottom-0 w-24 h-24 bg-white/5 rounded-full translate-y-1/2" />
          <p className="text-white/60 text-sm mb-1">Available Balance</p>
          <p className="text-4xl font-black tracking-tight">
            ${wallet?.balance.toFixed(2) ?? '0.00'}
          </p>
          <p className="text-white/40 text-xs mt-1 uppercase tracking-wider">{wallet?.currency ?? 'USD'}</p>
          <div className="mt-5 flex gap-6">
            <div>
              <p className="text-white text-lg font-black leading-tight">{txs.length}</p>
              <p className="text-white/50 text-xs">Transactions</p>
            </div>
            <div>
              <p className="text-white text-lg font-black leading-tight">{txs.filter(t => t.status === 'completed').length}</p>
              <p className="text-white/50 text-xs">Completed</p>
            </div>
          </div>
        </div>

        {/* Top-up card */}
        <div className="rounded-2xl border border-gray-100 dark:border-gray-800 p-5 bg-white dark:bg-gray-900">
          <h2 className="font-black text-gray-900 dark:text-white text-base mb-4">Top Up via WaafiPay</h2>
          <form onSubmit={handleTopup} className="space-y-3">
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                EVC/WaafiPay Phone
              </label>
              <input
                type="tel"
                value={phone}
                onChange={e => setPhone(e.target.value)}
                placeholder="252XXXXXXXXX"
                className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30 focus:border-[#FF8A00]"
              />
            </div>
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Amount (USD)
              </label>
              <input
                type="number"
                value={topupAmount}
                onChange={e => setTopupAmount(e.target.value)}
                placeholder="5.00"
                min="1"
                step="0.01"
                className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30 focus:border-[#FF8A00]"
              />
            </div>
            <div className="flex gap-2 flex-wrap">
              {['5', '10', '20', '50'].map(a => (
                <button
                  key={a}
                  type="button"
                  onClick={() => setTopupAmount(a)}
                  className={`px-4 py-1.5 rounded-full text-sm font-bold border transition-colors ${
                    topupAmount === a
                      ? 'bg-[#FF8A00] border-[#FF8A00] text-white'
                      : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-[#FF8A00] hover:text-[#FF8A00]'
                  }`}
                >
                  ${a}
                </button>
              ))}
            </div>
            {topupMsg && (
              <p className={`text-sm font-medium rounded-xl px-3 py-2 ${
                topupMsg.includes('failed')
                  ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400'
                  : 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400'
              }`}>{topupMsg}</p>
            )}
            <button
              type="submit"
              disabled={!topupAmount || !phone || topupLoading}
              className="w-full py-3.5 bg-[#FF8A00] disabled:opacity-40 text-white font-black rounded-xl transition-opacity text-sm"
            >
              {topupLoading ? 'Processing…' : 'Top Up Balance'}
            </button>
          </form>
        </div>

        {/* Transactions list */}
        <div>
          <h2 className="font-black text-gray-900 dark:text-white text-base mb-3">Transactions</h2>
          {txs.length === 0 ? (
            <div className="text-center py-12 text-gray-400 text-sm">No transactions yet</div>
          ) : (
            <div className="space-y-2">
              {txs.map(tx => (
                <div
                  key={tx.id}
                  className="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800"
                >
                  <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 text-lg font-black ${
                    tx.type === 'topup'      ? 'bg-green-100 dark:bg-green-900/30 text-green-600' :
                    tx.type === 'payment'    ? 'bg-red-100 dark:bg-red-900/30 text-red-500' :
                    tx.type === 'refund'     ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-500' :
                                              'bg-gray-100 dark:bg-gray-800 text-gray-500'
                  }`}>
                    {tx.type === 'topup' ? '↑' : tx.type === 'payment' ? '↓' : tx.type === 'refund' ? '↺' : '$'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-bold text-gray-900 dark:text-white truncate">{tx.description}</p>
                    <p className="text-xs text-gray-400">{new Date(tx.created_at).toLocaleDateString()}</p>
                  </div>
                  <div className="text-right shrink-0">
                    <p className={`text-sm font-black ${
                      tx.type === 'payment' || tx.type === 'withdrawal'
                        ? 'text-red-500'
                        : 'text-green-600 dark:text-green-400'
                    }`}>
                      {tx.type === 'payment' || tx.type === 'withdrawal' ? '-' : '+'}${tx.amount.toFixed(2)}
                    </p>
                    <p className={`text-[10px] font-bold uppercase tracking-wider ${
                      tx.status === 'completed' ? 'text-green-500' :
                      tx.status === 'failed'    ? 'text-red-500' : 'text-yellow-500'
                    }`}>{tx.status}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
