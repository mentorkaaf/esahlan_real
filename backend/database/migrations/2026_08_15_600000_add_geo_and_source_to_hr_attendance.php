<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance', function (Blueprint $table) {
            $table->enum('source', ['hr_panel', 'api', 'import', 'bulk'])->default('hr_panel')->after('is_manual');
            $table->decimal('check_in_lat',  10, 7)->nullable()->after('source');
            $table->decimal('check_in_lng',  10, 7)->nullable()->after('check_in_lat');
            $table->decimal('check_out_lat', 10, 7)->nullable()->after('check_in_lng');
            $table->decimal('check_out_lng', 10, 7)->nullable()->after('check_out_lat');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance', function (Blueprint $table) {
            $table->dropColumn(['source','check_in_lat','check_in_lng','check_out_lat','check_out_lng']);
        });
    }
};
