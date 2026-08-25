<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_chat_messages', function (Blueprint $table) {
            // Message type: text (default), voice, location, location_request
            $table->enum('message_type', ['text', 'voice', 'location', 'location_request'])
                  ->default('text')
                  ->after('id');

            // Voice message: URL to audio file in storage
            $table->text('voice_url')->nullable()->after('message');

            // Location message: lat/lng coordinates
            $table->decimal('lat', 10, 7)->nullable()->after('voice_url');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        // Allow message to be nullable (voice/location messages have no text body)
        DB::statement('ALTER TABLE order_chat_messages MODIFY COLUMN message text NULL');
    }

    public function down(): void
    {
        Schema::table('order_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'voice_url', 'lat', 'lng']);
        });
        DB::statement('ALTER TABLE order_chat_messages MODIFY COLUMN message text NOT NULL');
    }
};
