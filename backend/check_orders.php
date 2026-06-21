<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check the latest order
$o = DB::table('orders')->orderByDesc('id')->first();
if (!$o) { echo "No orders\n"; exit; }

echo "Order #{$o->id} {$o->order_number}\n";
echo "  subtotal={$o->subtotal}, commission={$o->commission}, total={$o->total_amount}\n";
echo "  vendor_id={$o->vendor_id}, module_id={$o->module_id}, module_slug={$o->module_slug}\n";

// Simulate commission calculation
$vendor = DB::table('vendors')->find($o->vendor_id);
echo "\nVendor: " . ($vendor ? $vendor->name : 'NULL') . "\n";
echo "  commission_type={$vendor->commission_type}, commission_value={$vendor->commission_value}\n";

$commissionRate = 0;
if ($vendor && $vendor->commission_value > 0) {
    $commissionRate = (float) $vendor->commission_value;
    echo "  → Using vendor rate: {$commissionRate}%\n";
}
if ($commissionRate <= 0) {
    $efoodModule = DB::table('modules')->where('slug', 'efood')->first();
    echo "  Module: " . ($efoodModule ? $efoodModule->name : 'NULL') . "\n";
    echo "  Module commission_value: " . ($efoodModule->commission_value ?? 'NULL') . "\n";
    $commissionRate = ($efoodModule && $efoodModule->commission_value > 0)
        ? (float) $efoodModule->commission_value
        : 10;
    echo "  → Using module rate: {$commissionRate}%\n";
}

$should = round($o->subtotal * $commissionRate / 100, 2);
echo "\nShould be: commission={$should} (rate={$commissionRate}%)\n";
echo "Actual:    commission={$o->commission}\n";

if ($o->commission != $should) {
    echo "\n⚠️  MISMATCH — fixing...\n";
    DB::table('orders')->where('id', $o->id)->update(['commission' => $should]);
    echo "Fixed!\n";
}
