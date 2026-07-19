'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

type Tab = 'doctor' | 'nurse' | 'ambulance';
interface Category { id: number; name: string; }
interface Doctor {
  id: number; name: string; avatar?: string; specialization?: string;
  experience?: number; rating?: number; fee?: number; available?: boolean;
}

export default function EHealthPage() {
  const [tab, setTab] = useState<Tab>('doctor');
  const [categories, setCategories] = useState<Category[]>([]);
  const [doctors, setDoctors] = useState<Doctor[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterSpec, setFilterSpec] = useState('');
  // Ambulance form
  const [ambAddress, setAmbAddress] = useState('');
  const [ambPhone, setAmbPhone] = useState('');
  const [ambNotes, setAmbNotes] = useState('');
  const [sending, setSending] = useState(false);
  const [ambSuccess, setAmbSuccess] = useState(false);
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: Category[] }>('/ehealth/categories').catch(() => ({ data: [] })).then(r => setCategories(Array.isArray(r.data) ? r.data : []));
    loadDoctors();
  }, []);

  async function loadDoctors() {
    setLoading(true);
    const params = new URLSearchParams();
    if (filterSpec) params.set('specialization', filterSpec);
    try {
      const res = await api.get<{ data: Doctor[] }>(`/ehealth/doctors?${params}`);
      setDoctors(Array.isArray(res.data) ? res.data : []);
    } catch { setDoctors([]); }
    setLoading(false);
  }

  useEffect(() => { if (tab === 'doctor') loadDoctors(); }, [tab, filterSpec]); // eslint-disable-line react-hooks/exhaustive-deps

  async function callAmbulance() {
    if (!ambAddress || !ambPhone) return;
    setSending(true);
    try {
      await api.post('/ehealth/ambulance', { address: ambAddress, phone: ambPhone, notes: ambNotes });
      setAmbSuccess(true);
    } catch { }
    setSending(false);
  }

  const TABS: { key: Tab; label: string; emoji: string }[] = [
    { key: 'doctor', label: 'Doctor', emoji: '👨‍⚕️' },
    { key: 'nurse', label: 'Nurse', emoji: '👩‍⚕️' },
    { key: 'ambulance', label: 'Ambulance', emoji: '🚑' },
  ];

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eHealth" emoji="🏥" onBack={() => router.push('/shop')} />

      {/* Tabs */}
      <div className="flex gap-2 px-4 mb-4">
        {TABS.map(t => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`flex-1 py-2.5 rounded-xl text-sm font-black flex items-center justify-center gap-1.5 transition-colors ${tab === t.key ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
            <span>{t.emoji}</span> {t.label}
          </button>
        ))}
      </div>

      {/* Doctor tab */}
      {tab === 'doctor' && (
        <div className="px-4 space-y-3">
          {categories.length > 0 && (
            <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
              <button onClick={() => setFilterSpec('')} className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold ${!filterSpec ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>All</button>
              {categories.map(c => (
                <button key={c.id} onClick={() => setFilterSpec(c.name)} className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold ${filterSpec === c.name ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>{c.name}</button>
              ))}
            </div>
          )}
          {loading ? <Spinner /> : doctors.length === 0 ? <Empty text="No doctors found" /> : (
            doctors.map(d => (
              <button key={d.id} onClick={() => router.push(`/shop/ehealth/${d.id}`)}
                className="w-full bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-4 text-left flex items-center gap-4 hover:shadow-md transition-shadow">
                <div className="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 shrink-0 overflow-hidden">
                  {d.avatar ? <img src={mediaUrl(d.avatar)} alt={d.name} className="w-full h-full object-cover" /> : <div className="w-full h-full flex items-center justify-center text-2xl">👨‍⚕️</div>}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-black text-gray-900 dark:text-white">{d.name}</p>
                  {d.specialization && <p className="text-xs text-[#FF8A00] font-bold mt-0.5">{d.specialization}</p>}
                  <div className="flex items-center gap-3 mt-1">
                    {d.experience && <span className="text-xs text-gray-400">{d.experience}y exp</span>}
                    {d.rating && <span className="text-xs text-yellow-500">⭐ {d.rating}</span>}
                    {d.available === false && <span className="text-xs text-red-400 font-bold">Unavailable</span>}
                  </div>
                </div>
                {d.fee && <p className="font-black text-[#FF8A00] shrink-0">${d.fee}</p>}
              </button>
            ))
          )}
        </div>
      )}

      {/* Nurse tab */}
      {tab === 'nurse' && (
        <div className="px-4">
          <NurseForm />
        </div>
      )}

      {/* Ambulance tab */}
      {tab === 'ambulance' && (
        <div className="px-4 space-y-4">
          {ambSuccess ? (
            <div className="flex flex-col items-center py-16 text-center">
              <div className="text-5xl mb-3">🚑</div>
              <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Ambulance Requested!</h2>
              <p className="text-gray-400 text-sm mb-6">Help is on the way. Stay calm and keep your phone nearby.</p>
              <button onClick={() => { setAmbSuccess(false); setAmbAddress(''); setAmbPhone(''); setAmbNotes(''); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">Done</button>
            </div>
          ) : (
            <>
              <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl p-3 flex items-start gap-2">
                <span className="text-red-500 text-lg mt-0.5">⚠️</span>
                <p className="text-xs text-red-600 dark:text-red-400 font-bold leading-relaxed">For life-threatening emergencies only. Misuse may result in charges.</p>
              </div>
              {[
                { label: 'Your Address / Location', val: ambAddress, set: setAmbAddress, type: 'text' },
                { label: 'Contact Phone', val: ambPhone, set: setAmbPhone, type: 'tel' },
              ].map(f => (
                <div key={f.label}>
                  <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{f.label}</label>
                  <input type={f.type} value={f.val} onChange={e => f.set(e.target.value)}
                    className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-400/30" />
                </div>
              ))}
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes (optional)</label>
                <textarea value={ambNotes} onChange={e => setAmbNotes(e.target.value)} rows={2} placeholder="Describe the emergency…"
                  className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none resize-none" />
              </div>
              <button onClick={callAmbulance} disabled={!ambAddress || !ambPhone || sending}
                className="w-full py-4 bg-red-500 hover:bg-red-600 text-white font-black text-base rounded-2xl disabled:opacity-40 transition-colors">
                {sending ? 'Requesting…' : '🚑 Call Ambulance'}
              </button>
            </>
          )}
        </div>
      )}
    </div>
  );
}

function NurseForm() {
  const [address, setAddress] = useState('');
  const [phone, setPhone] = useState('');
  const [date, setDate] = useState('');
  const [notes, setNotes] = useState('');
  const [sending, setSending] = useState(false);
  const [success, setSuccess] = useState(false);

  async function book() {
    if (!address || !phone || !date) return;
    setSending(true);
    try {
      await api.post('/ehealth/book', { service: 'nurse', address, phone, date, notes });
      setSuccess(true);
    } catch { }
    setSending(false);
  }

  if (success) return (
    <div className="flex flex-col items-center py-16 text-center">
      <div className="text-5xl mb-3">✅</div>
      <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Nurse Booked!</h2>
      <p className="text-gray-400 text-sm">A nurse will visit you at the scheduled time.</p>
    </div>
  );

  return (
    <div className="space-y-4">
      <h2 className="font-black text-gray-900 dark:text-white">Book a Home Nurse</h2>
      {[
        { label: 'Home Address', val: address, set: setAddress, type: 'text' },
        { label: 'Phone', val: phone, set: setPhone, type: 'tel' },
      ].map(f => (
        <div key={f.label}>
          <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{f.label}</label>
          <input type={f.type} value={f.val} onChange={e => f.set(e.target.value)}
            className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
        </div>
      ))}
      <div>
        <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Preferred Date</label>
        <input type="datetime-local" value={date} onChange={e => setDate(e.target.value)} min={new Date().toISOString().slice(0, 16)}
          className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
      </div>
      <div>
        <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</label>
        <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={2} placeholder="Any specific requirements…"
          className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none resize-none" />
      </div>
      <button onClick={book} disabled={!address || !phone || !date || sending}
        className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
        {sending ? 'Booking…' : 'Book Nurse'}
      </button>
    </div>
  );
}
