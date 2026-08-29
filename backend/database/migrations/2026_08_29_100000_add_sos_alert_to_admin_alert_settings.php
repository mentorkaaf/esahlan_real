<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only insert if not already there
        if (!DB::table('admin_alert_settings')->where('key', 'sos_alert')->exists()) {
            DB::table('admin_alert_settings')->insert([
                'key'         => 'sos_alert',
                'label'       => 'Driver SOS Emergency',
                'category'    => 'drivers',
                'is_enabled'  => true,
                'description' => 'Email when a driver sends an SOS emergency alert.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_alert_settings')->where('key', 'sos_alert')->delete();
    }
};
