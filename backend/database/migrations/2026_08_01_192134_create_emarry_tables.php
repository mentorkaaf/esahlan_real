<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('emarry_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('gender', ['male', 'female']);
            $table->enum('looking_for', ['male', 'female']);
            $table->unsignedTinyInteger('age');
            $table->string('city')->nullable();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('nationality')->nullable();
            $table->string('education')->nullable();   // high_school|bachelor|master|phd|other
            $table->string('occupation')->nullable();
            $table->boolean('has_children')->default(false);
            $table->string('marital_status')->default('single'); // single|divorced|widowed
            $table->text('bio')->nullable();
            $table->json('photos')->nullable();        // array of media URLs (max 4)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('fee_paid', 8, 2)->default(0);
            $table->boolean('is_visible')->default(false); // true after admin approval
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gender', 'looking_for', 'status', 'is_visible']);
        });

        Schema::create('emarry_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['sender_id', 'receiver_id']);
            $table->index(['receiver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emarry_interests');
        Schema::dropIfExists('emarry_profiles');
    }
};
