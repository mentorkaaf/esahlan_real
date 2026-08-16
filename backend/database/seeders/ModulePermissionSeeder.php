<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds module-scoped permissions into the existing `permissions` table.
 *
 * Format: {module_slug}.{group}.{action}
 * Groups: dashboard | employees | orders | vendors | customers |
 *         reports   | settings  | finance | operations
 *
 * 18 permissions × 13 modules = 234 permission rows.
 * Safe to re-run (insertOrIgnore on slug).
 */
class ModulePermissionSeeder extends Seeder
{
    /** The 13 active business modules. */
    private static array $modules = [
        'efood', 'eshop', 'eticket', 'ehealth', 'edata',
        'eparcel', 'erent', 'emoving', 'ewholesale', 'egrocery',
        'eexchange', 'elaundry', 'elearning',
    ];

    /**
     * Permission definitions per group.
     * [action => human-readable name suffix]
     */
    private static array $groups = [
        'dashboard'  => [
            'view'   => 'View Dashboard',
        ],
        'employees'  => [
            'view'   => 'View Employees',
            'manage' => 'Manage Employees',
        ],
        'orders'     => [
            'view'   => 'View Orders',
            'manage' => 'Manage Orders',
            'cancel' => 'Cancel Orders',
        ],
        'vendors'    => [
            'view'   => 'View Vendors',
            'manage' => 'Manage Vendors',
        ],
        'customers'  => [
            'view'   => 'View Customers',
            'manage' => 'Manage Customers',
        ],
        'reports'    => [
            'view'   => 'View Reports',
            'export' => 'Export Reports',
        ],
        'settings'   => [
            'view'   => 'View Settings',
            'manage' => 'Manage Settings',
        ],
        'finance'    => [
            'view'    => 'View Finance',
            'manage'  => 'Manage Finance',
            'approve' => 'Approve Payouts',
        ],
        'operations' => [
            'view'   => 'View Operations',
            'manage' => 'Manage Operations',
        ],
    ];

    /** Human-readable module label overrides. */
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
        $now  = now();
        $rows = [];

        foreach (self::$modules as $slug) {
            $label = self::$moduleLabels[$slug] ?? ucfirst($slug);

            foreach (self::$groups as $group => $actions) {
                foreach ($actions as $action => $nameSuffix) {
                    $rows[] = [
                        'name'       => "{$label}: {$nameSuffix}",
                        'slug'       => "{$slug}.{$group}.{$action}",
                        'module'     => $slug,
                        'group'      => $group,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // insertOrIgnore prevents duplicate slugs on re-run
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('permissions')->insertOrIgnore($chunk);
        }

        $this->command->info('ModulePermissionSeeder: ' . count($rows) . ' permissions seeded.');
    }

    /**
     * Return all permission slugs for a given module + action subset.
     * Used by ModuleRoleSeeder.
     */
    public static function slugsForModule(string $moduleSlug, array $groupActions): array
    {
        $slugs = [];
        foreach ($groupActions as $group => $actions) {
            foreach ((array) $actions as $action) {
                $slugs[] = "{$moduleSlug}.{$group}.{$action}";
            }
        }
        return $slugs;
    }
}
