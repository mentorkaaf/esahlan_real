<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('privacy')->nullable();        // private_account, who_can_*
            $table->json('notifications')->nullable();  // likes, comments, replies, etc.
            $table->json('data_saver')->nullable();     // enabled, image_quality, video_quality, etc.
            $table->json('appearance')->nullable();     // theme, accent_color, font_size, reduce_motion
            $table->json('video')->nullable();          // autoplay, resolution, pip, loop, default_volume
            $table->json('audio')->nullable();          // background_playback, speed, enhancement
            $table->json('messages')->nullable();       // read_receipts, typing, who_can_message
            $table->json('accessibility')->nullable();  // large_text, screen_reader, captions, high_contrast
            $table->json('ai_features')->nullable();    // recommendations, translation, captions, assistant
            $table->json('safety')->nullable();         // hidden_words, comment_filter, sensitive_content
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
