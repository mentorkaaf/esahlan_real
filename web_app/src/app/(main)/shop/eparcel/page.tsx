'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ModuleHeader, Spinner } from '../efood/page';

interface ParcelType { id: number; name: string; description?: string; icon?: string; }
interface District { id: number; name: string; }
interface PriceResult { fee: number; estimated_days?: string; }

export default function EParcelPage() {
  const [types, setTypes] = useState<ParcelType[]>([]);
  const [districts, setDistricts] = useState<District[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedType, setSelectedType] = useState<number | null>(null);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [price, setPrice] = useState<PriceResult | null>(null);
  const [calculating, setCalculating] = useState(false);
  const [ordering, setOrdering] = useState(false);
  const [success, setSuccess] = useState(false);
  const [senderName, setSenderName] = useState('');
  const [senderPhone, setSenderPhone] = useState('');
  const [recipientName, setRecipientName] = useState('');
  const [recipientPhone, setRecipientPhone] = useState('');
  const router = useRouter();

  useEffect(() => {
    Promise.all([
      api.get<{ data: ParcelType[] }>('/eparcel/types').catch(() => ({ data: [] })),
      api.get<{ data: District[] }>('/eparcel/districts').catch(() => ({ data: [] })),
    ]).then(([t, d]) => {
      setTypes(Array.isArray(t.data) ? t.data : []);
      setDistricts(Array.isArray(d.data) ? d.data : []);
    }).finally(() => setLoading(false));
  }, []);

  async function calculate() {
    if (!selectedType || !from || !to) return;
    setCalculating(true);
    setPrice(null);
    try {
      const res = await api.post<{ data: PriceResult }>('/eparcel/calculate', {
        type_id: selectedType, from_district_id: from, to_district_id: to,
      });
      setPrice(res.data);
    } catch { setPrice(null); }
    setCalculating(false);
  }

  async function placeOrder() {
    if (!selectedType || !from || !to || !price) return;
    setOrdering(true);
    try {
      await api.post('/eparcel/order', {
        type_id: selectedType, from_district_id: from, to_district_id: to,
        sender_name: senderName, sender_phone: senderPhone,
        recipient_name: recipientName, recipient_phone: recipientPhone,
      });
      setSuccess(true);
    } catch { }
    setOrdering(false);
  }

  if (success) return (
    <div className="max-w-2xl mx-auto">
      <ModuleHeader title="eParcel" emoji="📦" onBack={() => router.push('/shop')} />
      <div className="flex flex-col items-center justify-center py-20 px-8 text-center">
        <div className="text-5xl mb-4">✅</div>
        <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Order Placed!</h2>
        <p className="text-gray-400 text-sm mb-6">Your parcel order has been received. We'll contact you shortly.</p>
        <button onClick={() => { setSuccess(false); setPrice(null); setSelectedType(null); setFrom(''); setTo(''); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">New Order</button>
      </div>
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eParcel" emoji="📦" onBack={() => router.push('/shop')} />
      {loading ? <Spinner /> : (
        <div className="px-4 space-y-5">
          {/* Parcel Type */}
          <div>
            <h2 className="font-black text-gray-900 dark:text-white mb-2">Select Parcel Type</h2>
            <div className="grid grid-cols-2 gap-2">
              {types.map(t => (
                <button key={t.id} onClick={() => setSelectedType(t.id)}
                  className={`p-3 rounded-2xl border-2 text-left transition-colors ${selectedType === t.id ? 'border-[#FF8A00] bg-[#FF8A00]/5' : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900'}`}>
                  <p className="font-black text-sm text-gray-900 dark:text-white">{t.name}</p>
                  {t.description && <p className="text-xs text-gray-400 mt-0.5 leading-snug">{t.description}</p>}
                </button>
              ))}
            </div>
          </div>

          {/* Districts */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pickup District</label>
              <select value={from} onChange={e => setFrom(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                <option value="">Select…</option>
                {districts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivery District</label>
              <select value={to} onChange={e => setTo(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                <option value="">Select…</option>
                {districts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
          </div>

          <button onClick={calculate} disabled={!selectedType || !from || !to || calculating}
            className="w-full py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black rounded-2xl disabled:opacity-40 transition-opacity">
            {calculating ? 'Calculating…' : 'Calculate Price'}
          </button>

          {/* Price result */}
          {price && (
            <div className="bg-[#FF8A00]/5 border border-[#FF8A00]/20 rounded-2xl p-4">
              <div className="flex items-center justify-between">
                <p className="text-sm text-gray-600 dark:text-gray-300 font-bold">Delivery Fee</p>
                <p className="text-2xl font-black text-[#FF8A00]">${price.fee}</p>
              </div>
              {price.estimated_days && <p className="text-xs text-gray-400 mt-1">Estimated: {price.estimated_days}</p>}
            </div>
          )}

          {/* Contact details */}
          {price && (
            <div className="space-y-3">
              <h2 className="font-black text-gray-900 dark:text-white">Contact Details</h2>
              <div className="grid grid-cols-2 gap-3">
                {[
                  { label: 'Sender Name', val: senderName, set: setSenderName },
                  { label: 'Sender Phone', val: senderPhone, set: setSenderPhone },
                  { label: 'Recipient Name', val: recipientName, set: setRecipientName },
                  { label: 'Recipient Phone', val: recipientPhone, set: setRecipientPhone },
                ].map(f => (
                  <div key={f.label}>
                    <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{f.label}</label>
                    <input value={f.val} onChange={e => f.set(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
                  </div>
                ))}
              </div>
              <button onClick={placeOrder} disabled={ordering || !senderName || !recipientName}
                className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
                {ordering ? 'Placing Order…' : `Place Order · $${price.fee}`}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
