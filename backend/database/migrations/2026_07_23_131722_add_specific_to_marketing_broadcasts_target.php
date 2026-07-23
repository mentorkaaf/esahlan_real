<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE marketing_broadcasts MODIFY COLUMN target ENUM('all','active_30d','specific') NOT NULL DEFAULT 'all'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketing_broadcasts MODIFY COLUMN target ENUM('all','active_30d') NOT NULL DEFAULT 'all'");
    }
};
