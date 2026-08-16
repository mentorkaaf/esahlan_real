<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('module_department_id')
                  ->nullable()
                  ->after('module_id');

            $table->foreign('module_department_id')
                  ->references('id')->on('module_departments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->dropForeign(['module_department_id']);
            $table->dropColumn('module_department_id');
        });
    }
};
