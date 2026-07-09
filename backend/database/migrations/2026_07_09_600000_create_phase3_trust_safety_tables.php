<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->enum('status', ['pending','live','ended','terminated'])->default('pending');
            $table->string('stream_key', 64)->unique();
            $table->string('hls_url')->nullable();
            $table->integer('viewer_count')->default(0);
            $table->integer('peak_viewers')->default(0);
            $table->float('risk_score')->default(0);
            $table->integer('violation_count')->default(0);
            $table->string('terminated_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['status','started_at']);
        });

        Schema::create('live_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained('live_streams')->cascadeOnDelete();
            $table->string('event_type');
            $table->float('risk_score')->default(0);
            $table->string('violation_type')->nullable();
            $table->string('action_taken')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('copyright_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimant_id')->constrained('users')->cascadeOnDelete();
            $table->string('claimant_name');
            $table->string('claimant_email');
            $table->string('work_description', 1000);
            $table->string('original_url')->nullable();
            $table->string('reported_type');
            $table->unsignedBigInteger('reported_id');
            $table->enum('status', ['pending','under_review','upheld','dismissed','counter_notice'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('admin_note')->nullable();
            $table->boolean('content_disabled')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status','created_at']);
            $table->index(['reported_type','reported_id']);
        });

        Schema::create('copyright_counter_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('copyright_claims')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('statement');
            $table->string('jurisdiction')->nullable();
            $table->enum('status', ['pending','accepted','rejected'])->default('pending');
            $table->timestamps();
        });

        Schema::create('ai_content_scores', function (Blueprint $table) {
            $table->id();
            $table->string('scoreable_type');
            $table->unsignedBigInteger('scoreable_id');
            $table->float('image_score')->default(0);
            $table->float('text_score')->default(0);
            $table->float('behavior_score')->default(0);
            $table->float('context_score')->default(0);
            $table->float('final_score')->default(0);
            $table->json('signals')->nullable();
            $table->string('model_version')->default('v1');
            $table->timestamps();
            $table->index(['scoreable_type','scoreable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_content_scores');
        Schema::dropIfExists('copyright_counter_notices');
        Schema::dropIfExists('copyright_claims');
        Schema::dropIfExists('live_moderation_events');
        Schema::dropIfExists('live_streams');
    }
};
