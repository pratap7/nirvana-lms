<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Events Master
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('format', 30)->default('in_person'); // in_person, online, hybrid
            $table->string('pricing_type', 30)->default('free'); // free, paid, invitation_only
            $table->unsignedBigInteger('fee_amount')->default(0); // in minor units
            $table->char('fee_currency', 3)->default('INR');
            $table->boolean('is_recurring')->default(false);
            $table->string('rrule')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('online_meeting_link')->nullable();
            $table->unsignedInteger('capacity')->default(100);
            $table->timestamp('registration_deadline')->nullable();
            $table->boolean('allow_waitlist')->default(true);
            $table->boolean('allow_plus_one')->default(true);
            $table->boolean('issue_certificates')->default(true);
            $table->string('certificate_rule', 50)->default('attendance_only');
            $table->string('status', 30)->default('published'); // draft, published, cancelled, completed
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['starts_at', 'status']);
        });

        // 2. Event Occurrences (one-time or recurring expanded instances)
        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('occurrence_number')->default(1);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('online_meeting_link')->nullable();
            $table->string('status', 30)->default('scheduled'); // scheduled, cancelled, completed
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['starts_at', 'status']);
        });

        // 3. Event Speakers / Guest Teachers
        Schema::create('event_speakers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('is_lead')->default(false);
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        // 4. Event Invitations (for invitation-only events, signed single-use links, CSV import)
        Schema::create('event_invitations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->string('status', 30)->default('pending'); // pending, used, expired
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'email']);
        });

        // 5. Event Registrations (attendees, tickets, plus-ones, check-ins)
        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('has_plus_one')->default(false);
            $table->string('plus_one_name')->nullable();
            $table->string('plus_one_email')->nullable();
            $table->string('ticket_code', 64)->unique();
            $table->text('qr_code_svg')->nullable();
            $table->boolean('is_checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('event_invitations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'user_id']);
            $table->index(['ticket_code']);
        });

        // 6. Post-Event Feedback
        Schema::create('event_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5 stars
            $table->unsignedTinyInteger('recommendation_score')->nullable(); // 1-10 NPS
            $table->text('comments')->nullable();
            $table->text('highlights')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        // 7. Polymorphic integrations on existing tables for reuse
        Schema::table('invoices', function (Blueprint $table): void {
            $table->nullableMorphs('billable');
            $table->foreignId('event_id')->nullable()->after('course_id')->constrained('events')->nullOnDelete();
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->change();
            $table->foreignId('course_id')->nullable()->change();
            $table->nullableMorphs('enrollable');
            $table->foreignId('event_id')->nullable()->after('course_id')->constrained('events')->nullOnDelete();
        });

        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->foreignId('session_id')->nullable()->change();
            $table->nullableMorphs('attendable');
            $table->foreignId('event_occurrence_id')->nullable()->after('session_id')->constrained('event_occurrences')->nullOnDelete();
        });

        Schema::table('certificates', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->change();
            $table->nullableMorphs('certifiable');
            $table->foreignId('event_id')->nullable()->after('batch_id')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropMorphs('certifiable');
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->dropMorphs('attendable');
            $table->dropConstrainedForeignId('event_occurrence_id');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropMorphs('enrollable');
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropMorphs('billable');
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::dropIfExists('event_feedbacks');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_invitations');
        Schema::dropIfExists('event_speakers');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
    }
};
