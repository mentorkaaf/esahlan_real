'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ModuleHeader, Spinner } from '../efood/page';

type ServiceType = 'normal' | 'express';
interface LaundryItem { id: number; name: string; normal_price: number; express_price: number; normal_days?: number; express_hours?: number; }

export default function ELaundryPage() {
  const [items, setItems] = useState<LaundryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [serviceType, setServiceType] = useState<ServiceType>('normal');
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [address, setAddress] = useState('');
  const [phone, setPhone] = useState('');
  const [ordering, setOrdering] = useState(false);
  const [success, setSuccess] = useState(false);
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: LaundryItem[] }>('/elaundry/items').then(r => setItems(Array.isArray(r.data) ? r.data : [])).catch(() => {}).finally(() => setLoading(false));
  }, []);

  const cartItems = items.filter(i => (quantities[i.id] ?? 0) > 0);
  const total = cartItems.reduce((sum, i) => {
    const price = serviceType === 'express' ? i.express_price : i.normal_price;
    return sum + price * (quantities[i.id] ?? 0);
  }, 0);
  const cartCount = Object.values(quantities).reduce((a, b) => a + b, 0);

  function setQty(id: number, delta: number) {
    setQuantities(prev => {
      const n = Math.max(0, (prev[id] ?? 0) + delta);
      const next = { ...prev, [id]: n };
      if (n === 0) delete next[id];
      return next;
    });
  }

  async function placeOrder() {
    if (!address || !phone || cartItems.length === 0) return;
    setOrdering(true);
    try {
      await api.post('/elaundry/order', {
        service_type: serviceType,
        items: cartItems.map(i => ({ item_id: i.id, quantity: quantities[i.id] })),
        pickup_address: address,
        contact_phone: phone,
      });
      setSuccess(true);
    } catch { }
    setOrdering(false);
  }

  if (success) return (
    <div className="max-w-2xl mx-auto">
      <ModuleHeader title="eLaundry" emoji="👕" onBack={() => router.push('/shop')} />
      <div className="flex flex-col items-center py-20 px-8 text-center">
        <div className="text-5xl mb-4">✅</div>
        <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Order Placed!</h2>
        <p className="text-gray-400 text-sm mb-1">We'll pick up your laundry soon.</p>
        <p className="text-xs text-gray-400">{serviceType === 'express' ? 'Express: ready within hours' : 'Normal: ready in 2-3 days'}</p>
        <button onClick={() => { setSuccess(false); setQuantities({}); }} className="mt-6 px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">New Order</button>
      </div>
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto pb-48">
      <ModuleHeader title="eLaundry" emoji="👕" onBack={() => router.push('/shop')} />

      {/* Service type */}
      <div className="px-4 mb-4">
        <div className="flex gap-2 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl">
          {(['normal', 'express'] as ServiceType[]).map(s => (
            <button key={s} onClick={() => setServiceType(s)}
              className={`flex-1 py-2.5 rounded-lg text-sm font-black transition-colors ${serviceType === s ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500'}`}>
              {s === 'express' ? '⚡ Express' : '🌀 Normal'}
            </button>
          ))}
        </div>
        <p className="text-xs text-center text-gray-400 mt-1.5">
          {serviceType === 'express' ? 'Ready in a few hours · Higher price' : 'Ready in 2-3 days · Standard price'}
        </p>
      </div>

      {loading ? <Spinner /> : (
        <div className="px-4 space-y-2">
          {items.map(item => {
            const qty = quantities[item.id] ?? 0;
            const price = serviceType === 'express' ? item.express_price : item.normal_price;
            return (
              <div key={item.id} className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-3 flex items-center gap-3">
                <div className="flex-1 min-w-0">
                  <p className="font-black text-sm text-gray-900 dark:text-white">{item.name}</p>
                  <p className="text-xs text-gray-400 mt-0.5">
                    ${price} each{serviceType === 'express' && item.express_hours ? ` · ${item.express_hours}h` : !serviceType && item.normal_days ? ` · ${item.normal_days} days` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {qty > 0 && (
                    <>
                      <button onClick={() => setQty(item.id, -1)} className="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-black flex items-center justify-center">−</button>
                      <span className="w-5 text-center font-black text-sm text-gray-900 dark:text-white">{qty}</span>
                    </>
                  )}
                  <button onClick={() => setQty(item.id, 1)} className="w-7 h-7 rounded-full bg-[#FF8A00] text-white font-black flex items-center justify-center">+</button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Bottom order form */}
      {cartCount > 0 && (
        <div className="fixed bottom-16 md:bottom-0 inset-x-0 max-w-2xl mx-auto bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 px-4 pt-3 pb-4 space-y-2 z-40">
          <div className="flex items-center justify-between mb-1">
            <p className="text-sm font-bold text-gray-600 dark:text-gray-300">{cartCount} item(s)</p>
            <p className="font-black text-[#FF8A00] text-lg">${total.toFixed(2)}</p>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <input value={address} onChange={e => setAddress(e.target.value)} placeholder="Pickup address"
              className="px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
            <input value={phone} onChange={e => setPhone(e.target.value)} placeholder="Contact phone" type="tel"
              className="px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
          </div>
          <button onClick={placeOrder} disabled={ordering || !phone || !address}
            className="w-full py-3 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40 transition-opacity">
            {ordering ? 'Placing Order…' : `Place Order · $${total.toFixed(2)}`}
          </button>
        </div>
      )}
    </div>
  );
}
