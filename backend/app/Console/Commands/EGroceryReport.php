<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EGroceryReport extends Command
{
    protected $signature   = 'egrocery:report';
    protected $description = 'Print eGrocery catalog summary: category counts + 5 sample products with effective prices.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║          eGrocery Catalog Report                         ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        // ── Totals ────────────────────────────────────────────────────────────
        $cats     = DB::table('egrocery_categories')->count();
        $products = DB::table('egrocery_products')->count();
        $variants = DB::table('egrocery_product_variants')->count();
        $orders   = DB::table('egrocery_orders')->count();
        $brands   = DB::table('egrocery_brands')->count();

        $this->table(['Metric', 'Count'], [
            ['Categories',       $cats],
            ['Products',         $products],
            ['Variants (SKUs)',  $variants],
            ['Brands',           $brands],
            ['Orders (demo)',     $orders],
        ]);

        $this->newLine();

        // ── Per-category breakdown ────────────────────────────────────────────
        $this->info('📦 Products per Category:');
        $catRows = DB::table('egrocery_categories as c')
            ->leftJoin('egrocery_products as p', 'p.category_id', '=', 'c.id')
            ->whereNull('c.parent_id')
            ->groupBy('c.id', 'c.name', 'c.icon')
            ->orderByDesc(DB::raw('COUNT(p.id)'))
            ->select('c.icon', 'c.name', DB::raw('COUNT(p.id) as total'))
            ->get();

        $rows = $catRows->map(fn($r) => [$r->icon . ' ' . $r->name, $r->total])->toArray();
        $this->table(['Category', 'Products'], $rows);

        $this->newLine();

        // ── 5 sample products with effective prices ────────────────────────────
        $this->info('🛒 5 Sample Products with Effective Prices:');

        $samples = DB::table('egrocery_products as p')
            ->join('egrocery_product_variants as v', function ($j) {
                $j->on('v.product_id', '=', 'p.id')
                  ->where('v.is_default', true)
                  ->where('v.is_active', true);
            })
            ->leftJoin('egrocery_flash_deals as fd', function ($j) {
                $j->on('fd.variant_id', '=', 'v.id')
                  ->where(function ($q) {
                      $q->whereNull('fd.qty_limit')
                        ->orWhereColumn('fd.qty_sold', '<', 'fd.qty_limit');
                  });
            })
            ->where('p.is_active', true)
            ->orderByDesc('p.orders_count')
            ->limit(5)
            ->select([
                'p.name',
                'v.label',
                'v.price',
                'v.compare_price',
                'v.stock_qty',
                'fd.deal_price',
            ])
            ->get();

        $sampleRows = $samples->map(function ($r) {
            $effective = $r->deal_price ?? $r->price;
            $original  = $r->deal_price ? $r->price : ($r->compare_price ?? null);
            $discount  = ($original && $original > $effective)
                ? ' (' . round((($original - $effective) / $original) * 100) . '% off)'
                : '';
            $flash     = $r->deal_price ? ' ⚡ FLASH' : '';
            $strike    = $original ? ' was $' . number_format($original, 2) : '';
            return [
                $r->name . ' — ' . $r->label,
                '$' . number_format($effective, 2) . $strike . $discount . $flash,
                $r->stock_qty . ' units',
            ];
        })->toArray();

        $this->table(['Product', 'Effective Price', 'Stock'], $sampleRows);

        // ── Low stock warning ─────────────────────────────────────────────────
        $lowStock = DB::table('egrocery_product_variants as v')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->whereColumn('v.stock_qty', '<=', 'v.low_stock_threshold')
            ->where('v.stock_qty', '>', 0)
            ->where('v.is_active', true)
            ->count();
        $outOfStock = DB::table('egrocery_product_variants')
            ->where('stock_qty', '<=', 0)
            ->where('is_active', true)
            ->count();

        if ($lowStock > 0 || $outOfStock > 0) {
            $this->newLine();
            $this->warn("⚠️  Low stock: {$lowStock} variants | Out of stock: {$outOfStock} variants");
        }

        // ── Flash deals ────────────────────────────────────────────────────────
        $flashCount = DB::table('egrocery_flash_deals')->count();
        if ($flashCount > 0) {
            $this->newLine();
            $this->info("⚡ Active flash deals: {$flashCount}");
        }

        $this->newLine();
        $this->line('Report generated: ' . now()->toDateTimeString());
        $this->newLine();

        return self::SUCCESS;
    }
}
