<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modules an employee is allowed to manage (full access to that module's
        // admin management pages). Empty for non-employee roles.
        if (!Schema::hasTable('user_modules')) {
            Schema::create('user_modules', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('module_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['user_id', 'module_id']);
            });
        }

        // Generic "Employee" role — a staff member scoped to one or more modules.
        DB::table('roles')->updateOrInsert(
            ['slug' => 'employee'],
            [
                'name'       => 'Employee',
                'slug'       => 'employee',
                'is_system'  => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_modules');
        DB::table('roles')->where('slug', 'employee')->delete();
    }
};
