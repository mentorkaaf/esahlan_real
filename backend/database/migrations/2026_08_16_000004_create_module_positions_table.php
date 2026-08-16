<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id')
                  ->constrained('modules')
                  ->cascadeOnDelete();

            // Optional: scope to a specific department within the module
            $table->unsignedBigInteger('module_department_id')->nullable();
            $table->foreign('module_department_id')
                  ->references('id')->on('module_departments')
                  ->nullOnDelete();

            // Optional link to an existing company-wide HR position (reuse)
            $table->unsignedBigInteger('hr_position_id')->nullable();
            $table->foreign('hr_position_id')
                  ->references('id')->on('hr_positions')
                  ->nullOnDelete();

            $table->string('name', 120);
            $table->text('description')->nullable();

            // junior | mid | senior | lead | manager
            $table->string('level', 20)->default('mid');

            // active | inactive | archived
            $table->string('status', 20)->default('active')->index();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['module_id', 'status']);
            $table->index(['module_department_id', 'status']);
            $table->unique(['module_id', 'name']); // no duplicate names within a module
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_positions');
    }
};
