'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';

interface Restaurant {
  id: number;
  name: string;
  logo?: string;
  cover_image?: string;
  rating?: number;
  delivery_time?: string;
  min_order?: number;
  delivery_fee?: number;
  is_open?: boolean;
  category?: string;
}
interface Category { id: number; name: string; icon?: string; }
interface Banner { id: number; image: string; }

export default function EFoodPage() {
  const [banners, setBanners] = useState<Banner[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [restaurants, setRestaurants] = useState<Restaurant[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const router = useRouter();

  useEffect(() => {
    Promise.all([
      api.get<{ data: Banner[] }>('/efood/banners').catch(() => ({ data: [] })),
      api.get<{ data: Category[] }>('/efood/categories').catch(() => ({ data: [] })),
      api.get<{ data: Restaurant[] }>('/efood/restaurants').catch(() => ({ data: [] })),
    ]).then(([b, c, r]) => {
      setBanners(Array.isArray(b.data) ? b.data : []);
      setCategories(Array.isArray(c.data) ? c.data : []);
      setRestaurants(Array.isArray(r.data) ? r.data : []);
    }).finally(() => setLoading(false));
  }, []);

  const filtered = restaurants.filter(r =>
    (!search || r.name.toLowerCase().includes(search.toLowerCase()))
  );

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eFood" emoji="🍕" onBack={() => router.push('/shop')} />

      {/* Search */}
      <div className="px-4 pb-4">
        <div className="relative">
          <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search restaurants…" className="w-full pl-9 pr-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-xl text-sm focus:outline-none text-gray-900 dark:text-white" />
        </div>
      </div>

      {loading ? <Spinner /> : (
        <>
          {/* Banners */}
          {banners.length > 0 && (
            <div className="px-4 mb-4">
              <img src={mediaUrl(banners[0].image)} alt="" className="w-full h-36 object-cover rounded-2xl" />
            </div>
          )}

          {/* Categories */}
          {categories.length > 0 && (
            <div className="flex gap-2 px-4 mb-4 overflow-x-auto pb-1 scrollbar-none">
              <CategoryPill label="All" active={activeCategory === null} onClick={() => setActiveCategory(null)} />
              {categories.map(c => (
                <CategoryPill key={c.id} label={c.name} active={activeCategory === c.id} onClick={() => setActiveCategory(c.id)} />
              ))}
            </div>
          )}

          {/* Restaurant list */}
          <div className="px-4 space-y-3">
            {filtered.length === 0
              ? <Empty text="No restaurants found" />
              : filtered.map(r => (
                <button key={r.id} onClick={() => router.push(`/shop/efood/${r.id}`)}
                  className="w-full bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden text-left hover:shadow-md transition-shadow">
                  <div className="h-28 bg-gray-100 dark:bg-gray-800 relative">
                    {r.cover_image && <img src={mediaUrl(r.cover_image)} alt="" className="w-full h-full object-cover" />}
                    {r.is_open === false && (
                      <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                        <span className="text-white text-xs font-bold bg-black/60 px-3 py-1 rounded-full">Closed</span>
                      </div>
                    )}
                  </div>
                  <div className="p-3 flex items-center gap-3">
                    {r.logo && <img src={mediaUrl(r.logo)} alt="" className="w-10 h-10 rounded-xl object-cover shrink-0" />}
                    <div className="flex-1 min-w-0">
                      <p className="font-black text-sm text-gray-900 dark:text-white truncate">{r.name}</p>
                      <div className="flex items-center gap-2 mt-0.5 text-xs text-gray-400">
                        {r.rating && <span>⭐ {r.rating}</span>}
                        {r.delivery_time && <span>· {r.delivery_time}</span>}
                        {r.delivery_fee !== undefined && <span>· {r.delivery_fee === 0 ? 'Free delivery' : `$${r.delivery_fee} delivery`}</span>}
                      </div>
                    </div>
                  </div>
                </button>
              ))
            }
          </div>
        </>
      )}
    </div>
  );
}

function CategoryPill({ label, active, onClick }: { label: string; active: boolean; onClick: () => void }) {
  return (
    <button onClick={onClick} className={`shrink-0 px-4 py-1.5 rounded-full text-xs font-bold transition-colors ${active ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
      {label}
    </button>
  );
}

export function ModuleHeader({ title, emoji, onBack }: { title: string; emoji: string; onBack: () => void }) {
  return (
    <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 py-4 z-10 flex items-center gap-3 mb-4">
      <button onClick={onBack} className="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500">
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
      </button>
      <span className="text-xl">{emoji}</span>
      <h1 className="font-black text-lg text-gray-900 dark:text-white">{title}</h1>
    </div>
  );
}
export function Spinner() {
  return <div className="flex justify-center py-16"><div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" /></div>;
}
export function Empty({ text }: { text: string }) {
  return <div className="text-center py-12 text-gray-400 text-sm">{text}</div>;
}
