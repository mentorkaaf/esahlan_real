<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Business pages — users can create a page for their business
        Schema::create('community_business_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('avatar')->nullable();
            $t->string('cover_photo')->nullable();
            $t->string('category')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('website')->nullable();
            $t->string('address')->nullable();
            $t->string('location')->nullable();
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->unsignedBigInteger('posts_count')->default(0);
            $t->boolean('is_verified')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Business page followers
        Schema::create('community_page_followers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('page_id')->constrained('community_business_pages')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'page_id']);
        });

        // Ad pricing — admin sets prices per format
        Schema::create('community_ad_pricing', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('ad_type', ['image', 'video', 'carousel']);
            $t->enum('placement', ['feed', 'reels', 'explore', 'stories']);
            $t->decimal('cost_per_click', 8, 2)->default(0);
            $t->decimal('cost_per_impression', 8, 4)->default(0);
            $t->decimal('cost_per_1000', 8, 2)->default(0);
            $t->decimal('min_budget', 8, 2)->default(5.00);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Ads — created by business page owners
        Schema::create('community_ads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('page_id')->nullable()->constrained('community_business_pages')->nullOnDelete();
            $t->foreignId('pricing_id')->nullable()->constrained('community_ad_pricing')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->enum('ad_type', ['image', 'video']);
            $t->string('media_url');
            $t->string('thumbnail_url')->nullable();
            $t->string('cta_text')->nullable();
            $t->string('cta_url')->nullable();
            $t->enum('placement', ['feed', 'reels', 'explore', 'stories'])->default('feed');
            $t->decimal('budget', 10, 2)->default(0);
            $t->decimal('spent', 10, 2)->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->unsignedBigInteger('views')->default(0);
            $t->enum('status', ['draft', 'pending', 'active', 'paused', 'completed', 'rejected'])->default('draft');
            $t->enum('payment_method', ['wallet', 'waafi_pay'])->default('wallet');
            $t->string('payment_reference')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('target_district')->nullable();
            $t->string('target_gender')->nullable();
            $t->json('target_interests')->nullable();
            $t->timestamps();
        });

        // Ad interactions — track clicks and impressions
        Schema::create('community_ad_interactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ad_id')->constrained('community_ads')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['impression', 'click', 'view']);
            $t->timestamps();
            $t->index(['ad_id', 'type']);
        });

        // Seed default ad pricing
        \Illuminate\Support\Facades\DB::table('community_ad_pricing')->insert([
            ['name' => 'Feed Image Ad',   'ad_type' => 'image', 'placement' => 'feed',    'cost_per_click' => 0.10, 'cost_per_impression' => 0.005,  'cost_per_1000' => 5.00, 'min_budget' => 5.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Feed Video Ad',   'ad_type' => 'video', 'placement' => 'feed',    'cost_per_click' => 0.15, 'cost_per_impression' => 0.008,  'cost_per_1000' => 8.00, 'min_budget' => 10.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Reels Video Ad',  'ad_type' => 'video', 'placement' => 'reels',   'cost_per_click' => 0.20, 'cost_per_impression' => 0.010,  'cost_per_1000' => 10.00, 'min_budget' => 15.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Explore Image Ad','ad_type' => 'image', 'placement' => 'explore', 'cost_per_click' => 0.12, 'cost_per_impression' => 0.006,  'cost_per_1000' => 6.00, 'min_budget' => 5.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('community_ad_interactions');
        Schema::dropIfExists('community_ads');
        Schema::dropIfExists('community_ad_pricing');
        Schema::dropIfExists('community_page_followers');
        Schema::dropIfExists('community_business_pages');
    }
};
