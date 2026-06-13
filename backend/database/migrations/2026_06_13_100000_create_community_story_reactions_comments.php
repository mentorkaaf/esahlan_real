<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_story_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('community_stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 10);
            $table->timestamps();
            $table->unique(['story_id', 'user_id']);
        });

        Schema::create('community_story_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('community_stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('content', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_story_comments');
        Schema::dropIfExists('community_story_reactions');
    }
};
