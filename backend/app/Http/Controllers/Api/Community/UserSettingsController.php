<?php
namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSettingsController extends Controller
{
    private const DEFAULTS = [
        'privacy' => [
            'private_account'    => false,
            'who_can_follow'     => 'everyone',   // everyone | followers | nobody
            'who_can_message'    => 'everyone',
            'who_can_comment'    => 'everyone',
            'who_can_mention'    => 'everyone',
            'who_can_tag'        => 'followers',
            'who_can_remix'      => 'everyone',
            'hide_online_status' => true,
            'hide_followers'     => false,
            'hide_following'     => false,
            'hide_likes'         => false,
        ],
        'notifications' => [
            'likes'            => true,
            'comments'         => true,
            'replies'          => true,
            'mentions'         => true,
            'messages'         => true,
            'follows'          => true,
            'follow_requests'  => true,
            'creator_uploads'  => true,
            'business_orders'  => true,
            'live_streams'     => true,
            'promotions'       => false,
            'email'            => true,
            'sms'              => false,
        ],
        'data_saver' => [
            'enabled'          => false,
            'image_quality'    => 'high',     // high | medium | low
            'video_quality'    => 'auto',     // auto | 144p | 240p | 360p | 480p | 720p | 1080p
            'disable_autoplay' => false,
            'wifi_only_download' => false,
            'preload_wifi_only'  => false,
        ],
        'appearance' => [
            'theme'          => 'system',    // light | dark | system
            'accent_color'   => '#FF8A00',
            'font_size'      => 'medium',    // small | medium | large | xlarge
            'reduce_motion'  => false,
        ],
        'video' => [
            'autoplay'        => 'wifi_only', // always | wifi_only | never
            'resolution'      => 'auto',
            'default_volume'  => 100,
            'pip_enabled'     => true,
            'loop'            => false,
        ],
        'audio' => [
            'background_playback' => true,
            'playback_speed'      => '1.0',
            'voice_enhancement'   => false,
            'download_podcasts'   => false,
        ],
        'messages' => [
            'read_receipts'       => true,
            'typing_indicator'    => true,
            'message_requests'    => true,
            'disappearing'        => 'off',  // off | 24h | 7d | 30d
            'who_can_message'     => 'everyone',
        ],
        'accessibility' => [
            'large_text'       => false,
            'screen_reader'    => false,
            'captions'         => false,
            'high_contrast'    => false,
            'voice_commands'   => false,
        ],
        'ai_features' => [
            'recommendations'  => true,
            'translation'      => true,
            'captions'         => true,
            'assistant'        => true,
            'summary'          => true,
        ],
        'language' => [
            'code' => 'en',   // en | so | ar | am | sw | fr
        ],
        'content' => [
            'auto_translate'    => false,
            'suggest_reels'     => true,
            'personalized_ads'  => true,
        ],
        'security' => [
            'security_alerts'   => true,
        ],
        'safety' => [
            'hidden_words'       => [],
            'comment_filter'     => false,
            'sensitive_content'  => 'standard', // off | standard | strict
        ],
    ];

    // GET /community/settings
    public function index()
    {
        $row = DB::table('user_settings')->where('user_id', auth()->id())->first();
        $settings = [];

        foreach (self::DEFAULTS as $key => $defaults) {
            $stored = $row ? json_decode($row->$key ?? 'null', true) : null;
            $settings[$key] = array_merge($defaults, is_array($stored) ? $stored : []);
        }

        return response()->json(['status' => 'success', 'data' => $settings]);
    }

    // PUT /community/settings
    public function update(Request $request)
    {
        $userId = auth()->id();

        // Get or create settings row
        $row = DB::table('user_settings')->where('user_id', $userId)->first();
        $data = ['user_id' => $userId, 'updated_at' => now()];

        foreach (array_keys(self::DEFAULTS) as $key) {
            if ($request->has($key)) {
                $incoming = $request->input($key);
                if (!is_array($incoming)) continue;

                // Merge with existing stored value
                $existing = $row ? json_decode($row->$key ?? 'null', true) : null;
                $merged   = array_merge(self::DEFAULTS[$key], is_array($existing) ? $existing : [], $incoming);
                $data[$key] = json_encode($merged);
            }
        }

        if ($row) {
            DB::table('user_settings')->where('user_id', $userId)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('user_settings')->insert($data);
        }

        return response()->json(['status' => 'success', 'message' => 'Settings saved.']);
    }

    // GET /community/settings/blocked-users
    public function blockedUsers()
    {
        $blocked = DB::table('community_blocks')
            ->join('users', 'community_blocks.blocked_id', '=', 'users.id')
            ->where('community_blocks.blocker_id', auth()->id())
            ->select('users.id', 'users.name', 'users.avatar', 'community_blocks.created_at')
            ->orderByDesc('community_blocks.created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $blocked]);
    }

    // GET /community/settings/muted-users
    public function mutedUsers()
    {
        $muted = DB::table('community_mutes')
            ->join('users', 'community_mutes.muted_id', '=', 'users.id')
            ->where('community_mutes.muter_id', auth()->id())
            ->select('users.id', 'users.name', 'users.avatar', 'community_mutes.created_at')
            ->orderByDesc('community_mutes.created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $muted]);
    }

    // DELETE /community/settings/blocked-users/{id}
    public function unblock($id)
    {
        DB::table('community_blocks')
            ->where('blocker_id', auth()->id())
            ->where('blocked_id', $id)
            ->delete();
        return response()->json(['status' => 'success', 'message' => 'Unblocked']);
    }

    // DELETE /community/settings/muted-users/{id}
    public function unmute($id)
    {
        DB::table('community_mutes')
            ->where('muter_id', auth()->id())
            ->where('muted_id', $id)
            ->delete();
        return response()->json(['status' => 'success', 'message' => 'Unmuted']);
    }

    // GET /community/settings/restricted-users
    public function restrictedUsers()
    {
        $restricted = DB::table('ts_restrictions')
            ->join('users', 'ts_restrictions.user_id', '=', 'users.id')
            ->where('ts_restrictions.active', true)
            ->where(fn ($q) => $q->whereNull('ts_restrictions.expires_at')->orWhere('ts_restrictions.expires_at', '>', now()))
            ->select('users.id', 'users.name', 'users.avatar', 'ts_restrictions.type', 'ts_restrictions.expires_at')
            ->orderByDesc('ts_restrictions.created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $restricted]);
    }
}
