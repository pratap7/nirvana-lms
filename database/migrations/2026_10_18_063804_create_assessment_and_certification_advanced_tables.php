<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Certificate Templates with Institute Branding & Drag-and-Drop Field Placement
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->json('layout')->nullable(); // width, height, orientation (landscape/portrait), background
            $table->json('fields')->nullable(); // drag-and-drop fields (student_name, course_title, date, cert_num, qr, signatures)
            $table->json('branding')->nullable(); // institute name, logo, seal, signature text/images
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'is_default']);
            $table->index(['event_id', 'is_default']);
        });

        // 2. Course Completion Evaluations (Per-requirement audit & snapshot checklist)
        Schema::create('completion_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->boolean('is_passed')->default(false)->index();
            $table->boolean('is_manually_approved')->default(false);
            $table->boolean('requires_manual_approval')->default(false);
            $table->foreignId('manual_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manual_approved_at')->nullable();
            $table->json('rules_snapshot'); // snapshot of course rules evaluated
            $table->json('checklist_results'); // per-requirement items (attendance, quiz average, lessons, assignments, manual approval)
            $table->timestamps();

            $table->index(['user_id', 'course_id', 'is_passed']);
        });

        // 3. Advanced Certificate Features (Revocation, Reissue, Public Verification Token, Open Badges)
        Schema::table('certificates', function (Blueprint $table): void {
            $table->string('public_token', 64)->nullable()->unique()->after('certificate_number');
            $table->foreignId('template_id')->nullable()->after('template_name')->constrained('certificate_templates')->nullOnDelete();
            $table->string('status', 30)->default('valid')->index()->after('issue_date'); // valid, revoked, reissued
            $table->timestamp('revoked_at')->nullable()->after('status');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by');
            $table->foreignId('original_certificate_id')->nullable()->after('revocation_reason')->constrained('certificates')->nullOnDelete();
            $table->foreignId('reissued_certificate_id')->nullable()->after('original_certificate_id')->constrained('certificates')->nullOnDelete();
            $table->string('pdf_path', 1000)->nullable()->after('metadata');
            $table->text('qr_code_svg')->nullable()->after('pdf_path');
            $table->json('rules_snapshot')->nullable()->after('qr_code_svg');
            $table->json('evaluation_snapshot')->nullable()->after('rules_snapshot');

            // Drop unique constraint on user_id, course_id if exists to allow reissued certs
            try {
                $table->dropUnique('certificates_user_id_course_id_unique');
            } catch (Throwable) {
                // ignore if not present or named differently
            }
        });

        // 4. Practical assessments & Rubric grading on Assignments
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('assessment_type', 40)->default('assignment')->after('title'); // assignment, quiz, practical, final_exam, teacher_evaluation
            $table->boolean('requires_video')->default(false)->after('submission_types');
        });

        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->string('video_url', 1000)->nullable()->after('link_url');
            $table->json('rubric_scores')->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->dropColumn(['video_url', 'rubric_scores']);
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['assessment_type', 'requires_video']);
        });

        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reissued_certificate_id');
            $table->dropConstrainedForeignId('original_certificate_id');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn([
                'public_token',
                'status',
                'revoked_at',
                'revocation_reason',
                'pdf_path',
                'qr_code_svg',
                'rules_snapshot',
                'evaluation_snapshot',
            ]);
        });

        Schema::dropIfExists('completion_evaluations');
        Schema::dropIfExists('certificate_templates');
    }
};
