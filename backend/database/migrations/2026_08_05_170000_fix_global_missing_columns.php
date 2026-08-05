<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── global_products: add views_count if missing ───────────────────────
        if (!Schema::hasColumn('global_products', 'views_count')) {
            Schema::table('global_products', function (Blueprint $table) {
                $table->unsignedBigInteger('views_count')->default(0)->after('stock');
            });
        }

        // ── global_addresses: make first_name / last_name nullable ───────────
        // The register flow stores full name in global_users.name and address_line1.
        // first_name / last_name are optional display fields.
        if (Schema::hasColumn('global_addresses', 'first_name')) {
            DB::statement('ALTER TABLE global_addresses MODIFY first_name VARCHAR(100) NULL DEFAULT NULL');
        }
        if (Schema::hasColumn('global_addresses', 'last_name')) {
            DB::statement('ALTER TABLE global_addresses MODIFY last_name VARCHAR(100) NULL DEFAULT NULL');
        }

        // ── global_addresses: make state nullable ────────────────────────────
        if (Schema::hasColumn('global_addresses', 'state')) {
            DB::statement('ALTER TABLE global_addresses MODIFY state VARCHAR(100) NULL DEFAULT NULL');
        }
        // ── global_addresses: make phone nullable ─────────────────────────────
        if (Schema::hasColumn('global_addresses', 'phone')) {
            DB::statement('ALTER TABLE global_addresses MODIFY phone VARCHAR(30) NULL DEFAULT NULL');
        }
        // ── global_addresses: make zip_code nullable ──────────────────────────
        if (Schema::hasColumn('global_addresses', 'zip_code')) {
            DB::statement('ALTER TABLE global_addresses MODIFY zip_code VARCHAR(255) NULL DEFAULT NULL');
        }
        // ── global_addresses: make country_name nullable ──────────────────────
        if (Schema::hasColumn('global_addresses', 'country_name')) {
            DB::statement('ALTER TABLE global_addresses MODIFY country_name VARCHAR(255) NULL DEFAULT NULL');
        }
        // ── global_addresses: make country_code nullable ──────────────────────
        if (Schema::hasColumn('global_addresses', 'country_code')) {
            DB::statement('ALTER TABLE global_addresses MODIFY country_code VARCHAR(2) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        // Non-destructive migration — no down needed
    }
};
