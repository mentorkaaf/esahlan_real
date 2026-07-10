<?php
namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AutoRestrictService
{
    // Which restriction types block which actions
    private const BLOCKS = [
        'post'    => ['post_ban', 'read_only', 'temp_suspend', 'perm_suspend'],
        'comment' => ['comment_ban', 'post_ban', 'read_only', 'temp_suspend', 'perm_suspend'],
        'message' => ['message_ban', 'read_only', 'temp_suspend', 'perm_suspend'],
        'live'    => ['live_ban', 'read_only', 'temp_suspend', 'perm_suspend'],
        'any'     => ['read_only', 'temp_suspend', 'perm_suspend'],
    ];

    private const MESSAGES = [
        'comment_ban'  => 'You are banned from commenting.',
        'post_ban'     => 'You are banned from creating posts.',
        'live_ban'     => 'You are banned from going live.',
        'message_ban'  => 'You are banned from sending messages.',
        'shadow_reduce'=> null, // silent — user never told
        'read_only'    => 'Your account is in read-only mode.',
        'temp_suspend' => 'Your account is temporarily suspended.',
        'perm_suspend' => 'Your account has been permanently suspended.',
    ];

    /**
     * Check if user is restricted for a given action.
     * Aborts with 403 JSON if blocked. Silent if not blocked.
     *
     * Usage: AutoRestrictService::enforce(auth()->id(), 'post');
     *
     * @param  string  $action  post|comment|message|live|any
     */
    public static function enforce(int $userId, string $action): void
    {
        $blockedBy = self::BLOCKS[$action] ?? self::BLOCKS['any'];

        $restriction = DB::table('ts_restrictions')
            ->where('user_id', $userId)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('type', $blockedBy)
            ->orderByDesc('created_at')
            ->first(['type', 'expires_at', 'reason']);

        if (!$restriction) return;

        $message = self::MESSAGES[$restriction->type] ?? 'Action not allowed.';
        $expiresAt = $restriction->expires_at
            ? ' Expires: ' . $restriction->expires_at
            : '';

        abort(response()->json([
            'message'      => $message . $expiresAt,
            'restriction'  => $restriction->type,
            'expires_at'   => $restriction->expires_at,
        ], 403));
    }


    // Points → restriction type + duration (days, null = permanent)
    private const TIERS = [
        ['min' => 15, 'type' => 'temp_suspend',  'days' => 30],
        ['min' => 10, 'type' => 'read_only',      'days' => 30],
        ['min' => 7,  'type' => 'post_ban',        'days' => 14],
        ['min' => 3,  'type' => 'comment_ban',     'days' => 7],
    ];

    // Restriction severity order — higher index = more severe
    private const SEVERITY_ORDER = [
        'comment_ban', 'post_ban', 'read_only', 'temp_suspend', 'perm_suspend',
    ];

    /**
     * Called after every strike insert.
     * Calculates active points and applies / upgrades restriction if needed.
     *
     * @param  int       $userId
     * @param  int|null  $issuedBy   Moderator user id (or null for AI/system)
     * @param  string    $strikeSeverity  'low'|'medium'|'high'|'critical'
     */
    public static function evaluate(int $userId, ?int $issuedBy = null, string $strikeSeverity = 'medium'): void
    {
        // Critical strike → immediate temp_suspend regardless of points
        if ($strikeSeverity === 'critical') {
            self::applyRestriction($userId, 'temp_suspend', 30, $issuedBy, 'Critical violation — automatic temporary suspension.');
            return;
        }

        // Sum active (non-expired) strike points
        $totalPoints = (int) DB::table('ts_strikes')
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('points');

        // Find the tier this user falls into
        $targetType = null;
        foreach (self::TIERS as $tier) {
            if ($totalPoints >= $tier['min']) {
                $targetType = $tier;
                break;
            }
        }

        if ($targetType === null) {
            // Below 3 points — no restriction needed
            return;
        }

        // Check if user already has an active restriction of equal or higher severity
        $existing = DB::table('ts_restrictions')
            ->where('user_id', $userId)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->first(['id', 'type']);

        if ($existing) {
            $existingRank = array_search($existing->type, self::SEVERITY_ORDER);
            $newRank      = array_search($targetType['type'], self::SEVERITY_ORDER);

            if ($existingRank >= $newRank) {
                // Already restricted at same or higher level — do nothing
                return;
            }

            // Upgrade: deactivate current restriction
            DB::table('ts_restrictions')
                ->where('id', $existing->id)
                ->update(['active' => false, 'updated_at' => now()]);
        }

        self::applyRestriction(
            $userId,
            $targetType['type'],
            $targetType['days'],
            $issuedBy,
            "Auto-restriction: {$totalPoints} active strike points."
        );
    }

    private static function applyRestriction(
        int    $userId,
        string $type,
        ?int   $days,
        ?int   $issuedBy,
        string $reason
    ): void {
        // Deactivate any existing active restrictions first
        DB::table('ts_restrictions')
            ->where('user_id', $userId)
            ->where('active', true)
            ->update(['active' => false, 'updated_at' => now()]);

        DB::table('ts_restrictions')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'reason'     => $reason,
            'issued_by'  => $issuedBy,
            'expires_at' => $days ? now()->addDays($days) : null,
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log to moderator actions
        DB::table('ts_moderator_actions')->insert([
            'moderator_id' => $issuedBy ?? 0,
            'target_type'  => 'App\\Models\\User',
            'target_id'    => $userId,
            'action'       => 'suspend',
            'note'         => $reason,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
