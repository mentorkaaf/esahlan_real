'use client';
import Link from 'next/link';

const MODULES = [
  { href: '/shop/efood',      label: 'eFood',      description: 'Order food from local restaurants',    emoji: '🍕', color: 'from-orange-400 to-red-500' },
  { href: '/shop/egrocery',   label: 'eGrocery',   description: 'Fresh groceries delivered to you',     emoji: '🛒', color: 'from-green-400 to-emerald-600' },
  { href: '/shop/eshop',      label: 'eShop',      description: 'Shop from local stores',               emoji: '🛍️', color: 'from-blue-400 to-indigo-600' },
  { href: '/shop/eparcel',    label: 'eParcel',    description: 'Send and receive parcels',             emoji: '📦', color: 'from-yellow-400 to-amber-500' },
  { href: '/shop/emoving',    label: 'eMoving',    description: 'Moving and relocation services',       emoji: '🚛', color: 'from-purple-400 to-violet-600' },
  { href: '/shop/erent',      label: 'eRent',      description: 'Rent properties and equipment',       emoji: '🏠', color: 'from-slate-400 to-gray-600' },
  { href: '/shop/eexchange',  label: 'eExchange',  description: 'Exchange between mobile wallets',     emoji: '💱', color: 'from-pink-400 to-rose-500' },
  { href: '/shop/elearning',  label: 'eLearning',  description: 'Online courses and tutoring',         emoji: '🎓', color: 'from-teal-400 to-cyan-600' },
  { href: '/shop/ehealth',    label: 'eHealth',    description: 'Doctors, nurses & ambulance',         emoji: '🏥', color: 'from-red-400 to-pink-600' },
  { href: '/shop/elaundry',   label: 'eLaundry',   description: 'Laundry pickup & delivery',           emoji: '👕', color: 'from-sky-400 to-blue-600' },
  { href: '/shop/eticket',    label: 'eTicket',    description: 'Book flights and travel',             emoji: '✈️', color: 'from-indigo-400 to-purple-600' },
  { href: '/shop/edata',      label: 'eData',      description: 'Buy internet data bundles',           emoji: '📶', color: 'from-lime-400 to-green-600' },
  { href: '/shop/ewholesale', label: 'eWholesale', description: 'Bulk products for businesses',        emoji: '🏭', color: 'from-amber-400 to-orange-600' },
];

export default function ShopPage() {
  return (
    <div className="max-w-2xl mx-auto px-4 py-6">
      <div className="mb-6">
        <h1 className="text-2xl font-black text-gray-900 dark:text-white">eSahlan Shop</h1>
        <p className="text-sm text-gray-400 mt-1">Everything you need, in one place</p>
      </div>

      <div className="grid grid-cols-2 gap-3">
        {MODULES.map(mod => (
          <Link
            key={mod.href}
            href={mod.href}
            className="relative overflow-hidden rounded-2xl p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 hover:shadow-md transition-shadow group"
          >
            <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${mod.color} flex items-center justify-center text-2xl mb-3 group-hover:scale-110 transition-transform`}>
              {mod.emoji}
            </div>
            <p className="font-black text-gray-900 dark:text-white text-sm">{mod.label}</p>
            <p className="text-xs text-gray-400 mt-0.5 leading-snug">{mod.description}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}
