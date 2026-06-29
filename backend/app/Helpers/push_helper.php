<?php

if (!function_exists('send_community_push')) {
    function send_community_push(int $userId, string $title, string $body, array $data = []): void
    {
        if ($userId === (int) auth()->id()) return;
        try {
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->fcm_token) return;
            
            $pushData = array_merge($data, ['deep_link' => build_deep_link($data)]);
            $ns = app(\App\Services\Notification\NotificationService::class);
            $ns->sendPush([$user->fcm_token], $title, $body, $pushData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Push failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('send_followers_push')) {
    function send_followers_push(int $authorId, string $title, string $body, array $data = []): void
    {
        try {
            $followerIds = \App\Models\CommunityFollow::where('following_id', $authorId)->pluck('follower_id');
            $tokens = \App\Models\User::whereIn('id', $followerIds)->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
            if (empty($tokens)) return;

            $pushData = array_merge($data, ['deep_link' => build_deep_link($data)]);
            $ns = app(\App\Services\Notification\NotificationService::class);
            $ns->sendPush($tokens, $title, $body, $pushData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Followers push failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('build_deep_link')) {
    function build_deep_link(array $data): string
    {
        $type = $data['type'] ?? '';
        if ($type === 'post' && isset($data['post_id'])) return '/community/post/' . $data['post_id'];
        if ($type === 'follow') return '/community/profile';
        if ($type === 'chat' && isset($data['chat_id'])) return '/community/chat/' . $data['chat_id'];
        return '/community';
    }
}
