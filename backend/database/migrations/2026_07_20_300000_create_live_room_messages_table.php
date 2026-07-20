<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_room_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 300);
            $table->timestamps();

            $table->index(['live_room_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_room_messages');
    }
};
