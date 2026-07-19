'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface Banner { id: number; image: string; }
interface Category { id: number; name: string; icon?: string; }
interface Product { id: number; name: string; image?: string; price: number; sale_price?: number; rating?: number; }

export default function EShopPage() {
  const [banners, setBanners] = useState<Banner[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [flashDeals, setFlashDeals] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const [bannerIdx, setBannerIdx] = useState(0);
  const router = useRouter();

  useEffect(() => {
    Promise.all([
      api.get<{ data: Banner[] }>('/eshop/banners').catch(() => ({ data: [] })),
      api.get<{ data: Category[] }>('/eshop/categories').catch(() => ({ data: [] })),
      api.get<{ data: Product[] }>('/eshop/flash-deals').catch(() => ({ data: [] })),
      api.get<{ data: Product[] }>('/eshop/products').catch(() => ({ data: [] })),
    ]).then(([b, c, f, p]) => {
      setBanners(Array.isArray(b.data) ? b.data : []);
      setCategories(Array.isArray(c.data) ? c.data : []);
      setFlashDeals(Array.isArray(f.data) ? f.data : []);
      setProducts(Array.isArray(p.data) ? p.data : []);
    }).finally(() => setLoading(false));

    const t = setInterval(() => setBannerIdx(i => i + 1), 3500);
    return () => clearInterval(t);
  }, []);

  useEffect(() => {
    const params = new URLSearchParams();
    if (activeCategory) params.set('category', String(activeCategory));
    if (search) params.set('search', search);
    api.get<{ data: Product[] }>(`/eshop/products?${params}`).then(r => setProducts(Array.isArray(r.data) ? r.data : [])).catch(() => {});
  }, [activeCategory, search]);

  const activeBanner = banners.length ? banners[bannerIdx % banners.length] : null;

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eShop" emoji="🛍️" onBack={() => router.push('/shop')} />

      <div className="px-4 pb-4">
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search products…" className="w-full px-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-xl text-sm focus:outline-none text-gray-900 dark:text-white" />
      </div>

      {loading ? <Spinner /> : (
        <>
          {/* Banner */}
          {activeBanner && (
            <div className="px-4 mb-4 relative">
              <img src={mediaUrl(activeBanner.image)} alt="" className="w-full h-36 object-cover rounded-2xl" />
              {banners.length > 1 && (
                <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1">
                  {banners.map((_, i) => <div key={i} className={`w-1.5 h-1.5 rounded-full ${i === bannerIdx % banners.length ? 'bg-white' : 'bg-white/40'}`} />)}
                </div>
              )}
            </div>
          )}

          {/* Categories */}
          {categories.length > 0 && (
            <div className="flex gap-2 px-4 mb-4 overflow-x-auto pb-1 scrollbar-none">
              <button onClick={() => setActiveCategory(null)} className={`shrink-0 px-4 py-1.5 rounded-full text-xs font-bold ${activeCategory === null ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>All</button>
              {categories.map(c => (
                <button key={c.id} onClick={() => setActiveCategory(c.id)} className={`shrink-0 px-4 py-1.5 rounded-full text-xs font-bold ${activeCategory === c.id ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>{c.name}</button>
              ))}
            </div>
          )}

          {/* Flash Deals */}
          {flashDeals.length > 0 && (
            <div className="px-4 mb-5">
              <div className="flex items-center gap-2 mb-3">
                <span className="text-base">⚡</span>
                <h2 className="font-black text-gray-900 dark:text-white">Flash Deals</h2>
              </div>
              <div className="flex gap-3 overflow-x-auto pb-1 scrollbar-none">
                {flashDeals.map(p => <ProductCard key={p.id} product={p} compact />)}
              </div>
            </div>
          )}

          {/* All Products */}
          <div className="px-4">
            <h2 className="font-black text-gray-900 dark:text-white mb-3">Products</h2>
            {products.length === 0 ? <Empty text="No products found" /> : (
              <div className="grid grid-cols-2 gap-3">
                {products.map(p => <ProductCard key={p.id} product={p} />)}
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}

function ProductCard({ product: p, compact }: { product: Product; compact?: boolean }) {
  return (
    <div className={`bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden ${compact ? 'shrink-0 w-36' : ''}`}>
      <div className="h-28 bg-gray-100 dark:bg-gray-800">
        {p.image && <img src={mediaUrl(p.image)} alt={p.name} className="w-full h-full object-cover" />}
      </div>
      <div className="p-2.5">
        <p className="text-xs font-bold text-gray-900 dark:text-white line-clamp-2 leading-snug">{p.name}</p>
        {p.rating && <p className="text-[11px] text-yellow-500 mt-0.5">⭐ {p.rating}</p>}
        <div className="flex items-center justify-between mt-1.5">
          {p.sale_price ? (
            <div>
              <p className="text-sm font-black text-[#FF8A00]">${p.sale_price}</p>
              <p className="text-[10px] text-gray-400 line-through">${p.price}</p>
            </div>
          ) : <p className="text-sm font-black text-gray-900 dark:text-white">${p.price}</p>}
          <button className="w-7 h-7 rounded-full bg-[#FF8A00] text-white flex items-center justify-center font-black text-base">+</button>
        </div>
      </div>
    </div>
  );
}
