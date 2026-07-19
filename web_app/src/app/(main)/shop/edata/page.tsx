'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface Provider { id: number; name: string; logo?: string; color?: string; }
interface DataPackage { id: number; name: string; description?: string; }
interface Bundle { id: number; name: string; data?: string; price: number; validity?: string; speed?: string; }

export default function EDataPage() {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [packages, setPackages] = useState<DataPackage[]>([]);
  const [bundles, setBundles] = useState<Bundle[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedProvider, setSelectedProvider] = useState<Provider | null>(null);
  const [selectedPackage, setSelectedPackage] = useState<DataPackage | null>(null);
  const [selectedBundle, setSelectedBundle] = useState<Bundle | null>(null);
  const [phone, setPhone] = useState('');
  const [purchasing, setPurchasing] = useState(false);
  const [success, setSuccess] = useState(false);
  const [loadingPackages, setLoadingPackages] = useState(false);
  const [loadingBundles, setLoadingBundles] = useState(false);
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: Provider[] }>('/edata/providers').then(r => setProviders(Array.isArray(r.data) ? r.data : [])).catch(() => {}).finally(() => setLoading(false));
  }, []);

  async function selectProvider(p: Provider) {
    setSelectedProvider(p);
    setSelectedPackage(null);
    setSelectedBundle(null);
    setBundles([]);
    setLoadingPackages(true);
    try {
      const res = await api.get<{ data: DataPackage[] }>(`/edata/providers/${p.id}/packages`);
      setPackages(Array.isArray(res.data) ? res.data : []);
    } catch { setPackages([]); }
    setLoadingPackages(false);
  }

  async function selectPackage(pkg: DataPackage) {
    setSelectedPackage(pkg);
    setSelectedBundle(null);
    setLoadingBundles(true);
    try {
      const res = await api.get<{ data: Bundle[] }>(`/edata/packages/${pkg.id}/bundles`);
      setBundles(Array.isArray(res.data) ? res.data : []);
    } catch { setBundles([]); }
    setLoadingBundles(false);
  }

  async function purchase() {
    if (!selectedProvider || !selectedBundle || !phone) return;
    setPurchasing(true);
    try {
      await api.post('/edata/purchase', {
        bundle_id: selectedBundle.id,
        phone,
      });
      setSuccess(true);
    } catch { }
    setPurchasing(false);
  }

  if (success) return (
    <div className="max-w-2xl mx-auto">
      <ModuleHeader title="eData" emoji="📶" onBack={() => router.push('/shop')} />
      <div className="flex flex-col items-center py-20 px-8 text-center">
        <div className="text-5xl mb-4">✅</div>
        <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Purchase Successful!</h2>
        <p className="text-gray-400 text-sm mb-1">{selectedBundle?.data} data activated on {phone}</p>
        <p className="text-gray-400 text-sm mb-6">via {selectedProvider?.name}</p>
        <button onClick={() => { setSuccess(false); setSelectedBundle(null); setPhone(''); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">Buy More</button>
      </div>
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eData" emoji="📶" onBack={() => router.push('/shop')} />
      {loading ? <Spinner /> : (
        <div className="px-4 space-y-5">
          {/* Providers */}
          <div>
            <h2 className="font-black text-gray-900 dark:text-white mb-3">Select Provider</h2>
            {providers.length === 0 ? <Empty text="No providers available" /> : (
              <div className="grid grid-cols-2 gap-3">
                {providers.map(p => (
                  <button key={p.id} onClick={() => selectProvider(p)}
                    className={`p-4 rounded-2xl border-2 flex items-center gap-3 transition-colors ${selectedProvider?.id === p.id ? 'border-[#FF8A00] bg-[#FF8A00]/5' : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900'}`}>
                    {p.logo
                      ? <img src={mediaUrl(p.logo)} alt={p.name} className="w-10 h-10 rounded-xl object-cover" />
                      : <div className="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style={{ background: p.color ?? '#FF8A00' }}>📶</div>
                    }
                    <p className="font-black text-sm text-gray-900 dark:text-white">{p.name}</p>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Packages */}
          {selectedProvider && (
            <div>
              <h2 className="font-black text-gray-900 dark:text-white mb-3">Select Package Type</h2>
              {loadingPackages ? <Spinner /> : packages.length === 0 ? <Empty text="No packages available" /> : (
                <div className="flex gap-2 flex-wrap">
                  {packages.map(pkg => (
                    <button key={pkg.id} onClick={() => selectPackage(pkg)}
                      className={`px-4 py-2 rounded-xl text-sm font-black transition-colors ${selectedPackage?.id === pkg.id ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
                      {pkg.name}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Bundles */}
          {selectedPackage && (
            <div>
              <h2 className="font-black text-gray-900 dark:text-white mb-3">Choose a Bundle</h2>
              {loadingBundles ? <Spinner /> : bundles.length === 0 ? <Empty text="No bundles available" /> : (
                <div className="grid grid-cols-2 gap-3">
                  {bundles.map(b => (
                    <button key={b.id} onClick={() => setSelectedBundle(b)}
                      className={`p-3 rounded-2xl border-2 text-center transition-colors ${selectedBundle?.id === b.id ? 'border-[#FF8A00] bg-[#FF8A00]/5' : 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900'}`}>
                      <p className="text-2xl font-black text-gray-900 dark:text-white">{b.data ?? b.name}</p>
                      {b.validity && <p className="text-xs text-gray-400">{b.validity}</p>}
                      {b.speed && <p className="text-xs text-[#FF8A00] font-bold">{b.speed}</p>}
                      <p className="font-black text-[#FF8A00] mt-1">${b.price}</p>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Purchase */}
          {selectedBundle && (
            <div className="space-y-3 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-4">
              <h2 className="font-black text-gray-900 dark:text-white">Phone Number</h2>
              <input type="tel" value={phone} onChange={e => setPhone(e.target.value)} placeholder="+252…"
                className="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
              <button onClick={purchase} disabled={!phone || purchasing}
                className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
                {purchasing ? 'Processing…' : `Buy ${selectedBundle.data ?? selectedBundle.name} · $${selectedBundle.price}`}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
