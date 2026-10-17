<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categories table (nested categories e.g. Yoga > Hatha)
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Extend courses table with spec fields
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('branch_id')->constrained('categories')->nullOnDelete();
            $table->string('type', 30)->default('short_term')->after('category_id'); // long_term, short_term
            $table->string('default_delivery_mode', 30)->default('online')->after('type'); // online, offline, hybrid
            $table->json('outcomes')->nullable()->after('long_description');
            $table->text('prerequisites_text')->nullable()->after('outcomes');
            $table->unsignedInteger('enrollment_limit')->nullable()->after('max_students');
            $table->date('start_date')->nullable()->after('enrollment_limit');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('seo_title')->nullable()->after('end_date');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('cover_image_path', 500)->nullable()->after('seo_description');
            $table->string('language', 10)->default('en')->after('cover_image_path');
            $table->timestamp('cancelled_at')->nullable()->after('published_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->decimal('rating_average', 3, 2)->default(0.00)->after('sort_order');
            $table->unsignedInteger('reviews_count')->default(0)->after('rating_average');
            $table->json('completion_rules')->nullable()->after('metadata');
        });

        // 3. Extend lessons table with drip release & publication status
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('drip_type', 30)->default('immediate')->after('is_mandatory'); // immediate, days_after_enrollment, after_previous_lesson
            $table->unsignedSmallInteger('drip_days')->default(0)->after('drip_type');
            $table->boolean('is_published')->default(true)->after('drip_days');
        });

        // 4. Lesson resources table (PDFs, Audio, Guided Meditations, Downloadable files, External Links)
        Schema::create('lesson_resources', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 30)->default('file'); // pdf, audio, link, file
            $table->string('url_or_path', 1000);
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'sort_order']);
        });

        // 5. Course teachers table (assign teachers to courses with role: primary, assistant, guest)
        Schema::create('course_teachers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('primary'); // primary, assistant, guest
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
        });

        // 6. Course reviews table (moderation queue, 1 review per student with >= 25% progress)
        Schema::create('course_reviews', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->string('title')->nullable();
            $table->text('review_text');
            $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_note')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index(['course_id', 'status']);
        });

        // 7. Completion rules table per course
        Schema::create('completion_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained('courses')->cascadeOnDelete();
            $table->unsignedTinyInteger('min_attendance_percent')->default(80);
            $table->unsignedTinyInteger('min_score_percent')->default(70);
            $table->json('required_lessons')->nullable();
            $table->unsignedSmallInteger('assignments_required')->default(0);
            $table->boolean('manual_approval')->default(false);
            $table->json('rules_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completion_rules');
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('course_teachers');
        Schema::dropIfExists('lesson_resources');

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn(['drip_type', 'drip_days', 'is_published']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id',
                'type',
                'default_delivery_mode',
                'outcomes',
                'prerequisites_text',
                'enrollment_limit',
                'start_date',
                'end_date',
                'seo_title',
                'seo_description',
                'cover_image_path',
                'language',
                'cancelled_at',
                'cancellation_reason',
                'rating_average',
                'reviews_count',
                'completion_rules',
            ]);
        });

        Schema::dropIfExists('categories');
    }
};
