<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Battle invite: host A invites host B
        Schema::create('live_battle_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending/accepted/rejected/expired
            $table->timestamps();
        });

        // Battle session (shared LiveKit room for both hosts + viewers)
        Schema::create('live_battles', function (Blueprint $table) {
            $table->id();
            $table->string('battle_room_name')->unique(); // shared LiveKit room name
            $table->string('status')->default('active'); // active/ended
            $table->unsignedSmallInteger('duration_seconds')->default(180);
            $table->timestamp('ends_at');
            $table->foreignId('winner_host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invite_id')->nullable()->constrained('live_battle_invites')->nullOnDelete();
            $table->timestamps();
        });

        // Per-participant score inside a battle
        Schema::create('live_battle_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('live_battles')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('score')->default(0); // coins received
            $table->unsignedTinyInteger('rank')->default(0);  // 1/2/3/4
            $table->timestamps();

            $table->unique(['battle_id', 'host_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_battle_participants');
        Schema::dropIfExists('live_battles');
        Schema::dropIfExists('live_battle_invites');
    }
};
