'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface District { id: number; name: string; }
interface Property {
  id: number; title: string; price: number; type?: string;
  bedrooms?: number; bathrooms?: number; area?: number;
  district?: string; images?: string[]; thumbnail?: string;
  is_available?: boolean;
}

export default function ERentPage() {
  const [districts, setDistricts] = useState<District[]>([]);
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterDistrict, setFilterDistrict] = useState('');
  const [filterType, setFilterType] = useState('');
  const [filterBedrooms, setFilterBedrooms] = useState('');
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: District[] }>('/erent/districts').catch(() => ({ data: [] })).then(r => setDistricts(Array.isArray(r.data) ? r.data : []));
    loadProperties();
  }, []);

  async function loadProperties() {
    setLoading(true);
    const params = new URLSearchParams();
    if (filterDistrict) params.set('district', filterDistrict);
    if (filterType) params.set('type', filterType);
    if (filterBedrooms) params.set('bedrooms', filterBedrooms);
    try {
      const res = await api.get<{ data: Property[] }>(`/erent/properties?${params}`);
      setProperties(Array.isArray(res.data) ? res.data : []);
    } catch { setProperties([]); }
    setLoading(false);
  }

  useEffect(() => { loadProperties(); }, [filterDistrict, filterType, filterBedrooms]); // eslint-disable-line react-hooks/exhaustive-deps

  const TYPES = ['Apartment', 'Villa', 'Studio', 'Office', 'Warehouse'];

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eRent" emoji="🏠" onBack={() => router.push('/shop')} />

      {/* Filters */}
      <div className="px-4 pb-4 grid grid-cols-3 gap-2">
        <select value={filterDistrict} onChange={e => setFilterDistrict(e.target.value)}
          className="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-white focus:outline-none">
          <option value="">All Districts</option>
          {districts.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
        </select>
        <select value={filterType} onChange={e => setFilterType(e.target.value)}
          className="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-white focus:outline-none">
          <option value="">All Types</option>
          {TYPES.map(t => <option key={t} value={t.toLowerCase()}>{t}</option>)}
        </select>
        <select value={filterBedrooms} onChange={e => setFilterBedrooms(e.target.value)}
          className="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-white focus:outline-none">
          <option value="">Any Beds</option>
          {['1','2','3','4','5+'].map(b => <option key={b} value={b}>{b} bed{b === '1' ? '' : 's'}</option>)}
        </select>
      </div>

      {loading ? <Spinner /> : properties.length === 0 ? <Empty text="No properties found" /> : (
        <div className="px-4 space-y-4">
          {properties.map(p => (
            <button key={p.id} onClick={() => router.push(`/shop/erent/${p.id}`)}
              className="w-full bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden text-left hover:shadow-md transition-shadow">
              <div className="h-44 bg-gray-100 dark:bg-gray-800 relative">
                {(p.thumbnail || p.images?.[0]) && (
                  <img src={mediaUrl(p.thumbnail ?? p.images![0])} alt={p.title} className="w-full h-full object-cover" />
                )}
                {p.type && (
                  <span className="absolute top-3 left-3 bg-white/90 dark:bg-gray-900/90 text-xs font-bold px-2 py-1 rounded-lg capitalize text-gray-900 dark:text-white">
                    {p.type}
                  </span>
                )}
                {p.is_available === false && (
                  <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                    <span className="text-white font-bold text-sm bg-black/60 px-3 py-1 rounded-full">Not Available</span>
                  </div>
                )}
              </div>
              <div className="p-4">
                <div className="flex items-start justify-between gap-2">
                  <p className="font-black text-gray-900 dark:text-white leading-snug">{p.title}</p>
                  <p className="font-black text-[#FF8A00] shrink-0">${p.price}<span className="text-xs font-normal text-gray-400">/mo</span></p>
                </div>
                {p.district && <p className="text-xs text-gray-400 mt-1">📍 {p.district}</p>}
                <div className="flex gap-4 mt-2">
                  {p.bedrooms !== undefined && <span className="text-xs text-gray-500">🛏 {p.bedrooms} bed{p.bedrooms !== 1 ? 's' : ''}</span>}
                  {p.bathrooms !== undefined && <span className="text-xs text-gray-500">🚿 {p.bathrooms} bath{p.bathrooms !== 1 ? 's' : ''}</span>}
                  {p.area && <span className="text-xs text-gray-500">📐 {p.area} m²</span>}
                </div>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
