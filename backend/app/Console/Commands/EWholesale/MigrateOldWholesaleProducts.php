<?php

namespace App\Console\Commands\EWholesale;

use App\Models\EWholesale\{EWCategory, EWProduct, EWProductVariant, EWPriceTier, EWSupplier};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off migration: old wholesale products (in shared `products` table)
 * → ewholesale_products with a single default variant + one price tier.
 */
class MigrateOldWholesaleProducts extends Command
{
    protected $signature   = 'ewholesale:migrate-products {--dry-run}';
    protected $description = 'Migrate legacy wholesale products to the new ewholesale_* structure';

    public function handle(): int
    {
        $module = DB::table('modules')->where('slug', 'ewholesale')->first();
        if (!$module) {
            $this->warn('No ewholesale module found in modules table — nothing to migrate.');
            return 0;
        }

        $old = DB::table('products')
            ->where('module_id', $module->id)
            ->get();

        if ($old->isEmpty()) {
            $this->info('No legacy wholesale products found.');
            return 0;
        }

        $this->info("Found {$old->count()} legacy products to migrate.");

        // Ensure a default "General" category exists
        $defaultCat = EWCategory::firstOrCreate(
            ['slug' => 'general-wholesale'],
            ['name' => 'General', 'name_so' => 'Guud', 'is_active' => true, 'sort_order' => 99]
        );

        $migrated = 0;
        $skipped  = 0;

        foreach ($old as $p) {
            // Find or create supplier from vendor
            $supplier = null;
            if ($p->vendor_id) {
                $supplier = EWSupplier::where('vendor_id', $p->vendor_id)->first();
                if (!$supplier) {
                    $this->warn("  ⚠ Product [{$p->id}] {$p->name}: vendor #{$p->vendor_id} has no supplier record — using first supplier.");
                    $supplier = EWSupplier::first();
                }
            }

            if (!$supplier) {
                $this->warn("  ✗ Skipping [{$p->id}] {$p->name}: no supplier available.");
                $skipped++;
                continue;
            }

            // Map category
            $catId = $defaultCat->id;
            if ($p->category_id) {
                $oldCat = DB::table('categories')->find($p->category_id);
                if ($oldCat) {
                    $ewCat = EWCategory::firstOrCreate(
                        ['slug' => 'migrated-' . Str::slug($oldCat->name)],
                        ['name' => $oldCat->name, 'is_active' => true, 'sort_order' => 50]
                    );
                    $catId = $ewCat->id;
                }
            }

            $slug = Str::slug($p->name) . '-' . Str::random(4);

            if ($this->option('dry-run')) {
                $this->line("  [DRY-RUN] Would migrate: [{$p->id}] {$p->name} → supplier #{$supplier->id}, price \${$p->price}, moq {$p->min_qty}");
                $migrated++;
                continue;
            }

            DB::transaction(function () use ($p, $supplier, $catId, $slug) {
                $product = EWProduct::create([
                    'supplier_id'   => $supplier->id,
                    'category_id'   => $catId,
                    'name'          => $p->name,
                    'slug'          => $slug,
                    'description'   => $p->description ?? null,
                    'images'        => $p->thumbnail ? [$p->thumbnail] : null,
                    'unit'          => 'carton',
                    'moq'           => max(1, (float)($p->min_qty ?? 1)),
                    'status'        => 'active',
                    'min_price'     => (float)$p->price,
                    'max_price'     => (float)$p->price,
                ]);

                $variant = EWProductVariant::create([
                    'product_id' => $product->id,
                    'sku'        => 'MIGR-' . $p->id,
                    'stock_qty'  => max(0, (float)($p->stock_quantity ?? 0)),
                    'is_default' => true,
                    'is_active'  => true,
                ]);

                EWPriceTier::create([
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'min_qty'    => max(1, (float)($p->min_qty ?? 1)),
                    'max_qty'    => null,
                    'unit_price' => (float)$p->price,
                ]);
            });

            $this->info("  ✓ Migrated: {$p->name}");
            $migrated++;
        }

        $this->newLine();
        $this->info("Done. Migrated: {$migrated}, Skipped: {$skipped}");
        return 0;
    }
}
