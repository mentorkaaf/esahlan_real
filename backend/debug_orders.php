<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ALL ORDERS ===\n";
$orders = DB::table('orders')->orderByDesc('id')->get();
foreach ($orders as $o) {
    echo "#{$o->id} {$o->order_number} | status={$o->status} | subtotal={$o->subtotal} | delivery={$o->delivery_fee} | discount={$o->discount_amount} | commission={$o->commission} | total={$o->total_amount} | vendor={$o->vendor_id}\n";
}

echo "\n=== MODULE COMMISSION CONFIG ===\n";
$modules = DB::table('modules')->get(['id','name','slug','commission_type','commission_value']);
foreach ($modules as $m) {
    echo "{$m->slug}: type={$m->commission_type}, value={$m->commission_value}\n";
}

echo "\n=== VENDOR COMMISSION CONFIG ===\n";
$vendors = DB::table('vendors')->whereNull('deleted_at')->get(['id','name','commission_type','commission_value','delivery_fee']);
foreach ($vendors as $v) {
    echo "#{$v->id} {$v->name}: commission_type={$v->commission_type}, commission_value={$v->commission_value}, delivery_fee={$v->delivery_fee}\n";
}
