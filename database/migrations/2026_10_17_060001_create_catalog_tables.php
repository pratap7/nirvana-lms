<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_months')->default(1);
            $table->string('status', 20)->default('draft');
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'slug']);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('short_description', 500)->nullable();
            $table->longText('long_description')->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('difficulty_level', 30)->default('all_levels');
            $table->unsignedSmallInteger('duration_hours')->default(0);
            $table->unsignedSmallInteger('max_students')->nullable();
            $table->unsignedBigInteger('base_fee_amount')->default(0); // in smallest unit (paise)
            $table->char('base_fee_currency', 3)->default('INR');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'slug']);
            $table->index(['status', 'branch_id']);
        });

        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_locked_by_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'sort_order']);
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('content_type', 30)->default('text');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_free_preview')->default(false);
            $table->boolean('is_mandatory')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module_id', 'sort_order']);
            $table->unique(['module_id', 'slug']);
        });

        Schema::create('lesson_contents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('type', 30);
            $table->longText('body')->nullable();
            $table->string('media_path', 500)->nullable();
            $table->string('external_url', 2000)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'sort_order']);
        });

        Schema::create('course_prerequisites', function (Blueprint $table): void {
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('prerequisite_course_id')->constrained('courses')->cascadeOnDelete();
            $table->boolean('is_mandatory')->default(true);

            $table->primary(['course_id', 'prerequisite_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('lesson_contents');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programs');
    }
};
