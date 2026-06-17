<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->enum('ad_type', [
                'popup_fullscreen',
                'popup_modal',
                'banner_slider',
                'banner_inline',
                'card',
            ])->default('popup_modal');
            $table->string('target_module', 50)->nullable(); // null or 'all' = everyone
            $table->foreignId('target_district_id')
                ->nullable()
                ->constrained('districts')
                ->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'scheduled'])->default('inactive');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->enum('action_type', ['module', 'product', 'vendor', 'house', 'url', 'none'])->default('none');
            $table->string('action_value', 500)->nullable();
            $table->string('button_text', 100)->default('Learn More');
            $table->string('button_color', 20)->default('#FF8A00');
            $table->unsignedSmallInteger('display_frequency')->default(1);   // show every N opens
            $table->unsignedSmallInteger('display_delay_seconds')->default(2);
            $table->boolean('show_on_app_open')->default(true);
            $table->boolean('show_after_login')->default(false);
            $table->boolean('allow_dont_show_today')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['status', 'ad_type']);
            $table->index(['status', 'target_module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
