<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Venues (Ashrams, Centres, Campuses)
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->char('country', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Rooms within venues with capacity
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(30);
            $table->string('floor', 50)->nullable();
            $table->string('room_type', 50)->default('studio'); // studio, classroom, hall, meditation_room
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'is_active']);
        });

        // 3. Batches (course delivery cohort)
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('delivery_mode', 30)->default('online'); // online, offline, hybrid
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('capacity')->default(30);
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->unsignedBigInteger('fee_override_amount')->nullable();
            $table->char('fee_override_currency', 3)->default('INR');
            $table->timestamp('enrollment_starts_at')->nullable();
            $table->timestamp('enrollment_ends_at')->nullable();
            $table->string('status', 30)->default('draft')->index(); // draft, open, in_progress, completed, cancelled
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'status']);
        });

        // 4. Batch recurrence schedule rules
        Schema::create('batch_schedules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->json('days_of_week'); // [1, 3, 5] for Mon, Wed, Fri
            $table->string('start_time', 8); // '06:00:00'
            $table->string('end_time', 8); // '07:30:00'
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->date('until_date');
            $table->boolean('exclude_holidays')->default(true);
            $table->timestamps();
        });

        // 5. Academic Course Sessions (named course_sessions to avoid collision with Laravel HTTP sessions table)
        Schema::create('course_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->boolean('is_online')->default(true);
            $table->string('meeting_provider', 30)->default('none'); // none, zoom, google_meet, manual
            $table->string('meeting_link', 1000)->nullable();
            $table->string('meeting_id', 100)->nullable();
            $table->string('meeting_password', 100)->nullable();
            $table->string('status', 30)->default('scheduled')->index(); // scheduled, in_progress, completed, cancelled, rescheduled
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('course_sessions')->nullOnDelete();
            $table->string('recording_url', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['batch_id', 'starts_at']);
            $table->index(['room_id', 'starts_at', 'ends_at']);
        });

        // 6. Session Teachers (lead, substitute, assistant)
        Schema::create('session_teachers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('course_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('lead'); // lead, substitute, assistant
            $table->string('status', 30)->default('approved'); // requested, approved, rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'user_id', 'role']);
            $table->index(['user_id', 'role', 'status']);
        });

        // 7. Session Recordings
        Schema::create('session_recordings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('session_id')->constrained('course_sessions')->cascadeOnDelete();
            $table->string('title');
            $table->string('provider', 30)->default('bunny'); // bunny, zoom, s3, vimeo
            $table->string('video_id_or_url', 1000);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_processed')->default(true);
            $table->timestamps();
        });

        // 8. Institute Holidays calendar
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->date('holiday_date')->index();
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
        });

        // 9. Add calendar_token to users table for ICS feed
        Schema::table('users', function (Blueprint $table): void {
            $table->string('calendar_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('calendar_token');
        });

        Schema::dropIfExists('holidays');
        Schema::dropIfExists('session_recordings');
        Schema::dropIfExists('session_teachers');
        Schema::dropIfExists('course_sessions');
        Schema::dropIfExists('batch_schedules');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('venues');
    }
};
