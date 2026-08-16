<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('name');             // "eFood Manager"
            $table->string('slug')->unique();   // "efood_manager"
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false); // shown first in pickers
            $table->boolean('is_system')->default(false);  // cannot be deleted
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['module_id', 'name']);
            $table->index(['module_id', 'status']);
        });

        Schema::create('module_role_permissions', function (Blueprint $table) {
            $table->foreignId('module_role_id')
                ->constrained('module_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('permissions')->cascadeOnDelete();
            $table->primary(['module_role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_role_permissions');
        Schema::dropIfExists('module_roles');
    }
};
