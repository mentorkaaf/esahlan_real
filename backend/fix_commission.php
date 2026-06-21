<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Fix ALL orders with commission=0 or NULL
$orders = DB::table('orders')->where(function($q) {
    $q->whereNull('commission')->orWhere('commission', 0);
})->get();

$count = 0;
foreach ($orders as $o) {
    $rate = 10; // default

    // Check vendor override
    if ($o->vendor_id) {
        $v = DB::table('vendors')->find($o->vendor_id);
        if ($v && $v->commission_value > 0) {
            $rate = (float) $v->commission_value;
        }
    }

    // Check module override
    if ($rate == 10 && $o->module_id) {
        $m = DB::table('modules')->find($o->module_id);
        if ($m && $m->commission_value > 0) {
            $rate = (float) $m->commission_value;
        }
    }

    // If no module_id, try module_slug
    if ($rate == 10 && $o->module_slug) {
        $m = DB::table('modules')->where('slug', $o->module_slug)->first();
        if ($m && $m->commission_value > 0) {
            $rate = (float) $m->commission_value;
        }
    }

    $sub = (float) ($o->subtotal ?? $o->total_amount ?? 0);
    $comm = round($sub * $rate / 100, 2);

    DB::table('orders')->where('id', $o->id)->update(['commission' => $comm]);
    echo "Order #{$o->id}: subtotal=$sub, rate={$rate}%, commission=$comm\n";
    $count++;
}
echo "\n$count orders fixed.\n";
