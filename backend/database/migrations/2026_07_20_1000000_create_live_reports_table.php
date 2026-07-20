<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_room_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 60); // spam|nudity|hate_speech|violence|other
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending');
            $table->timestamps();
            $table->unique(['live_room_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_room_reports');
    }
};
