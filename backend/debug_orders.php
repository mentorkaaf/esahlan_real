<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orders = DB::table('orders')->whereIn('module_slug', ['eparcel','emoving'])->orderByDesc('id')->limit(5)->get();
foreach ($orders as $o) {
    echo "#{$o->id} {$o->order_number} [{$o->module_slug}]\n";
    echo "  vendor_id={$o->vendor_id} user_id={$o->user_id} fee={$o->delivery_fee}\n";
    echo "  note=" . substr($o->note ?? 'NULL', 0, 500) . "\n";
    echo "  notes=" . substr($o->notes ?? 'NULL', 0, 500) . "\n";
    echo "  delivery_address=" . (is_string($o->delivery_address) ? substr($o->delivery_address, 0, 300) : json_encode($o->delivery_address)) . "\n\n";
}

// Check districts with coordinates
echo "=== DISTRICTS ===\n";
$dists = DB::table('districts')->get(['id','name','latitude','longitude']);
foreach ($dists as $d) echo "  #{$d->id} {$d->name} lat={$d->latitude} lng={$d->longitude}\n";

// Check zone pricing
echo "\n=== ZONE PRICING ===\n";
$zones = DB::table('delivery_zone_pricing')->where('is_active', true)->get();
echo "Total zones: " . count($zones) . "\n";
foreach ($zones as $z) {
    $from = DB::table('districts')->find($z->from_district_id);
    $to = DB::table('districts')->find($z->to_district_id);
    echo "  {$from->name} → {$to->name} = \${$z->base_price}\n";
}
