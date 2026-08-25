<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'app_type', 'min_version', 'latest_version',
        'force_update', 'update_message', 'android_url', 'ios_url',
    ];

    protected $casts = [
        'force_update' => 'boolean',
    ];

    /**
     * Compare two semver strings.
     * Returns: -1 if $a < $b, 0 if equal, 1 if $a > $b
     */
    public static function compareVersions(string $a, string $b): int
    {
        $aParts = array_map('intval', explode('.', $a));
        $bParts = array_map('intval', explode('.', $b));

        // Pad to same length
        $len = max(count($aParts), count($bParts));
        while (count($aParts) < $len) $aParts[] = 0;
        while (count($bParts) < $len) $bParts[] = 0;

        for ($i = 0; $i < $len; $i++) {
            if ($aParts[$i] < $bParts[$i]) return -1;
            if ($aParts[$i] > $bParts[$i]) return 1;
        }
        return 0;
    }

    /**
     * Returns true if $currentVersion is less than $minVersion.
     */
    public static function needsUpdate(string $currentVersion, string $minVersion): bool
    {
        return self::compareVersions($currentVersion, $minVersion) < 0;
    }
}
