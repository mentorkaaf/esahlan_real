<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('module_position_id')
                  ->nullable()
                  ->after('module_department_id');

            $table->foreign('module_position_id')
                  ->references('id')->on('module_positions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->dropForeign(['module_position_id']);
            $table->dropColumn('module_position_id');
        });
    }
};
