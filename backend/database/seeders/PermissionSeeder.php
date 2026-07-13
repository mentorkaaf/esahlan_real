<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    // Canonical permission list. Slug is the stable identifier used in Gate::define().
    const PERMISSIONS = [
        // ── Community ──────────────────────────────────────────────
        ['slug' => 'community.post.create',        'name' => 'Create Posts',             'module' => 'community', 'group' => 'content'],
        ['slug' => 'community.post.delete_any',    'name' => 'Delete Any Post',          'module' => 'community', 'group' => 'moderation'],
        ['slug' => 'community.comment.delete_any', 'name' => 'Delete Any Comment',       'module' => 'community', 'group' => 'moderation'],
        ['slug' => 'community.user.ban',           'name' => 'Ban / Unban Users',        'module' => 'community', 'group' => 'moderation'],
        ['slug' => 'community.report.view',        'name' => 'View Content Reports',     'module' => 'community', 'group' => 'moderation'],
        ['slug' => 'community.report.resolve',     'name' => 'Resolve Content Reports',  'module' => 'community', 'group' => 'moderation'],
        ['slug' => 'community.content.feature',    'name' => 'Feature / Pin Posts',      'module' => 'community', 'group' => 'content'],

        // ── Finance ────────────────────────────────────────────────
        ['slug' => 'finance.transaction.view',     'name' => 'View All Transactions',    'module' => 'finance',   'group' => 'finance'],
        ['slug' => 'finance.refund.process',       'name' => 'Process Refunds',          'module' => 'finance',   'group' => 'finance'],
        ['slug' => 'finance.withdrawal.approve',   'name' => 'Approve Withdrawals',      'module' => 'finance',   'group' => 'finance'],

        // ── Platform ───────────────────────────────────────────────
        ['slug' => 'platform.setting.manage',      'name' => 'Manage App Settings',      'module' => 'platform',  'group' => 'system'],
        ['slug' => 'platform.role.manage',         'name' => 'Manage User Roles',        'module' => 'platform',  'group' => 'system'],
        ['slug' => 'platform.audit.view',          'name' => 'View Audit Logs / SOC',    'module' => 'platform',  'group' => 'security'],
        ['slug' => 'platform.analytics.view',      'name' => 'View Analytics Dashboard', 'module' => 'platform',  'group' => 'analytics'],
        ['slug' => 'platform.module.manage',       'name' => 'Enable / Disable Modules', 'module' => 'platform',  'group' => 'system'],
    ];

    // Which roles get which permission slugs
    const ROLE_PERMISSIONS = [
        'super_admin' => '*', // all
        'admin' => [
            'community.post.create', 'community.post.delete_any', 'community.comment.delete_any',
            'community.user.ban', 'community.report.view', 'community.report.resolve',
            'community.content.feature',
            'finance.transaction.view', 'finance.refund.process', 'finance.withdrawal.approve',
            'platform.audit.view', 'platform.analytics.view', 'platform.module.manage',
        ],
        'operations_manager' => [
            'community.post.create', 'community.post.delete_any', 'community.comment.delete_any',
            'community.report.view', 'community.report.resolve',
            'platform.analytics.view',
        ],
        'finance_manager' => [
            'community.post.create',
            'finance.transaction.view', 'finance.refund.process', 'finance.withdrawal.approve',
            'platform.analytics.view',
        ],
        'marketing_manager' => [
            'community.post.create', 'community.content.feature',
            'platform.analytics.view',
        ],
        'customer_support' => [
            'community.post.create', 'community.report.view', 'community.comment.delete_any',
        ],
        'employee' => [
            'community.post.create',
        ],
        'vendor_owner'    => ['community.post.create'],
        'vendor_employee' => ['community.post.create'],
        'deliveryman'     => [],
        'customer'        => ['community.post.create'],
    ];

    public function run(): void
    {
        // Upsert permissions
        foreach (self::PERMISSIONS as $p) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $p['slug']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Build slug → id map
        $idMap = DB::table('permissions')->pluck('id', 'slug');
        $allIds = $idMap->values()->all();

        // Assign permissions to roles
        foreach (self::ROLE_PERMISSIONS as $roleSlug => $perms) {
            $role = DB::table('roles')->where('slug', $roleSlug)->first();
            if (!$role) continue;

            $permIds = $perms === '*' ? $allIds : collect($perms)->map(fn($s) => $idMap[$s] ?? null)->filter()->values()->all();

            // Delete old assignments for this role, re-insert
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            foreach ($permIds as $permId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
}
