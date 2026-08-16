<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->foreignId('module_role_id')
                ->nullable()
                ->after('module_position_id')
                ->constrained('module_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->dropForeign(['module_role_id']);
            $table->dropColumn('module_role_id');
        });
    }
};
