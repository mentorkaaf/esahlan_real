<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityComment;
use App\Models\CommunityPostReaction;
use App\Models\User;
use App\Models\CommunityProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EngagementGeneratorController extends Controller
{
    private static $somaliNames = [
        ['name' => 'Cabdi Maxamed', 'g' => 'm'], ['name' => 'Faadumo Cali', 'g' => 'f'],
        ['name' => 'Maxamed Xasan', 'g' => 'm'], ['name' => 'Hodan Yuusuf', 'g' => 'f'],
        ['name' => 'Cabdirashiid Nuur', 'g' => 'm'], ['name' => 'Sahra Cabdi', 'g' => 'f'],
        ['name' => 'Axmed Jaamac', 'g' => 'm'], ['name' => 'Nimco Faarax', 'g' => 'f'],
        ['name' => 'Yuusuf Ismaciil', 'g' => 'm'], ['name' => 'Ikraan Maxamed', 'g' => 'f'],
        ['name' => 'Cismaan Diiriye', 'g' => 'm'], ['name' => 'Xamdi Aadan', 'g' => 'f'],
        ['name' => 'Daahir Warsame', 'g' => 'm'], ['name' => 'Sucaad Xirsi', 'g' => 'f'],
        ['name' => 'Khadar Cabdalle', 'g' => 'm'], ['name' => 'Amina Shiikh', 'g' => 'f'],
        ['name' => 'Jaamac Siciid', 'g' => 'm'], ['name' => 'Sagal Cismaan', 'g' => 'f'],
        ['name' => 'Shariif Cumar', 'g' => 'm'], ['name' => 'Maryan Xaashi', 'g' => 'f'],
        ['name' => 'Cabdullaahi Shire', 'g' => 'm'], ['name' => 'Deeqa Maxamuud', 'g' => 'f'],
        ['name' => 'Mustafe Axmed', 'g' => 'm'], ['name' => 'Raxma Cabdi', 'g' => 'f'],
        ['name' => 'Xirsi Geelle', 'g' => 'm'], ['name' => 'Hibaq Nuur', 'g' => 'f'],
        ['name' => 'Warsame Saleebaan', 'g' => 'm'], ['name' => 'Xaawo Jaamac', 'g' => 'f'],
        ['name' => 'Liibaan Aadan', 'g' => 'm'], ['name' => 'Nasra Yuusuf', 'g' => 'f'],
        ['name' => 'Guuleed Maxamed', 'g' => 'm'], ['name' => 'Filsan Cali', 'g' => 'f'],
        ['name' => 'Biihi Xasan', 'g' => 'm'], ['name' => 'Sumaya Diiriye', 'g' => 'f'],
        ['name' => 'Cabdiqaadir Ibraahim', 'g' => 'm'], ['name' => 'Asli Faarax', 'g' => 'f'],
        ['name' => 'Muxumed Warsame', 'g' => 'm'], ['name' => 'Halima Cabdullaahi', 'g' => 'f'],
        ['name' => 'Siciid Nuur', 'g' => 'm'], ['name' => 'Zamzam Axmed', 'g' => 'f'],
    ];

    private static $somaliComments = [
        'Waa run!', 'Mashaa Allah', 'Aad ayaan ugu faraxsanahay', 'Waa xaqiiq',
        'Ilaahow naga ilaali', 'Waxaan aad u jeclahay', 'Taageero buuxda',
        'Allah ha ku barakeeyo', 'Waa fiican tahay', 'Wanaagsan',
        'Runtii waa sidaas', 'Mahadsanid', 'Waad mahadsan tahay',
        'Aad iyo aad', 'Waa hagaag', 'Allah ha noo fududeeyo',
        'Qof wanaagsan', 'Waa lagu faraxsanyahay', 'Taasi waa run',
        'Waa muuqaal wanaagsan', 'Aad baan u jeclahay', 'War waa yaab',
        'Subxaan Allah', 'Masha Allah aad ayuu u fiicanyahay',
        'Waa wax fiican', 'Alla mahad', 'Waa cajiib',
        'Rabi ha ku xafido', 'Waa dareen wanaagsan',
    ];

    private function getBotUsers(int $count): array
    {
        $tag = 'esahlan_bot';
        $existing = User::where('email', 'like', "%@{$tag}.local")->inRandomOrder()->limit($count)->get();
        if ($existing->count() >= $count) return $existing->take($count)->all();

        $needed = $count - $existing->count();
        $users = $existing->all();
        $names = collect(self::$somaliNames)->shuffle();

        for ($i = 0; $i < $needed && $i < $names->count(); $i++) {
            $n = $names[$i];
            $slug = strtolower(str_replace(' ', '', $n['name'])) . rand(100, 999);
            $seed = $slug . rand(1, 100);
            $avatar = "https://api.dicebear.com/7.x/avataaars/png?seed={$seed}&backgroundColor=b6e3f4,c0aede,d1d4f9";

            $user = User::create([
                'name' => $n['name'],
                'email' => "{$slug}@{$tag}.local",
                'phone' => '252' . rand(61, 69) . rand(1000000, 9999999),
                'password' => Hash::make('bot_' . $slug),
                'avatar' => $avatar,
            ]);

            CommunityProfile::create([
                'user_id' => $user->id,
                'username' => $slug,
                'bio' => '',
                'onboarding_completed' => true,
            ]);

            $users[] = $user;
        }

        return array_slice($users, 0, $count);
    }

    public function generateLikes(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:community_posts,id', 'count' => 'required|integer|min:1|max:500']);
        $post = CommunityPost::findOrFail($request->post_id);
        $users = $this->getBotUsers($request->count);
        $added = 0;

        foreach ($users as $user) {
            $exists = CommunityPostReaction::where('user_id', $user->id)->where('post_id', $post->id)->exists();
            if (!$exists) {
                CommunityPostReaction::create(['user_id' => $user->id, 'post_id' => $post->id, 'type' => collect(['like', 'love', 'haha', 'wow'])->random()]);
                $added++;
            }
        }

        $post->increment('likes_count', $added);
        return response()->json(['status' => 'success', 'message' => "Added {$added} likes", 'total' => $post->fresh()->likes_count]);
    }

    public function generateViews(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:community_posts,id', 'count' => 'required|integer|min:1|max:10000']);
        $post = CommunityPost::findOrFail($request->post_id);
        $post->increment('views_count', $request->count);
        return response()->json(['status' => 'success', 'message' => "Added {$request->count} views", 'total' => $post->fresh()->views_count]);
    }

    public function generateComments(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:community_posts,id', 'count' => 'required|integer|min:1|max:100']);
        $post = CommunityPost::findOrFail($request->post_id);
        $users = $this->getBotUsers($request->count);
        $comments = $request->custom_comments ?? self::$somaliComments;

        foreach ($users as $user) {
            CommunityComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)],
                'created_at' => now()->subMinutes(rand(1, 120)),
            ]);
        }

        $post->increment('comments_count', $request->count);
        return response()->json(['status' => 'success', 'message' => "Added {$request->count} comments", 'total' => $post->fresh()->comments_count]);
    }

    public function generateAll(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:community_posts,id',
            'likes' => 'nullable|integer|min:0|max:500',
            'views' => 'nullable|integer|min:0|max:10000',
            'comments' => 'nullable|integer|min:0|max:100',
        ]);

        $post = CommunityPost::findOrFail($request->post_id);
        $results = [];

        if ($request->likes > 0) {
            $users = $this->getBotUsers($request->likes);
            $added = 0;
            foreach ($users as $user) {
                if (!CommunityPostReaction::where('user_id', $user->id)->where('post_id', $post->id)->exists()) {
                    CommunityPostReaction::create(['user_id' => $user->id, 'post_id' => $post->id, 'type' => collect(['like', 'love', 'haha'])->random()]);
                    $added++;
                }
            }
            $post->increment('likes_count', $added);
            $results['likes_added'] = $added;
        }

        if ($request->views > 0) {
            $post->increment('views_count', $request->views);
            $results['views_added'] = $request->views;
        }

        if ($request->comments > 0) {
            $users = $this->getBotUsers($request->comments);
            foreach ($users as $user) {
                CommunityComment::create([
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'content' => self::$somaliComments[array_rand(self::$somaliComments)],
                    'created_at' => now()->subMinutes(rand(1, 180)),
                ]);
            }
            $post->increment('comments_count', $request->comments);
            $results['comments_added'] = $request->comments;
        }

        $fresh = $post->fresh();
        return response()->json(['status' => 'success', 'data' => $results, 'post' => [
            'id' => $fresh->id, 'likes' => $fresh->likes_count, 'views' => $fresh->views_count, 'comments' => $fresh->comments_count,
        ]]);
    }

    public function botUsers()
    {
        $users = User::where('email', 'like', '%@esahlan_bot.local')->get(['id', 'name', 'avatar']);
        return response()->json(['status' => 'success', 'count' => $users->count(), 'data' => $users]);
    }

    /**
     * Strip bot-generated engagement off one post, leaving real-user
     * engagement untouched. Recomputes counts from actual remaining rows
     * (rather than decrementing) to avoid drift from any prior mismatch.
     *
     * Views have no per-row fake/real record at all — generateViews() just
     * bumps a raw counter — so there's nothing to "delete" for views. Reset
     * recomputes views_count from genuinely-tracked feed_interactions
     * (view/watch events from real, non-bot users) instead of zeroing it,
     * so organic impressions picked up via InteractionTracker survive a reset.
     */
    private function resetPostEngagement(int $postId): array
    {
        $botUserIds = User::where('email', 'like', '%@esahlan_bot.local')->pluck('id');

        $likesRemoved = CommunityPostReaction::where('post_id', $postId)
            ->whereIn('user_id', $botUserIds)->delete();
        $commentsRemoved = CommunityComment::where('post_id', $postId)
            ->whereIn('user_id', $botUserIds)->delete();

        $post = CommunityPost::find($postId);
        if (!$post) return ['likes_removed' => 0, 'comments_removed' => 0];

        $post->likes_count = CommunityPostReaction::where('post_id', $postId)->count();
        $post->comments_count = CommunityComment::where('post_id', $postId)->count();
        $post->views_count = DB::table('feed_interactions')
            ->where('post_id', $postId)
            ->whereIn('type', ['view', 'watch'])
            ->whereNotIn('user_id', $botUserIds)
            ->distinct('user_id')
            ->count('user_id');
        $post->save();

        return ['likes_removed' => $likesRemoved, 'comments_removed' => $commentsRemoved];
    }

    public function resetEngagement(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:community_posts,id']);
        $result = $this->resetPostEngagement((int) $request->post_id);
        $post = CommunityPost::findOrFail($request->post_id);

        return response()->json([
            'status' => 'success',
            'message' => "Removed {$result['likes_removed']} fake likes, {$result['comments_removed']} fake comments",
            'post' => ['id' => $post->id, 'likes' => $post->likes_count, 'comments' => $post->comments_count, 'views' => $post->views_count],
        ]);
    }

    public function resetAllEngagement()
    {
        $postIds = CommunityPost::pluck('id');
        foreach ($postIds as $id) {
            $this->resetPostEngagement($id);
        }

        return response()->json(['status' => 'success', 'message' => "Reset fake engagement on {$postIds->count()} posts"]);
    }
}
