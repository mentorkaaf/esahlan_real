<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Wallet;

$orders = Order::where('status', 'delivered')
    ->whereNotNull('vendor_id')
    ->get();

$count = 0;
foreach ($orders as $o) {
    $earning = (float)$o->subtotal - (float)($o->commission ?? 0);
    if ($earning <= 0) continue;

    $wallet = Wallet::getOrCreateFor('App\\Models\\Vendor', $o->vendor_id);

    // Check if already credited for this order
    $exists = DB::table('transactions')
        ->where('wallet_id', $wallet->id)
        ->where('reference_type', 'App\\Models\\Order')
        ->where('reference_id', $o->id)
        ->exists();

    if (!$exists) {
        $wallet->credit($earning, "Order #{$o->order_number} earning", 'App\\Models\\Order', $o->id);
        echo "Order #{$o->id} ({$o->order_number}): credited \${$earning} to vendor #{$o->vendor_id}\n";
        $count++;
    }
}
echo "\n{$count} vendor wallet(s) credited.\n";
