'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ModuleHeader, Spinner } from '../efood/page';

interface MoveType { id: number; name: string; icon?: string; description?: string; }
interface District { id: number; name: string; }
interface ExtraService { id: number; name: string; price: number; }
interface Package { id: number; name: string; price: number; description?: string; }
interface PriceResult { total: number; base_price?: number; }

export default function EMovingPage() {
  const [moveTypes, setMoveTypes] = useState<MoveType[]>([]);
  const [districts, setDistricts] = useState<District[]>([]);
  const [extras, setExtras] = useState<ExtraService[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [loading, setLoading] = useState(true);
  const [step, setStep] = useState(1);
  const [selectedType, setSelectedType] = useState<MoveType | null>(null);
  const [selectedPackage, setSelectedPackage] = useState<Package | null>(null);
  const [selectedExtras, setSelectedExtras] = useState<number[]>([]);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [moveDate, setMoveDate] = useState('');
  const [price, setPrice] = useState<PriceResult | null>(null);
  const [calculating, setCalculating] = useState(false);
  const [ordering, setOrdering] = useState(false);
  const [success, setSuccess] = useState(false);
  const router = useRouter();

  useEffect(() => {
    Promise.all([
      api.get<{ data: MoveType[] }>('/emoving/move-types').catch(() => ({ data: [] })),
      api.get<{ data: District[] }>('/emoving/districts').catch(() => ({ data: [] })),
      api.get<{ data: ExtraService[] }>('/emoving/extra-services').catch(() => ({ data: [] })),
    ]).then(([t, d, e]) => {
      setMoveTypes(Array.isArray(t.data) ? t.data : []);
      setDistricts(Array.isArray(d.data) ? d.data : []);
      setExtras(Array.isArray(e.data) ? e.data : []);
    }).finally(() => setLoading(false));
  }, []);

  async function loadPackages(type: MoveType) {
    setSelectedType(type);
    setSelectedPackage(null);
    try {
      const res = await api.get<{ data: Package[] }>(`/emoving/packages/${type.id}`);
      setPackages(Array.isArray(res.data) ? res.data : []);
    } catch { setPackages([]); }
    setStep(2);
  }

  async function calculate() {
    if (!selectedType || !selectedPackage || !from || !to) return;
    setCalculating(true);
    try {
      const res = await api.post<{ data: PriceResult }>('/emoving/calculate', {
        move_type_id: selectedType.id,
        package_id: selectedPackage.id,
        from_district_id: from,
        to_district_id: to,
        extra_service_ids: selectedExtras,
      });
      setPrice(res.data);
      setStep(4);
    } catch { }
    setCalculating(false);
  }

  async function placeOrder() {
    if (!selectedType || !selectedPackage || !from || !to) return;
    setOrdering(true);
    try {
      await api.post('/emoving/order', {
        move_type_id: selectedType.id,
        package_id: selectedPackage.id,
        from_district_id: from,
        to_district_id: to,
        extra_service_ids: selectedExtras,
        move_date: moveDate,
      });
      setSuccess(true);
    } catch { }
    setOrdering(false);
  }

  function toggleExtra(id: number) {
    setSelectedExtras(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  }

  if (success) return (
    <div className="max-w-2xl mx-auto">
      <ModuleHeader title="eMoving" emoji="🚛" onBack={() => router.push('/shop')} />
      <div className="flex flex-col items-center py-20 px-8 text-center">
        <div className="text-5xl mb-4">✅</div>
        <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Booking Confirmed!</h2>
        <p className="text-gray-400 text-sm mb-6">Your moving request has been received. Our team will contact you soon.</p>
        <button onClick={() => { setSuccess(false); setStep(1); setPrice(null); setSelectedType(null); setSelectedPackage(null); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">New Booking</button>
      </div>
    </div>
  );

  const stepLabel = ['Move Type', 'Package', 'Details', 'Confirm'];

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eMoving" emoji="🚛" onBack={() => router.push('/shop')} />
      {loading ? <Spinner /> : (
        <div className="px-4 space-y-5">
          {/* Step indicator */}
          <div className="flex items-center gap-1">
            {stepLabel.map((s, i) => (
              <div key={s} className="flex items-center gap-1 flex-1">
                <div className={`flex-1 h-1 rounded-full ${i < step ? 'bg-[#FF8A00]' : 'bg-gray-200 dark:bg-gray-700'}`} />
                {i === stepLabel.length - 1 && <div className={`w-2 h-2 rounded-full ${step > i ? 'bg-[#FF8A00]' : 'bg-gray-200 dark:bg-gray-700'}`} />}
              </div>
            ))}
          </div>

          {/* Step 1: Move type */}
          {step === 1 && (
            <div>
              <h2 className="font-black text-gray-900 dark:text-white mb-3">What are you moving?</h2>
              <div className="space-y-2">
                {moveTypes.map(t => (
                  <button key={t.id} onClick={() => loadPackages(t)}
                    className="w-full p-4 rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 text-left flex items-center gap-3 hover:border-[#FF8A00] transition-colors">
                    <div className="w-10 h-10 rounded-xl bg-[#FF8A00]/10 flex items-center justify-center text-xl">{t.icon ?? '🚛'}</div>
                    <div>
                      <p className="font-black text-sm text-gray-900 dark:text-white">{t.name}</p>
                      {t.description && <p className="text-xs text-gray-400">{t.description}</p>}
                    </div>
                    <svg className="w-4 h-4 text-gray-400 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" /></svg>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Step 2: Package */}
          {step === 2 && (
            <div>
              <button onClick={() => setStep(1)} className="text-xs text-gray-400 mb-3 flex items-center gap-1">← Back</button>
              <h2 className="font-black text-gray-900 dark:text-white mb-3">Choose a Package</h2>
              <div className="space-y-2">
                {packages.map(p => (
                  <button key={p.id} onClick={() => { setSelectedPackage(p); setStep(3); }}
                    className={`w-full p-4 rounded-2xl border-2 text-left transition-colors ${selectedPackage?.id === p.id ? 'border-[#FF8A00] bg-[#FF8A00]/5' : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900'}`}>
                    <div className="flex items-center justify-between">
                      <p className="font-black text-sm text-gray-900 dark:text-white">{p.name}</p>
                      <p className="font-black text-[#FF8A00]">${p.price}</p>
                    </div>
                    {p.description && <p className="text-xs text-gray-400 mt-1">{p.description}</p>}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Step 3: Districts + extras + date */}
          {step === 3 && (
            <div className="space-y-4">
              <button onClick={() => setStep(2)} className="text-xs text-gray-400 flex items-center gap-1">← Back</button>
              <h2 className="font-black text-gray-900 dark:text-white">Moving Details</h2>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">From District</label>
                  <select value={from} onChange={e => setFrom(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                    <option value="">Select…</option>
                    {districts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">To District</label>
                  <select value={to} onChange={e => setTo(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                    <option value="">Select…</option>
                    {districts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Moving Date</label>
                <input type="date" value={moveDate} onChange={e => setMoveDate(e.target.value)} min={new Date().toISOString().split('T')[0]}
                  className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
              </div>
              {extras.length > 0 && (
                <div>
                  <h3 className="font-black text-sm text-gray-900 dark:text-white mb-2">Extra Services</h3>
                  <div className="space-y-2">
                    {extras.map(e => (
                      <label key={e.id} className="flex items-center gap-3 p-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 cursor-pointer">
                        <input type="checkbox" checked={selectedExtras.includes(e.id)} onChange={() => toggleExtra(e.id)} className="accent-[#FF8A00]" />
                        <span className="text-sm text-gray-700 dark:text-gray-300 flex-1">{e.name}</span>
                        <span className="text-sm font-black text-[#FF8A00]">+${e.price}</span>
                      </label>
                    ))}
                  </div>
                </div>
              )}
              <button onClick={calculate} disabled={!from || !to || calculating}
                className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
                {calculating ? 'Calculating…' : 'Get Price'}
              </button>
            </div>
          )}

          {/* Step 4: Confirm */}
          {step === 4 && price && (
            <div className="space-y-4">
              <button onClick={() => setStep(3)} className="text-xs text-gray-400 flex items-center gap-1">← Back</button>
              <h2 className="font-black text-gray-900 dark:text-white">Order Summary</h2>
              <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-4 space-y-2">
                <Row label="Move Type" value={selectedType?.name ?? ''} />
                <Row label="Package" value={selectedPackage?.name ?? ''} />
                <Row label="From" value={districts.find(d => String(d.id) === from)?.name ?? from} />
                <Row label="To" value={districts.find(d => String(d.id) === to)?.name ?? to} />
                {moveDate && <Row label="Date" value={moveDate} />}
                {selectedExtras.length > 0 && <Row label="Extras" value={`${selectedExtras.length} service(s)`} />}
                <div className="border-t border-gray-100 dark:border-gray-800 pt-2 mt-2 flex items-center justify-between">
                  <p className="font-black text-gray-900 dark:text-white">Total</p>
                  <p className="text-2xl font-black text-[#FF8A00]">${price.total}</p>
                </div>
              </div>
              <button onClick={placeOrder} disabled={ordering}
                className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
                {ordering ? 'Booking…' : `Book Now · $${price.total}`}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <p className="text-gray-400">{label}</p>
      <p className="font-bold text-gray-900 dark:text-white">{value}</p>
    </div>
  );
}
