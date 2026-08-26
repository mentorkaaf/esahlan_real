<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('driver_challenges')) {
            Schema::create('driver_challenges', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('type')->default('delivery_count');
                $table->unsignedInteger('target_count')->default(5);
                $table->decimal('reward_amount', 10, 2)->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('module_slug')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('driver_challenge_progress')) {
            Schema::create('driver_challenge_progress', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deliveryman_id');
                $table->unsignedBigInteger('challenge_id');
                $table->unsignedInteger('current_count')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('reward_paid_at')->nullable();
                $table->timestamps();
                $table->unique(['deliveryman_id','challenge_id']);
                $table->foreign('deliveryman_id')->references('id')->on('deliverymen')->onDelete('cascade');
                $table->foreign('challenge_id')->references('id')->on('driver_challenges')->onDelete('cascade');
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('driver_challenge_progress');
        Schema::dropIfExists('driver_challenges');
    }
};
