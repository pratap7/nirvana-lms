<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add waitlist and seat hold configuration to batches table
        Schema::table('batches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('seat_hold_hours')->default(24)->after('capacity');
            $table->boolean('allow_waitlist')->default(true)->after('seat_hold_hours');
            $table->boolean('auto_promote_waitlist')->default(true)->after('allow_waitlist');
            $table->boolean('requires_approval')->default(false)->after('auto_promote_waitlist');
        });

        // 2. Enrollments table
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('status', 30)->default('requested')->index();
            $table->timestamp('seat_hold_expires_at')->nullable()->index();
            $table->unsignedInteger('waitlist_position')->nullable()->index();
            $table->boolean('is_offline_registration')->default(false);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('consent_given')->default(true);
            $table->timestamp('consent_given_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->boolean('prerequisites_verified')->default(false);
            $table->text('prerequisites_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('drop_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['batch_id', 'status']);
            $table->index(['student_id', 'batch_id']);
            $table->index(['student_id', 'course_id', 'status']);
        });

        // 3. Enrollment status histories (Timeline of every state change)
        Schema::create('enrollment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
        });

        // 4. Enrollment transfers between batches
        Schema::create('enrollment_transfers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('source_batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('target_batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('pro_rata_refund_amount')->nullable();
            $table->unsignedBigInteger('pro_rata_fee_due_amount')->nullable();
            $table->char('currency', 3)->default('INR');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_transfers');
        Schema::dropIfExists('enrollment_status_histories');
        Schema::dropIfExists('enrollments');

        Schema::table('batches', function (Blueprint $table): void {
            $table->dropColumn([
                'seat_hold_hours',
                'allow_waitlist',
                'auto_promote_waitlist',
                'requires_approval',
            ]);
        });
    }
};
