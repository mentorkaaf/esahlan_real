<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_sections', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();          // e.g. 'hero', 'efood'
            $table->string('parent_slug', 60)->nullable(); // null = top-level section
            $table->string('label', 100);
            $table->string('icon', 60)->nullable();        // FontAwesome class or emoji
            $table->string('type', 20)->default('section'); // 'section' | 'service' | 'feature'
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Top-level sections ─────────────────────────────────────────────
        $sections = [
            // slug, parent, label, icon, type, enabled, order
            ['hero',                null,       'Hero Section',          'fas fa-home',           'section', true,  1],
            ['services',            null,       'Services Grid',         'fas fa-th-large',        'section', true,  2],
            ['espace',              null,       'eSpace Section',        'fas fa-play-circle',     'section', true,  3],
            ['why_esahlan',         null,       'Why eSahlan',           'fas fa-star',            'section', true,  4],
            ['cta',                 null,       'Call to Action / Join', 'fas fa-rocket',          'section', true,  5],

            // ── Services (children of 'services') ─────────────────────────
            ['efood',       'services', 'eFood',       'fas fa-utensils',     'service', true,  1],
            ['eshop',       'services', 'eShop',       'fas fa-shopping-bag', 'service', true,  2],
            ['ewholesale',  'services', 'eWholesale',  'fas fa-boxes',        'service', true,  3],
            ['egrocery',    'services', 'eGrocery',    'fas fa-shopping-cart','service', true,  4],
            ['eparcel',     'services', 'eParcel',     'fas fa-box',          'service', true,  5],
            ['elaundry',    'services', 'eLaundry',    'fas fa-tshirt',       'service', true,  6],
            ['emoving',     'services', 'eMoving',     'fas fa-truck',        'service', true,  7],
            ['ehealth',     'services', 'eHealth',     'fas fa-heartbeat',    'service', true,  8],
            ['erent',       'services', 'eRent',       'fas fa-home',         'service', true,  9],
            ['eticket',     'services', 'eTicket',     'fas fa-plane',        'service', true, 10],
            ['eexchange',   'services', 'eExchange',   'fas fa-exchange-alt', 'service', true, 11],
            ['edata',       'services', 'eData',       'fas fa-wifi',         'service', true, 12],

            // ── eSpace features (children of 'espace') ─────────────────────
            ['feed_reels',          'espace', 'Feed & Reels',           'fas fa-film',          'feature', true, 1],
            ['live_streaming',      'espace', 'Live Streaming',         'fas fa-broadcast-tower','feature', true, 2],
            ['podcasts',            'espace', 'Podcasts',               'fas fa-microphone',    'feature', true, 3],
            ['premium_content',     'espace', 'Premium Content',        'fas fa-gem',           'feature', true, 4],
            ['business_advertising','espace', 'Business Advertising',   'fas fa-bullhorn',      'feature', true, 5],
            ['direct_messages',     'espace', 'Direct Messages',        'fas fa-comments',      'feature', true, 6],
            ['stories',             'espace', 'Stories',                'fas fa-camera',        'feature', true, 7],
            ['hashtag_discovery',   'espace', 'Hashtag Discovery',      'fas fa-hashtag',       'feature', true, 8],
            ['follow_connect',      'espace', 'Follow & Connect',       'fas fa-user-friends',  'feature', true, 9],
        ];

        foreach ($sections as [$slug, $parent, $label, $icon, $type, $enabled, $order]) {
            DB::table('landing_sections')->insert([
                'slug'        => $slug,
                'parent_slug' => $parent,
                'label'       => $label,
                'icon'        => $icon,
                'type'        => $type,
                'is_enabled'  => $enabled,
                'sort_order'  => $order,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_sections');
    }
};
