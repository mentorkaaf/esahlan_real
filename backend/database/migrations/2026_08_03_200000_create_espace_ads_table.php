<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espace_ads', function (Blueprint $table) {
            $table->id();
            $table->string('module', 30);          // efood, egrocery, eshop, eparcel, emoving, elearning, eexchange, erent
            $table->string('title', 100);
            $table->string('subtitle', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('cta_text', 50)->default('Explore Now');
            $table->string('deep_link', 100);      // /efood, /erent, etc.
            $table->set('placement', ['feed', 'reels', 'comments', 'podcast'])->default('feed');
            $table->unsignedTinyInteger('priority')->default(5); // 1-10
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'placement', 'priority']);
        });

        // Track per-user impressions to avoid showing same ad too often
        Schema::create('espace_ad_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espace_ad_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 10)->default('view'); // view | click
            $table->timestamps();

            $table->index(['espace_ad_id', 'user_id', 'action']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espace_ad_impressions');
        Schema::dropIfExists('espace_ads');
    }
};
