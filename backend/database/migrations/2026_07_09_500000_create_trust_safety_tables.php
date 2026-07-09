<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Appeals
        Schema::create('ts_appeals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->morphs('appealable'); // post, comment, account
            $t->string('action_type'); // removed, suspended, warned, restricted
            $t->text('reason');
            $t->text('evidence')->nullable();
            $t->json('files')->nullable();
            $t->enum('status', ['pending','under_review','approved','rejected'])->default('pending');
            $t->text('moderator_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['user_id','status']);
        });

        // Strike history
        Schema::create('ts_strikes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('violation_type'); // nudity, spam, hate_speech, etc.
            $t->enum('severity', ['low','medium','high','critical'])->default('medium');
            $t->integer('points')->default(1);
            $t->text('reason');
            $t->morphs('content'); // what triggered the strike
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('appealed')->default(false);
            $t->timestamps();
            $t->index(['user_id','created_at']);
        });

        // Account restrictions
        Schema::create('ts_restrictions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', [
                'comment_ban','post_ban','live_ban','message_ban',
                'shadow_reduce','read_only','temp_suspend','perm_suspend'
            ]);
            $t->text('reason');
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['user_id','active']);
        });

        // Moderator actions log
        Schema::create('ts_moderator_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $t->morphs('target'); // post, user, comment, report
            $t->enum('action', [
                'approve','reject','remove','warn','strike',
                'suspend','unsuspend','escalate','appeal_approved','appeal_rejected'
            ]);
            $t->text('note')->nullable();
            $t->float('ai_score')->nullable();
            $t->integer('time_spent_seconds')->nullable();
            $t->timestamps();
            $t->index(['moderator_id','created_at']);
        });

        // Content AI scores
        Schema::create('ts_content_scores', function (Blueprint $t) {
            $t->id();
            $t->morphs('content');
            $t->float('safety_score')->default(100);
            $t->float('spam_score')->default(0);
            $t->float('violence_score')->default(0);
            $t->float('adult_score')->default(0);
            $t->float('fraud_score')->default(0);
            $t->float('hate_score')->default(0);
            $t->float('overall_risk')->default(0);
            $t->string('provider')->default('sightengine'); // sightengine, google_vision, manual
            $t->timestamps();
        });

        // Reporter reputation
        Schema::create('ts_reporter_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->integer('total_reports')->default(0);
            $t->integer('accurate_reports')->default(0);
            $t->integer('false_reports')->default(0);
            $t->float('trust_score')->default(50.0);
            $t->timestamps();
        });

        // Violations catalog
        Schema::create('ts_violations', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique(); // NUDITY, SPAM, HATE_SPEECH
            $t->string('category'); // safety, spam, fraud, privacy
            $t->string('name');
            $t->text('description');
            $t->enum('severity', ['low','medium','high','critical']);
            $t->integer('strike_points')->default(1);
            $t->boolean('auto_remove')->default(false);
            $t->boolean('immediate_ban')->default(false);
            $t->timestamps();
        });

        // Blocked keywords / domains
        Schema::create('ts_blocked_terms', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['keyword','domain','pattern','hashtag']);
            $t->string('value');
            $t->enum('severity', ['low','medium','high','critical'])->default('medium');
            $t->string('language', 10)->default('*');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['type','active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ts_blocked_terms');
        Schema::dropIfExists('ts_violations');
        Schema::dropIfExists('ts_reporter_scores');
        Schema::dropIfExists('ts_content_scores');
        Schema::dropIfExists('ts_moderator_actions');
        Schema::dropIfExists('ts_restrictions');
        Schema::dropIfExists('ts_strikes');
        Schema::dropIfExists('ts_appeals');
    }
};
