<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_sos_alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_sos_alerts', 'driver_name'))
                $table->string('driver_name')->nullable()->after('deliveryman_id');
            if (!Schema::hasColumn('driver_sos_alerts', 'driver_phone'))
                $table->string('driver_phone')->nullable()->after('driver_name');
            if (!Schema::hasColumn('driver_sos_alerts', 'resolved_by'))
                $table->unsignedBigInteger('resolved_by')->nullable()->after('status');
            if (!Schema::hasColumn('driver_sos_alerts', 'resolved_at'))
                $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            if (!Schema::hasColumn('driver_sos_alerts', 'admin_notes'))
                $table->text('admin_notes')->nullable()->after('resolved_at');
        });
    }

    public function down(): void {}
};
