'use client';
import { useState, useEffect } from 'react';
import { api, mediaUrl } from '@/lib/api';
import { StoryGroup } from '@/types';
import { useAuthStore } from '@/store/auth';

export default function StoriesBar() {
  const [groups, setGroups] = useState<StoryGroup[]>([]);
  const [viewer, setViewer] = useState<{ groupIdx: number; storyIdx: number } | null>(null);
  const { user } = useAuthStore();

  useEffect(() => {
    api.get<{ data: StoryGroup[] }>('/community/stories')
      .then(res => setGroups(Array.isArray(res.data) ? res.data : []))
      .catch(() => {});
  }, []);

  function open(groupIdx: number) { setViewer({ groupIdx, storyIdx: 0 }); }
  function close() { setViewer(null); }

  function advance() {
    if (!viewer) return;
    const group = groups[viewer.groupIdx];
    if (viewer.storyIdx < (group?.stories?.length ?? 0) - 1) {
      setViewer({ ...viewer, storyIdx: viewer.storyIdx + 1 });
    } else if (viewer.groupIdx < groups.length - 1) {
      setViewer({ groupIdx: viewer.groupIdx + 1, storyIdx: 0 });
    } else {
      close();
    }
  }

  function goBack() {
    if (!viewer) return;
    if (viewer.storyIdx > 0) {
      setViewer({ ...viewer, storyIdx: viewer.storyIdx - 1 });
    } else if (viewer.groupIdx > 0) {
      const prev = groups[viewer.groupIdx - 1];
      setViewer({ groupIdx: viewer.groupIdx - 1, storyIdx: (prev?.stories?.length ?? 1) - 1 });
    }
  }

  const activeGroup = viewer ? groups[viewer.groupIdx] : null;
  const activeStory = activeGroup?.stories[viewer?.storyIdx ?? 0];

  return (
    <>
      {/* Stories scroll bar */}
      <div
        className="flex gap-3 overflow-x-auto px-4 py-3 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800"
        style={{ scrollbarWidth: 'none' }}
      >
        {/* Add story button */}
        <div className="flex flex-col items-center gap-1 shrink-0 cursor-pointer">
          <div className="relative w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-800 border-2 border-dashed border-gray-300 dark:border-gray-600">
            {user?.avatar
              ? <img src={mediaUrl(user.avatar)} alt="" className="w-full h-full rounded-full object-cover" />
              : <div className="w-full h-full rounded-full flex items-center justify-center text-xl font-black text-gray-400">
                  {user?.name?.[0] ?? '?'}
                </div>
            }
            <div className="absolute bottom-0 right-0 w-5 h-5 bg-[#FF8A00] rounded-full flex items-center justify-center border-2 border-white dark:border-gray-900">
              <svg className="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
            </div>
          </div>
          <span className="text-[10px] text-gray-500 dark:text-gray-400 font-medium truncate w-14 text-center">Add Story</span>
        </div>

        {/* Story groups */}
        {groups.map((group, i) => {
          const p = group.profile;
          const name = p?.name ?? p?.display_name ?? 'User';
          return (
            <div key={p?.id ?? i} onClick={() => open(i)} className="flex flex-col items-center gap-1 shrink-0 cursor-pointer">
              <div className={`w-14 h-14 rounded-full p-0.5 ${group.has_unseen ? 'bg-gradient-to-tr from-[#FF8A00] to-[#FF5500]' : 'bg-gray-300 dark:bg-gray-600'}`}>
                <div className="w-full h-full rounded-full overflow-hidden bg-white dark:bg-gray-900 p-0.5">
                  {mediaUrl(p?.avatar)
                    ? <img src={mediaUrl(p?.avatar)} alt={name} className="w-full h-full rounded-full object-cover" />
                    : <div className="w-full h-full rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-sm font-black text-gray-500">{name[0]}</div>
                  }
                </div>
              </div>
              <span className="text-[10px] text-gray-600 dark:text-gray-400 truncate w-14 text-center">{name.split(' ')[0]}</span>
            </div>
          );
        })}
      </div>

      {/* Story viewer */}
      {viewer && activeGroup && activeStory && (
        <div className="fixed inset-0 z-50 bg-black flex items-center justify-center" onClick={advance}>
          {/* Progress bars */}
          <div className="absolute top-4 left-4 right-4 flex gap-1 z-10">
            {activeGroup.stories.map((_, si) => (
              <div key={si} className="flex-1 h-0.5 bg-white/30 rounded-full overflow-hidden">
                <div
                  className={`h-full bg-white rounded-full transition-none ${
                    si < viewer.storyIdx ? 'w-full' :
                    si === viewer.storyIdx ? 'w-full duration-[5000ms]' : 'w-0'
                  }`}
                />
              </div>
            ))}
          </div>

          {/* Header */}
          <div className="absolute top-8 left-4 right-12 z-10 flex items-center gap-2 pt-2">
            <div className="w-8 h-8 rounded-full overflow-hidden border border-white/50 bg-gray-700">
              {mediaUrl(activeGroup.profile?.avatar)
                ? <img src={mediaUrl(activeGroup.profile?.avatar)} alt="" className="w-full h-full object-cover" />
                : <div className="w-full h-full flex items-center justify-center text-white text-xs font-bold">
                    {(activeGroup.profile?.name ?? '?')[0]}
                  </div>
              }
            </div>
            <div>
              <p className="text-white text-xs font-bold">{activeGroup.profile?.name ?? activeGroup.profile?.display_name}</p>
              <p className="text-white/60 text-[10px]">{viewer.storyIdx + 1} / {activeGroup.stories.length}</p>
            </div>
          </div>

          {/* Close */}
          <button
            className="absolute top-10 right-4 z-10 w-8 h-8 flex items-center justify-center"
            onClick={e => { e.stopPropagation(); close(); }}
          >
            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          {/* Left tap → back */}
          <div className="absolute left-0 top-0 w-1/3 h-full z-10" onClick={e => { e.stopPropagation(); goBack(); }} />

          {/* Story media */}
          {activeStory.media_type === 'video' ? (
            <video
              key={activeStory.id}
              src={mediaUrl(activeStory.media_url)}
              autoPlay
              playsInline
              onEnded={advance}
              className="max-h-screen max-w-full object-contain"
            />
          ) : activeStory.media_type === 'text' ? (
            <div
              className="w-full max-w-sm aspect-[9/16] rounded-2xl flex items-center justify-center p-8"
              style={{ background: activeStory.bg_color ?? '#07003B' }}
            >
              <p className="text-white text-2xl font-black text-center leading-snug">{activeStory.text_content}</p>
            </div>
          ) : (
            <img
              key={activeStory.id}
              src={mediaUrl(activeStory.media_url)}
              alt=""
              className="max-h-screen max-w-full object-contain"
            />
          )}
        </div>
      )}
    </>
  );
}
