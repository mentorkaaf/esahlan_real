'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api, mediaUrl } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface Category { id: number; name: string; }
interface Course {
  id: number; title: string; slug: string; thumbnail?: string;
  instructor?: string; price?: number; is_free?: boolean;
  level?: string; rating?: number; students_count?: number;
  lessons_count?: number; duration?: string;
}

export default function ELearningPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const [levelFilter, setLevelFilter] = useState('');
  const [freeOnly, setFreeOnly] = useState(false);
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: Category[] }>('/elearning/categories').catch(() => ({ data: [] })).then(r => setCategories(Array.isArray(r.data) ? r.data : []));
    loadCourses();
  }, []);

  async function loadCourses() {
    setLoading(true);
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (activeCategory) params.set('category', String(activeCategory));
    if (levelFilter) params.set('level', levelFilter);
    if (freeOnly) params.set('isFree', '1');
    try {
      const res = await api.get<{ data: Course[] }>(`/elearning/courses?${params}`);
      setCourses(Array.isArray(res.data) ? res.data : []);
    } catch { setCourses([]); }
    setLoading(false);
  }

  useEffect(() => { loadCourses(); }, [search, activeCategory, levelFilter, freeOnly]); // eslint-disable-line react-hooks/exhaustive-deps

  const LEVELS = ['Beginner', 'Intermediate', 'Advanced'];

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eLearning" emoji="🎓" onBack={() => router.push('/shop')} />

      <div className="px-4 pb-3 space-y-3">
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search courses…"
          className="w-full px-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-xl text-sm focus:outline-none text-gray-900 dark:text-white" />

        {/* Filters row */}
        <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
          <button onClick={() => setFreeOnly(f => !f)}
            className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold transition-colors ${freeOnly ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
            Free
          </button>
          {LEVELS.map(l => (
            <button key={l} onClick={() => setLevelFilter(f => f === l.toLowerCase() ? '' : l.toLowerCase())}
              className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold transition-colors ${levelFilter === l.toLowerCase() ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
              {l}
            </button>
          ))}
        </div>

        {/* Categories */}
        {categories.length > 0 && (
          <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button onClick={() => setActiveCategory(null)}
              className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold ${activeCategory === null ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
              All
            </button>
            {categories.map(c => (
              <button key={c.id} onClick={() => setActiveCategory(c.id === activeCategory ? null : c.id)}
                className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-bold ${activeCategory === c.id ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
                {c.name}
              </button>
            ))}
          </div>
        )}
      </div>

      {loading ? <Spinner /> : courses.length === 0 ? <Empty text="No courses found" /> : (
        <div className="px-4 space-y-3">
          {courses.map(c => (
            <button key={c.id} onClick={() => router.push(`/shop/elearning/${c.slug}`)}
              className="w-full bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden text-left flex hover:shadow-md transition-shadow">
              <div className="w-28 h-24 bg-gray-100 dark:bg-gray-800 shrink-0">
                {c.thumbnail && <img src={mediaUrl(c.thumbnail)} alt={c.title} className="w-full h-full object-cover" />}
              </div>
              <div className="flex-1 p-3 min-w-0">
                <p className="font-black text-sm text-gray-900 dark:text-white leading-snug line-clamp-2">{c.title}</p>
                {c.instructor && <p className="text-xs text-gray-400 mt-0.5">{c.instructor}</p>}
                <div className="flex items-center gap-2 mt-1 flex-wrap">
                  {c.level && <span className="text-[10px] font-bold px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full capitalize">{c.level}</span>}
                  {c.rating && <span className="text-[10px] text-yellow-500">⭐ {c.rating}</span>}
                  {c.students_count && <span className="text-[10px] text-gray-400">{c.students_count} students</span>}
                </div>
                <div className="mt-1.5">
                  {c.is_free
                    ? <span className="text-sm font-black text-green-500">Free</span>
                    : <span className="text-sm font-black text-[#FF8A00]">${c.price}</span>
                  }
                </div>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
