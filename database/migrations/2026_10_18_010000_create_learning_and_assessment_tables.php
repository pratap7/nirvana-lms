<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Student Lesson Progress
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->boolean('is_completed')->default(false)->index();
            $table->unsignedSmallInteger('watch_percentage')->default(0); // 0 to 100
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'batch_id', 'is_completed']);
        });

        // 2. Aggregate Course Progress
        Schema::create('course_progress', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->decimal('overall_percentage', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->unsignedSmallInteger('lessons_completed')->default(0);
            $table->unsignedSmallInteger('total_required_lessons')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0.00);
            $table->decimal('quiz_average', 5, 2)->default(0.00);
            $table->foreignId('last_accessed_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['user_id', 'batch_id']);
        });

        // 3. Quizzes
        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // null for untimed
            $table->unsignedTinyInteger('pass_percentage')->default(60);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });

        // 4. Quiz Questions
        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('type', 30)->default('single_choice'); // single_choice, multiple_choice, true_false, short_answer
            $table->json('options'); // array of ['id' => 'A', 'text' => '...', 'is_correct' => bool]
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quiz_id', 'sort_order']);
        });

        // 5. Quiz Attempts
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->json('answers')->nullable(); // ['question_id' => selected_answers]
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('max_score')->default(0);
            $table->decimal('percentage', 5, 2)->default(0.00);
            $table->boolean('passed')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'quiz_id']);
        });

        // 6. Assignments
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->json('submission_types'); // ['text', 'file', 'link']
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->dateTime('due_at');
            $table->boolean('allow_late')->default(true);
            $table->unsignedTinyInteger('max_resubmissions')->default(2);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });

        // 7. Assignment Submissions
        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('submission_number')->default(1);
            $table->longText('text_content')->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->string('link_url', 2000)->nullable();
            $table->string('status', 30)->default('submitted')->index(); // submitted, graded, resubmission_requested
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->timestamps();

            $table->index(['assignment_id', 'user_id']);
        });

        // 8. Announcements
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('priority', 20)->default('normal'); // normal, high, urgent
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'batch_id', 'published_at']);
        });

        // 9. Notification Preferences
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20); // email, sms, whatsapp, in_app
            $table->string('notification_type', 40); // session_reminder, assignment_due, announcement, billing
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'channel', 'notification_type'], 'user_channel_type_unique');
        });

        // 10. Certificates
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('certificate_number', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->date('issue_date');
            $table->string('template_name')->default('classical_yoga_v1');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('course_progress');
        Schema::dropIfExists('lesson_progress');
    }
};
