<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Auto notification templates ────────────────────────────────────────
        Schema::create('auto_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // e.g. 'eticket_upcoming_flight'
            $table->string('type');                    // 'eticket', 'efood', 'promo', etc.
            $table->string('label');                   // human-readable name for admin
            $table->string('title_template');
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('interval_hours')->default(12); // send interval
            $table->string('channel_id')->default('esahlan_promo');
            $table->json('settings')->nullable();      // extra config
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // ── Auto notification send log ─────────────────────────────────────────
        Schema::create('auto_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug')->index();
            $table->unsignedBigInteger('reference_id')->nullable();  // flight_id, campaign_id, etc.
            $table->string('reference_type')->nullable();             // 'flight', 'campaign', etc.
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Seed the eTicket template ──────────────────────────────────────────
        DB::table('auto_notification_templates')->insert([
            'slug'           => 'eticket_upcoming_flight',
            'type'           => 'eticket',
            'label'          => 'eTicket — Upcoming Flight Alerts',
            'title_template' => '✈️ {from} → {to} — Available Now!',
            'body_template'  => 'Flight {flight_no} on {date} · {seats} seats from ${price}. Book your seat now!',
            'is_active'      => true,
            'interval_hours' => 12,
            'channel_id'     => 'esahlan_promo',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_notification_logs');
        Schema::dropIfExists('auto_notification_templates');
    }
};
