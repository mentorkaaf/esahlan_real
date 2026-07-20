<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Active guests currently on stage
        Schema::create('live_room_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'removed'])->default('active');
            $table->boolean('is_muted')->default(false);
            $table->boolean('camera_disabled')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['live_room_id', 'user_id']);
            $table->index(['live_room_id', 'status']);
        });

        // Join requests from viewers
        Schema::create('live_guest_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->unique(['live_room_id', 'user_id']);
            $table->index(['live_room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_guest_requests');
        Schema::dropIfExists('live_room_guests');
    }
};
