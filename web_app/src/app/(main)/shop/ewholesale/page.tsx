'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface Category { id: number; name: string; }
interface Product {
  id: number; name: string; image?: string;
  min_order?: number; unit?: string;
  price_range?: string; description?: string;
}

export default function EWholesalePage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const [inquiryProduct, setInquiryProduct] = useState<Product | null>(null);
  const [qty, setQty] = useState('');
  const [note, setNote] = useState('');
  const [phone, setPhone] = useState('');
  const [sending, setSending] = useState(false);
  const [success, setSuccess] = useState(false);
  const router = useRouter();

  useEffect(() => {
    Promise.all([
      api.get<{ data: Category[] }>('/ewholesale/categories').catch(() => ({ data: [] })),
      api.get<{ data: Product[] }>('/ewholesale/products').catch(() => ({ data: [] })),
    ]).then(([c, p]) => {
      setCategories(Array.isArray(c.data) ? c.data : []);
      setProducts(Array.isArray(p.data) ? p.data : []);
    }).finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (activeCategory) params.set('category', String(activeCategory));
    api.get<{ data: Product[] }>(`/ewholesale/products?${params}`).then(r => setProducts(Array.isArray(r.data) ? r.data : [])).catch(() => {});
  }, [search, activeCategory]);

  async function sendInquiry() {
    if (!inquiryProduct || !qty || !phone) return;
    setSending(true);
    try {
      await api.post('/ewholesale/inquire', { product_id: inquiryProduct.id, quantity: qty, phone, note });
      setSuccess(true);
    } catch { }
    setSending(false);
  }

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eWholesale" emoji="🏭" onBack={() => router.push('/shop')} />

      <div className="px-4 pb-3 space-y-3">
        <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl px-3 py-2">
          <p className="text-xs text-amber-700 dark:text-amber-400 font-bold">🏭 Bulk orders for businesses. Minimum order quantities apply.</p>
        </div>
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search products…"
          className="w-full px-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-xl text-sm focus:outline-none text-gray-900 dark:text-white" />
        {categories.length > 0 && (
          <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button onClick={() => setActiveCategory(null)} className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold ${activeCategory === null ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>All</button>
            {categories.map(c => (
              <button key={c.id} onClick={() => setActiveCategory(c.id === activeCategory ? null : c.id)} className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold ${activeCategory === c.id ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>{c.name}</button>
            ))}
          </div>
        )}
      </div>

      {loading ? <Spinner /> : products.length === 0 ? <Empty text="No products found" /> : (
        <div className="px-4 grid grid-cols-2 gap-3">
          {products.map(p => (
            <div key={p.id} className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">
              <div className="h-28 bg-gray-100 dark:bg-gray-800">
                {p.image && <img src={mediaUrl(p.image)} alt={p.name} className="w-full h-full object-cover" />}
              </div>
              <div className="p-3">
                <p className="text-xs font-black text-gray-900 dark:text-white line-clamp-2 leading-snug">{p.name}</p>
                {p.price_range && <p className="text-sm font-black text-[#FF8A00] mt-1">{p.price_range}</p>}
                {p.min_order && <p className="text-[10px] text-gray-400 mt-0.5">Min. {p.min_order} {p.unit ?? 'units'}</p>}
                <button onClick={() => { setInquiryProduct(p); setQty(''); setNote(''); setSuccess(false); }}
                  className="mt-2 w-full py-2 bg-[#FF8A00]/10 text-[#FF8A00] text-xs font-black rounded-xl">
                  Inquire
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Inquiry modal */}
      {inquiryProduct && !success && (
        <div className="fixed inset-0 z-50 flex items-end md:items-center justify-center px-4">
          <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={() => setInquiryProduct(null)} />
          <div className="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-t-2xl md:rounded-2xl p-5 space-y-4 shadow-2xl">
            <h2 className="font-black text-gray-900 dark:text-white">Inquiry: {inquiryProduct.name}</h2>
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Quantity {inquiryProduct.unit ? `(${inquiryProduct.unit})` : ''}</label>
              <input type="number" value={qty} onChange={e => setQty(e.target.value)} placeholder={inquiryProduct.min_order ? `Min. ${inquiryProduct.min_order}` : 'Enter quantity'}
                min={inquiryProduct.min_order ?? 1}
                className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
            </div>
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact Phone</label>
              <input type="tel" value={phone} onChange={e => setPhone(e.target.value)} placeholder="+252…"
                className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
            </div>
            <div>
              <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Note (optional)</label>
              <textarea value={note} onChange={e => setNote(e.target.value)} rows={2} placeholder="Any special requirements…"
                className="mt-1 w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none resize-none" />
            </div>
            <div className="flex gap-3">
              <button onClick={() => setInquiryProduct(null)} className="flex-1 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-600 dark:text-gray-300">Cancel</button>
              <button onClick={sendInquiry} disabled={!qty || !phone || sending}
                className="flex-1 py-3 bg-[#FF8A00] text-white font-black rounded-xl disabled:opacity-40">
                {sending ? 'Sending…' : 'Send Inquiry'}
              </button>
            </div>
          </div>
        </div>
      )}

      {success && (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4">
          <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />
          <div className="relative bg-white dark:bg-gray-900 rounded-2xl p-8 text-center shadow-2xl max-w-sm w-full">
            <div className="text-4xl mb-3">✅</div>
            <h2 className="font-black text-gray-900 dark:text-white mb-2">Inquiry Sent!</h2>
            <p className="text-gray-400 text-sm mb-5">Our team will contact you at {phone} within 24 hours.</p>
            <button onClick={() => { setSuccess(false); setInquiryProduct(null); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-xl">Done</button>
          </div>
        </div>
      )}
    </div>
  );
}
