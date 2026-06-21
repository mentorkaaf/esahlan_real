<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = App\Helpers\AppSettings::now();
echo "Local now: {$now->format('l H:i:s')} (dayOfWeek={$now->dayOfWeek})\n\n";

$vendors = App\Models\Vendor::with('schedules')->where('is_active', 1)->get();
foreach ($vendors as $v) {
    echo "--- [{$v->id}] {$v->name} ---\n";
    echo "  is_open={$v->is_open}, temporarily_closed={$v->temporarily_closed}\n";

    $todaySch = $v->schedules->where('day', $now->dayOfWeek)->first();
    if ($todaySch) {
        echo "  Today (day {$now->dayOfWeek}): is_open={$todaySch->is_open} {$todaySch->open_time}-{$todaySch->close_time}\n";
    } else {
        echo "  Today: NO SCHEDULE\n";
    }
    echo "  isCurrentlyOpen() = " . ($v->isCurrentlyOpen() ? 'OPEN' : 'CLOSED') . "\n\n";
}
