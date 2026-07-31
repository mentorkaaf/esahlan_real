<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agent recommends a specific property to a request
        Schema::create('request_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id')->references('id')->on('house_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('agent_user_id');
            $table->foreign('agent_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
        });

        // Agent schedules a property viewing
        Schema::create('request_viewings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id')->references('id')->on('house_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('recommendation_id')->nullable();
            $table->foreign('recommendation_id')->references('id')->on('request_recommendations')->nullOnDelete();
            $table->unsignedBigInteger('agent_user_id');
            $table->foreign('agent_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->datetime('proposed_at');
            $table->enum('status', ['proposed', 'confirmed', 'cancelled', 'completed'])->default('proposed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // In-app chat per request (customer ↔ agent)
        Schema::create('request_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id')->references('id')->on('house_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id');
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('sender_role', ['customer', 'agent']);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_messages');
        Schema::dropIfExists('request_viewings');
        Schema::dropIfExists('request_recommendations');
    }
};
