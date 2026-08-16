<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 3 default module roles per module + assigns their permissions.
 *
 * Roles per module:
 *   1. {slug}_manager  — ALL 18 permissions (is_system, is_default)
 *   2. {slug}_operator — operational: orders, customers, operations, dashboard
 *   3. {slug}_support  — view-only + customers manage
 *
 * Safe to re-run (insertOrIgnore on slug).
 */
class ModuleRoleSeeder extends Seeder
{
    /** Group→actions assigned to each role tier. */
    private const MANAGER_GROUPS = [
        'dashboard'  => ['view'],
        'employees'  => ['view', 'manage'],
        'orders'     => ['view', 'manage', 'cancel'],
        'vendors'    => ['view', 'manage'],
        'customers'  => ['view', 'manage'],
        'reports'    => ['view', 'export'],
        'settings'   => ['view', 'manage'],
        'finance'    => ['view', 'manage', 'approve'],
        'operations' => ['view', 'manage'],
    ];

    private const OPERATOR_GROUPS = [
        'dashboard'  => ['view'],
        'orders'     => ['view', 'manage'],
        'customers'  => ['view', 'manage'],
        'operations' => ['view', 'manage'],
        'reports'    => ['view'],
    ];

    private const SUPPORT_GROUPS = [
        'dashboard'  => ['view'],
        'orders'     => ['view'],
        'customers'  => ['view', 'manage'],
        'operations' => ['view'],
    ];

    private static array $moduleLabels = [
        'efood'      => 'eFood',
        'eshop'      => 'eShop',
        'eticket'    => 'eTicket',
        'ehealth'    => 'eHealth',
        'edata'      => 'eData',
        'eparcel'    => 'eParcel',
        'erent'      => 'eRent',
        'emoving'    => 'eMoving',
        'ewholesale' => 'eWholesale',
        'egrocery'   => 'eGrocery',
        'eexchange'  => 'eExchange',
        'elaundry'   => 'eLaundry',
        'elearning'  => 'eLearning',
    ];

    public function run(): void
    {
        $modules = Module::all()->keyBy('slug');
        $total   = 0;

        foreach ($modules as $slug => $module) {
            $label = self::$moduleLabels[$slug] ?? ucfirst($slug);

            $roleDefinitions = [
                [
                    'name'        => "{$label} Manager",
                    'slug'        => "{$slug}_manager",
                    'description' => "Full access to all {$label} features and settings.",
                    'is_default'  => true,
                    'is_system'   => true,
                    'sort_order'  => 1,
                    'groups'      => self::MANAGER_GROUPS,
                ],
                [
                    'name'        => "{$label} Operator",
                    'slug'        => "{$slug}_operator",
                    'description' => "Operational access to {$label}: orders, customers, and day-to-day operations.",
                    'is_default'  => false,
                    'is_system'   => true,
                    'sort_order'  => 2,
                    'groups'      => self::OPERATOR_GROUPS,
                ],
                [
                    'name'        => "{$label} Support",
                    'slug'        => "{$slug}_support",
                    'description' => "View-only access with customer support capability in {$label}.",
                    'is_default'  => false,
                    'is_system'   => true,
                    'sort_order'  => 3,
                    'groups'      => self::SUPPORT_GROUPS,
                ],
            ];

            foreach ($roleDefinitions as $def) {
                // Create or skip
                $groups = $def['groups'];
                unset($def['groups']);

                $existing = ModuleRole::where('slug', $def['slug'])->first();
                if (!$existing) {
                    $existing = ModuleRole::create(array_merge($def, [
                        'module_id' => $module->id,
                        'status'    => 'active',
                    ]));
                    $total++;
                }

                // Sync permissions (idempotent)
                $slugs = ModulePermissionSeeder::slugsForModule($slug, $groups);
                $permIds = DB::table('permissions')
                    ->whereIn('slug', $slugs)
                    ->pluck('id')
                    ->all();

                // insertOrIgnore pairs
                $pairs = array_map(fn($id) => [
                    'module_role_id' => $existing->id,
                    'permission_id'  => $id,
                ], $permIds);

                foreach (array_chunk($pairs, 100) as $chunk) {
                    DB::table('module_role_permissions')->insertOrIgnore($chunk);
                }
            }
        }

        $this->command->info("ModuleRoleSeeder: {$total} roles created (skipped existing).");
    }
}
