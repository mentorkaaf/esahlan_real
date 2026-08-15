<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('audience', ['all','department'])->default('all');
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('created_by')->constrained('hr_staff')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_announcements'); }
};
