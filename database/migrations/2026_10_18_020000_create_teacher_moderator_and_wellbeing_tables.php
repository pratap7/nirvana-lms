<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Wellbeing Escalations & Concerns
        Schema::create('wellbeing_concerns', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('category', 50); // academic_stress, mental_health, physical_safety, substance_use, harassment, other
            $table->string('urgency', 20)->default('medium'); // low, medium, high, critical
            $table->text('description'); // encrypted at rest in model
            $table->string('status', 30)->default('received'); // received, under_review, in_progress, resolved, escalated
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable(); // encrypted at rest in model
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 2. Batch Direct Messaging (strictly within shared batches)
        Schema::create('batch_messages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 3. Support Tickets & Inquiries
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('ticket_number', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 255);
            $table->string('category', 50)->default('general');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('open'); // open, in_progress, resolved, closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_staff_reply')->default(false);
            $table->timestamps();
        });

        // 4. Attendance Corrections & Audit Fields
        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->string('correction_reason', 255)->nullable()->after('notes');
            $table->foreignId('edited_by')->nullable()->after('marked_by')->constrained('users')->nullOnDelete();
        });

        // 5. Assignment Rubric JSON
        Schema::table('assignments', function (Blueprint $table): void {
            $table->json('rubric')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn('rubric');
        });

        Schema::table('session_attendances', function (Blueprint $table): void {
            $table->dropForeign(['edited_by']);
            $table->dropColumn(['correction_reason', 'edited_by']);
        });

        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('batch_messages');
        Schema::dropIfExists('wellbeing_concerns');
    }
};
