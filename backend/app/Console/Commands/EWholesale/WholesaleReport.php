<?php

namespace App\Console\Commands\EWholesale;

use App\Models\EWholesale\{EWSupplier, EWCategory, EWProduct, EWProductVariant, EWBuyer};
use App\Services\EWholesale\PricingService;
use Illuminate\Console\Command;

class WholesaleReport extends Command
{
    protected $signature   = 'ewholesale:report';
    protected $description = 'Print a verification report of the eWholesale module state';

    public function __construct(private PricingService $pricing) { parent::__construct(); }

    public function handle(): int
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║         eWholesale Module — Verification Report       ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();

        // ── Suppliers ────────────────────────────────────────────────────
        $this->info('── SUPPLIERS ──────────────────────────────────────────');
        $suppliers = EWSupplier::with('vendor:id,name')->get();
        $this->table(
            ['ID', 'Display Name', 'Vendor', 'Verification', 'Active', 'Products'],
            $suppliers->map(fn($s) => [
                $s->id,
                $s->display_name,
                $s->vendor?->name ?? '-',
                strtoupper($s->verification),
                $s->is_active ? '✓' : '✗',
                $s->products()->count(),
            ])
        );

        // ── Categories ───────────────────────────────────────────────────
        $this->info('── CATEGORIES ─────────────────────────────────────────');
        $cats = EWCategory::withCount('products')->roots()->active()->with('children')->orderBy('sort_order')->get();
        $this->table(
            ['ID', 'Name', 'Products (direct)', 'Sub-cats'],
            $cats->map(fn($c) => [
                $c->id,
                $c->icon . ' ' . $c->name,
                $c->products_count,
                $c->children->count(),
            ])
        );
        $this->line('Total categories: ' . EWCategory::count() . ' (roots: ' . $cats->count() . ')');
        $this->newLine();

        // ── Products summary ─────────────────────────────────────────────
        $this->info('── PRODUCTS SUMMARY ───────────────────────────────────');
        $total   = EWProduct::count();
        $active  = EWProduct::where('status', 'active')->count();
        $featured= EWProduct::where('is_featured', true)->count();
        $this->line("  Total: {$total}  |  Active: {$active}  |  Featured: {$featured}");
        $this->newLine();

        // ── 5 sample products with full tier ladders ──────────────────────
        $this->info('── SAMPLE PRODUCTS WITH TIER LADDERS ─────────────────');
        $samples = EWProduct::with(['priceTiers', 'defaultVariant', 'supplier:id,display_name'])
            ->where('status', 'active')
            ->limit(5)
            ->get();

        foreach ($samples as $product) {
            $this->line("  ┌─ [{$product->id}] {$product->name}");
            $this->line("  │  Supplier: {$product->supplier?->display_name}  |  Unit: {$product->unit}  |  MOQ: {$product->moq}  |  Lead: {$product->lead_time_days}d");
            $this->line("  │  Price range: \${$product->min_price} – \${$product->max_price}");
            $this->line('  │  Tier ladder:');

            $tiers = $product->priceTiers;
            if ($tiers->isEmpty()) {
                $this->line('  │    (no tiers defined)');
            } else {
                foreach ($tiers as $tier) {
                    $range = $tier->max_qty ? "{$tier->min_qty}–{$tier->max_qty}" : "{$tier->min_qty}+";
                    $this->line("  │    {$range} {$product->unit} → \${$tier->unit_price}/unit");
                }
            }

            // PricingService demo at 3 quantities
            $variant = $product->defaultVariant;
            if ($variant) {
                $this->line('  │  PricingService resolution demo:');
                $testQtys = [$product->moq, $product->moq * 5, $product->moq * 20];
                foreach ($testQtys as $qty) {
                    try {
                        $result = $this->pricing->resolve($product, $variant, $qty);
                        $flag = $result['below_moq'] ? ' [BELOW MOQ]' : '';
                        $deal = $result['deal_applied'] ? ' [DEAL]' : '';
                        $pl   = $result['price_list_applied'] ? ' [PRICE LIST]' : '';
                        $savings = $result['savings_vs_top_tier'] > 0 ? " (save \${$result['savings_vs_top_tier']})" : '';
                        $this->line("  │    qty={$qty} → \${$result['unit_price']}/unit{$savings}{$flag}{$deal}{$pl}");
                    } catch (\Throwable $e) {
                        $this->line("  │    qty={$qty} → ERROR: " . $e->getMessage());
                    }
                }
            }
            $this->line('  └' . str_repeat('─', 52));
        }

        // ── Buyers ───────────────────────────────────────────────────────
        $this->newLine();
        $this->info('── BUYERS ─────────────────────────────────────────────');
        $buyers = EWBuyer::with(['creditAccount', 'user:id,name'])->get();
        $this->table(
            ['ID', 'Business', 'User', 'KYB', 'Credit Limit', 'Credit Used', 'Term'],
            $buyers->map(fn($b) => [
                $b->id,
                $b->business_name,
                $b->user?->name ?? '-',
                strtoupper($b->kyb_status),
                $b->creditAccount ? "\${$b->creditAccount->credit_limit}" : '-',
                $b->creditAccount ? "\${$b->creditAccount->balance_used}" : '-',
                $b->creditAccount?->term ?? '-',
            ])
        );

        $this->newLine();
        $this->info('✅ Report complete.');
        return 0;
    }
}
