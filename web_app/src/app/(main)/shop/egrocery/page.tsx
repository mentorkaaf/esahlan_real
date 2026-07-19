'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface Category { id: number; name: string; icon?: string; }
interface Product { id: number; name: string; image?: string; price: number; sale_price?: number; unit?: string; stock?: number; }

export default function EGroceryPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const [cart, setCart] = useState<Record<number, number>>({});
  const router = useRouter();

  useEffect(() => {
    Promise.all([
      api.get<{ data: Category[] }>('/egrocery/categories').catch(() => ({ data: [] })),
      api.get<{ data: Product[] }>('/egrocery/products').catch(() => ({ data: [] })),
    ]).then(([c, p]) => {
      setCategories(Array.isArray(c.data) ? c.data : []);
      setProducts(Array.isArray(p.data) ? p.data : []);
    }).finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    const params = new URLSearchParams();
    if (activeCategory) params.set('category', String(activeCategory));
    if (search) params.set('search', search);
    setLoading(true);
    api.get<{ data: Product[] }>(`/egrocery/products?${params}`).then(r => setProducts(Array.isArray(r.data) ? r.data : [])).catch(() => {}).finally(() => setLoading(false));
  }, [activeCategory, search]);

  const total = Object.entries(cart).reduce((sum, [id, qty]) => {
    const p = products.find(p => p.id === Number(id));
    return sum + (p ? (p.sale_price ?? p.price) * qty : 0);
  }, 0);
  const cartCount = Object.values(cart).reduce((a, b) => a + b, 0);

  function addToCart(id: number) { setCart(c => ({ ...c, [id]: (c[id] ?? 0) + 1 })); }
  function removeFromCart(id: number) { setCart(c => { const n = { ...c }; if (n[id] > 1) n[id]--; else delete n[id]; return n; }); }

  return (
    <div className="max-w-2xl mx-auto pb-24">
      <ModuleHeader title="eGrocery" emoji="🛒" onBack={() => router.push('/shop')} />

      <div className="px-4 pb-4">
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search products…" className="w-full px-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-xl text-sm focus:outline-none text-gray-900 dark:text-white" />
      </div>

      {/* Categories */}
      {categories.length > 0 && (
        <div className="flex gap-2 px-4 mb-4 overflow-x-auto pb-1 scrollbar-none">
          <button onClick={() => setActiveCategory(null)} className={`shrink-0 px-4 py-1.5 rounded-full text-xs font-bold ${activeCategory === null ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>All</button>
          {categories.map(c => (
            <button key={c.id} onClick={() => setActiveCategory(c.id)} className={`shrink-0 px-4 py-1.5 rounded-full text-xs font-bold ${activeCategory === c.id ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>{c.name}</button>
          ))}
        </div>
      )}

      {loading ? <Spinner /> : products.length === 0 ? <Empty text="No products found" /> : (
        <div className="grid grid-cols-2 gap-3 px-4">
          {products.map(p => (
            <div key={p.id} className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">
              <div className="h-28 bg-gray-100 dark:bg-gray-800">
                {p.image && <img src={mediaUrl(p.image)} alt={p.name} className="w-full h-full object-cover" />}
              </div>
              <div className="p-3">
                <p className="text-xs font-bold text-gray-900 dark:text-white leading-snug line-clamp-2">{p.name}</p>
                {p.unit && <p className="text-[11px] text-gray-400 mt-0.5">{p.unit}</p>}
                <div className="flex items-center justify-between mt-2">
                  <div>
                    {p.sale_price ? (
                      <>
                        <p className="text-sm font-black text-[#FF8A00]">${p.sale_price}</p>
                        <p className="text-[11px] text-gray-400 line-through">${p.price}</p>
                      </>
                    ) : <p className="text-sm font-black text-gray-900 dark:text-white">${p.price}</p>}
                  </div>
                  {cart[p.id] ? (
                    <div className="flex items-center gap-1.5">
                      <button onClick={() => removeFromCart(p.id)} className="w-6 h-6 rounded-full bg-[#FF8A00]/10 text-[#FF8A00] font-black text-sm flex items-center justify-center">-</button>
                      <span className="text-xs font-black text-gray-900 dark:text-white w-4 text-center">{cart[p.id]}</span>
                      <button onClick={() => addToCart(p.id)} className="w-6 h-6 rounded-full bg-[#FF8A00] text-white font-black text-sm flex items-center justify-center">+</button>
                    </div>
                  ) : (
                    <button onClick={() => addToCart(p.id)} className="w-7 h-7 rounded-full bg-[#FF8A00] text-white flex items-center justify-center font-black text-lg">+</button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {cartCount > 0 && (
        <div className="fixed bottom-20 md:bottom-6 inset-x-4 max-w-2xl mx-auto z-40">
          <button className="w-full bg-[#FF8A00] text-white font-black py-3.5 rounded-2xl shadow-lg flex items-center justify-between px-5">
            <span className="bg-white/20 rounded-lg px-2 py-0.5 text-sm">{cartCount}</span>
            <span>View Cart</span>
            <span>${total.toFixed(2)}</span>
          </button>
        </div>
      )}
    </div>
  );
}
