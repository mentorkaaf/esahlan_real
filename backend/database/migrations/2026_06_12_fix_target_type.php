<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::statement("ALTER TABLE push_notification_logs MODIFY COLUMN target_type VARCHAR(20) NOT NULL DEFAULT 'all'");
    }
};
