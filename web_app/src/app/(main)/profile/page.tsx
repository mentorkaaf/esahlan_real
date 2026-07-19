'use client';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/auth';
import { useEffect } from 'react';

export default function MyProfilePage() {
  const { user } = useAuthStore();
  const router = useRouter();

  useEffect(() => {
    if (user?.id) router.replace(`/profile/${user.id}`);
  }, [user, router]);

  return (
    <div className="flex items-center justify-center h-full">
      <div className="w-6 h-6 border-2 border-[#FF8A00] border-t-transparent rounded-full animate-spin" />
    </div>
  );
}
