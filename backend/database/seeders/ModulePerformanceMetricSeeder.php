<?php

namespace Database\Seeders;

use App\Models\HR\ModulePerformanceMetric;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulePerformanceMetricSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding module performance metrics...');

        foreach (ModulePerformanceMetric::METRICS as $slug => $metrics) {
            $module = Module::where('slug', $slug)->first();
            if (!$module) {
                $this->command->warn("  Module [{$slug}] not found — skipping");
                continue;
            }

            foreach ($metrics as $i => $m) {
                ModulePerformanceMetric::updateOrCreate(
                    ['module_id' => $module->id, 'slug' => $m['slug']],
                    [
                        'name'             => $m['name'],
                        'description'      => $m['desc'],
                        'unit'             => $m['unit'],
                        'target_value'     => $m['target'],
                        'weight'           => $m['weight'],
                        'higher_is_better' => $m['hib'],
                        'is_active'        => true,
                        'sort_order'       => $i + 1,
                    ]
                );
            }

            $count = count($metrics);
            $this->command->info("  ✓ {$module->name}: {$count} metrics");
        }

        $total = ModulePerformanceMetric::count();
        $this->command->info("Done. Total: {$total} metric definitions.");
    }
}
