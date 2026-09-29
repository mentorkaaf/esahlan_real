<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            // 'public' = all users see it | 'private' = only assigned users (beta/test)
            $table->enum('visibility', ['public', 'private'])->default('public')->after('is_available');
        });

        Schema::create('module_beta_users', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['module_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_beta_users');
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
