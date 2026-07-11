<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('community_highlight_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('highlight_id')->constrained('community_highlights')->cascadeOnDelete();
            // content_type: 'post' (includes reels) | 'story'
            $table->string('content_type', 10)->default('post');
            $table->unsignedBigInteger('content_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['highlight_id', 'content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_highlight_items');
    }
};
