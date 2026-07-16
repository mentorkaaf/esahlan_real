<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('podcast_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color', 7)->default('#FF6B35');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedBigInteger('podcast_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default categories
        $categories = [
            ['name'=>'Business',     'slug'=>'business',     'icon'=>'business_center','color'=>'#1877F2','sort_order'=>1],
            ['name'=>'Education',    'slug'=>'education',    'icon'=>'school',          'color'=>'#34C759','sort_order'=>2],
            ['name'=>'Religion',     'slug'=>'religion',     'icon'=>'mosque',          'color'=>'#8B5CF6','sort_order'=>3],
            ['name'=>'Technology',   'slug'=>'technology',   'icon'=>'computer',        'color'=>'#06B6D4','sort_order'=>4],
            ['name'=>'Finance',      'slug'=>'finance',      'icon'=>'attach_money',    'color'=>'#F59E0B','sort_order'=>5],
            ['name'=>'Health',       'slug'=>'health',       'icon'=>'favorite',        'color'=>'#EF4444','sort_order'=>6],
            ['name'=>'Comedy',       'slug'=>'comedy',       'icon'=>'sentiment_very_satisfied','color'=>'#FBBF24','sort_order'=>7],
            ['name'=>'Sports',       'slug'=>'sports',       'icon'=>'sports_soccer',   'color'=>'#10B981','sort_order'=>8],
            ['name'=>'Politics',     'slug'=>'politics',     'icon'=>'account_balance', 'color'=>'#6366F1','sort_order'=>9],
            ['name'=>'Motivation',   'slug'=>'motivation',   'icon'=>'emoji_events',    'color'=>'#F97316','sort_order'=>10],
            ['name'=>'Lifestyle',    'slug'=>'lifestyle',    'icon'=>'spa',             'color'=>'#EC4899','sort_order'=>11],
            ['name'=>'News',         'slug'=>'news',         'icon'=>'newspaper',       'color'=>'#64748B','sort_order'=>12],
            ['name'=>'Entertainment','slug'=>'entertainment','icon'=>'movie',           'color'=>'#A855F7','sort_order'=>13],
            ['name'=>'Science',      'slug'=>'science',      'icon'=>'science',         'color'=>'#0EA5E9','sort_order'=>14],
            ['name'=>'History',      'slug'=>'history',      'icon'=>'history_edu',     'color'=>'#92400E','sort_order'=>15],
            ['name'=>'Kids',         'slug'=>'kids',         'icon'=>'child_care',      'color'=>'#F43F5E','sort_order'=>16],
            ['name'=>'Audiobooks',   'slug'=>'audiobooks',   'icon'=>'menu_book',       'color'=>'#059669','sort_order'=>17],
            ['name'=>'Languages',    'slug'=>'languages',    'icon'=>'translate',       'color'=>'#7C3AED','sort_order'=>18],
        ];

        $now = now();
        foreach ($categories as &$c) {
            $c['created_at'] = $now;
            $c['updated_at'] = $now;
        }
        DB::table('podcast_categories')->insert($categories);
    }

    public function down(): void {
        Schema::dropIfExists('podcast_categories');
    }
};
