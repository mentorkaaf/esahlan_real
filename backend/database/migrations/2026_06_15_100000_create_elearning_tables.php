<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categories
        Schema::create('el_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('el_categories')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Instructors
        Schema::create('el_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('expertise')->nullable();
            $table->text('qualifications')->nullable();
            $table->integer('experience_years')->default(0);
            $table->string('profile_photo')->nullable();
            $table->string('cover_photo')->nullable();
            $table->json('social_links')->nullable();
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_active')->default(true);
            $table->integer('total_students')->default(0);
            $table->integer('total_courses')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->timestamps();
        });

        // Courses
        Schema::create('el_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('el_instructors')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('el_categories');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description');
            $table->string('thumbnail')->nullable();
            $table->string('trailer_video')->nullable();
            $table->string('language')->default('so');
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'all'])->default('all');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->decimal('duration_hours', 8, 2)->default(0);
            $table->integer('total_lessons')->default(0);
            $table->integer('total_sections')->default(0);
            $table->integer('total_students')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->json('tags')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('requirements')->nullable();
            $table->text('target_audience')->nullable();
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_free')->default(false);
            $table->decimal('commission_rate', 5, 2)->default(20);
            $table->timestamps();
        });

        // Sections
        Schema::create('el_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('el_courses')->cascadeOnDelete();
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Lessons
        Schema::create('el_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('el_sections')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('el_courses')->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['video', 'pdf', 'document', 'quiz', 'assignment', 'live'])->default('video');
            $table->string('video_url')->nullable();
            $table->integer('video_duration_seconds')->default(0);
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();
        });

        // Enrollments
        Schema::create('el_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('el_courses')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('status', ['active', 'completed', 'refunded'])->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        // Lesson Progress
        Schema::create('el_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('el_lessons')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('el_enrollments')->cascadeOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->integer('watch_seconds')->default(0);
            $table->integer('last_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        // Quizzes
        Schema::create('el_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('el_lessons')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('pass_score')->default(70);
            $table->integer('time_limit_minutes')->nullable();
            $table->integer('max_attempts')->default(3);
            $table->timestamps();
        });

        // Quiz Questions
        Schema::create('el_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('el_quizzes')->cascadeOnDelete();
            $table->text('question');
            $table->enum('type', ['mcq', 'true_false', 'short_answer'])->default('mcq');
            $table->json('options')->nullable();
            $table->text('correct_answer');
            $table->text('explanation')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Quiz Attempts
        Schema::create('el_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('el_quizzes')->cascadeOnDelete();
            $table->json('answers');
            $table->decimal('score', 5, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->integer('time_taken_seconds')->default(0);
            $table->integer('attempt_number')->default(1);
            $table->timestamps();
        });

        // Assignments
        Schema::create('el_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('el_lessons')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->integer('due_days')->default(7);
            $table->integer('max_score')->default(100);
            $table->timestamps();
        });

        // Assignment Submissions
        Schema::create('el_assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('el_assignments')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->text('text_answer')->nullable();
            $table->integer('grade')->nullable();
            $table->text('feedback')->nullable();
            $table->enum('status', ['submitted', 'graded', 'returned'])->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
        });

        // Certificates
        Schema::create('el_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('el_courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('el_enrollments')->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->timestamp('issued_at');
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        // Reviews
        Schema::create('el_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('el_courses')->cascadeOnDelete();
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->text('instructor_reply')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        // Wishlists
        Schema::create('el_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('el_courses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        // Notes
        Schema::create('el_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('el_lessons')->cascadeOnDelete();
            $table->text('note');
            $table->integer('timestamp_seconds')->default(0);
            $table->timestamps();
        });

        // Withdrawals
        Schema::create('el_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('el_instructors')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Instructor Earnings
        Schema::create('el_instructor_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('el_instructors')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('el_enrollments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->enum('status', ['pending', 'released'])->default('pending');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('el_instructor_earnings');
        Schema::dropIfExists('el_withdrawals');
        Schema::dropIfExists('el_notes');
        Schema::dropIfExists('el_wishlists');
        Schema::dropIfExists('el_reviews');
        Schema::dropIfExists('el_certificates');
        Schema::dropIfExists('el_assignment_submissions');
        Schema::dropIfExists('el_assignments');
        Schema::dropIfExists('el_quiz_attempts');
        Schema::dropIfExists('el_quiz_questions');
        Schema::dropIfExists('el_quizzes');
        Schema::dropIfExists('el_lesson_progress');
        Schema::dropIfExists('el_enrollments');
        Schema::dropIfExists('el_lessons');
        Schema::dropIfExists('el_sections');
        Schema::dropIfExists('el_courses');
        Schema::dropIfExists('el_instructors');
        Schema::dropIfExists('el_categories');
    }
};
