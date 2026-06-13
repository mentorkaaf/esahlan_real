<?php
namespace App\Console\Commands;
use App\Models\Order;
use App\Services\Commission\CommissionService;
use Illuminate\Console\Command;
class SettleCommissionsCommand extends Command {
    protected $signature = 'esahlan:settle-commissions';
    protected $description = 'Settle pending commissions for delivered orders';
    public function handle(CommissionService $commissionService): void {
        $orders = Order::where('status','delivered')
            ->where('payment_status','paid')
            ->whereDoesntHave('commission')
            ->whereDate('delivered_at','<',today())
            ->get();
        $this->info("Settling {$orders->count()} orders...");
        foreach($orders as $order) {
            try { $commissionService->settle($order); $this->line("✓ Order #{$order->order_number}"); }
            catch(\Exception $e) { $this->error("✗ Order #{$order->order_number}: {$e->getMessage()}"); }
        }
        $this->info('Commission settlement complete.');
    }
}
