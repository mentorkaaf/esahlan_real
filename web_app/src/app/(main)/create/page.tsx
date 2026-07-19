'use client';
import { useState, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';

type PostType = 'post' | 'reel';

export default function CreatePage() {
  const [type, setType] = useState<PostType>('post');
  const [content, setContent] = useState('');
  const [files, setFiles] = useState<File[]>([]);
  const [previews, setPreviews] = useState<string[]>([]);
  const [posting, setPosting] = useState(false);
  const [error, setError] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);
  const router = useRouter();

  function pickFiles(e: React.ChangeEvent<HTMLInputElement>) {
    const picked = Array.from(e.target.files ?? []);
    if (!picked.length) return;
    setFiles(prev => [...prev, ...picked].slice(0, 10));
    const urls = picked.map(f => URL.createObjectURL(f));
    setPreviews(prev => [...prev, ...urls].slice(0, 10));
  }

  function removeFile(i: number) {
    setFiles(prev => prev.filter((_, idx) => idx !== i));
    setPreviews(prev => {
      URL.revokeObjectURL(prev[i]);
      return prev.filter((_, idx) => idx !== i);
    });
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!content.trim() && files.length === 0) { setError('Add some content or media.'); return; }
    setPosting(true);
    setError('');
    try {
      // Backend type: text | image | video | reel
      const hasVideo = files.some(f => f.type.startsWith('video'));
      const hasImage = files.some(f => f.type.startsWith('image'));
      let backendType: string = 'text';
      if (type === 'reel') backendType = 'reel';
      else if (hasVideo) backendType = 'video';
      else if (hasImage) backendType = 'image';

      const form = new FormData();
      form.append('type', backendType);
      form.append('content', content);
      files.forEach(f => form.append('media[]', f));
      await api.postForm('/community/posts', form);
      router.push('/feed');
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to post. Try again.');
    }
    setPosting(false);
  }

  const accept = type === 'reel' ? 'video/*' : 'image/*,video/*';

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 py-4 z-10 flex items-center justify-between">
        <button onClick={() => router.back()} className="text-gray-400 hover:text-gray-600">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <h1 className="text-base font-black text-gray-900 dark:text-white">New {type === 'reel' ? 'Reel' : 'Post'}</h1>
        <button
          onClick={submit}
          disabled={posting || (!content.trim() && files.length === 0)}
          className="px-4 py-1.5 bg-[#FF8A00] disabled:opacity-40 text-white text-sm font-black rounded-full transition-opacity"
        >
          {posting ? 'Posting…' : 'Post'}
        </button>
      </div>

      <div className="px-4 py-5 space-y-5">
        {/* Type tabs */}
        <div className="flex gap-2 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl">
          {(['post', 'reel'] as PostType[]).map(t => (
            <button
              key={t}
              onClick={() => { setType(t); setFiles([]); setPreviews([]); }}
              className={`flex-1 py-2 rounded-lg text-sm font-black capitalize transition-colors ${
                type === t ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500'
              }`}
            >
              {t === 'reel' ? '🎬 Reel' : '📝 Post'}
            </button>
          ))}
        </div>

        {/* Text area */}
        <textarea
          value={content}
          onChange={e => setContent(e.target.value)}
          placeholder={type === 'reel' ? 'Add a caption for your reel…' : "What's on your mind?"}
          rows={4}
          className="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30 focus:border-[#FF8A00] resize-none"
        />

        {/* Media previews */}
        {previews.length > 0 && (
          <div className="grid grid-cols-3 gap-2">
            {previews.map((url, i) => (
              <div key={i} className="relative aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800">
                {files[i]?.type.startsWith('video') ? (
                  <video src={url} className="w-full h-full object-cover" />
                ) : (
                  <img src={url} alt="" className="w-full h-full object-cover" />
                )}
                <button
                  onClick={() => removeFile(i)}
                  className="absolute top-1.5 right-1.5 w-6 h-6 bg-black/60 rounded-full flex items-center justify-center text-white"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Add media button */}
        <button
          type="button"
          onClick={() => fileRef.current?.click()}
          className="w-full py-4 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl flex flex-col items-center gap-2 text-gray-400 hover:border-[#FF8A00] hover:text-[#FF8A00] transition-colors"
        >
          <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span className="text-sm font-bold">
            {type === 'reel' ? 'Add Video' : 'Add Photos / Videos'}
          </span>
          <span className="text-xs">{type === 'reel' ? 'MP4, MOV' : 'JPEG, PNG, MP4, MOV · up to 10 files'}</span>
        </button>
        <input
          ref={fileRef}
          type="file"
          accept={accept}
          multiple={type !== 'reel'}
          onChange={pickFiles}
          className="hidden"
        />

        {error && (
          <p className="text-sm text-red-500 font-medium bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-2">{error}</p>
        )}
      </div>
    </div>
  );
}
