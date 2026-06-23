<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find eMoving route pricing table
echo "=== TABLES WITH 'moving' OR 'route' ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    $name = array_values((array)$t)[0];
    if (stripos($name, 'moving') !== false || stripos($name, 'route') !== false || stripos($name, 'pricing') !== false) {
        echo "  $name\n";
        $cols = DB::select("SHOW COLUMNS FROM `$name`");
        foreach ($cols as $c) echo "    - {$c->Field} ({$c->Type})\n";
    }
}

// Check eMoving specific data
echo "\n=== EMOVING ROUTE PRICING (if exists) ===\n";
try {
    $routes = DB::table('moving_route_pricing')->get();
    echo "Found " . count($routes) . " routes\n";
    foreach ($routes as $r) echo "  " . json_encode((array)$r) . "\n";
} catch (\Throwable $e) {
    echo "Table 'moving_route_pricing' not found\n";
}

// Check delivery_zone_pricing for emoving module
echo "\n=== DELIVERY ZONE PRICING (module_id=emoving) ===\n";
$zones = DB::table('delivery_zone_pricing')->where('module_id', 'emoving')->get();
echo "Found " . count($zones) . " emoving zones\n";
foreach ($zones as $z) {
    $from = DB::table('districts')->find($z->from_district_id);
    $to = DB::table('districts')->find($z->to_district_id);
    echo "  {$from->name} → {$to->name} = \${$z->base_price}\n";
}

// Check all distinct module_ids in zone pricing
echo "\n=== ALL MODULE_IDS IN ZONE PRICING ===\n";
$mods = DB::table('delivery_zone_pricing')->select('module_id')->distinct()->pluck('module_id');
foreach ($mods as $m) echo "  '$m' (" . DB::table('delivery_zone_pricing')->where('module_id', $m)->count() . " zones)\n";

// Check eMoving order note data
echo "\n=== EMOVING ORDER NOTE SAMPLE ===\n";
$o = DB::table('orders')->where('module_slug', 'emoving')->orderByDesc('id')->first();
if ($o) {
    $note = json_decode($o->note, true);
    echo "Order #{$o->id}: total={$note['total']}, distance_fee={$note['distance_fee']}\n";
    echo "  base_price={$note['base_price']}, room_price={$note['room_price']}\n";
}
