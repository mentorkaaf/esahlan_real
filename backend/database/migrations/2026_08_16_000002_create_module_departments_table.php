<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_departments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id')
                  ->constrained('modules')
                  ->cascadeOnDelete();

            $table->string('name', 120);
            $table->text('description')->nullable();

            // Department head — nullable (department may start with no manager)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->foreign('manager_id')
                  ->references('id')->on('hr_employees')
                  ->nullOnDelete();

            // active | inactive | archived
            $table->string('status', 20)->default('active')->index();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['module_id', 'status']);
            $table->unique(['module_id', 'name']); // unique dept name per module
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_departments');
    }
};
