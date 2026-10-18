<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Read-optimized summary tables refreshed by scheduled jobs
        Schema::create('report_daily_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->index();
            $table->string('metric_group', 50)->index(); // enrollment, revenue, attendance, completion, certification, event, workload
            $table->string('dimension_key', 100)->default('all')->index(); // e.g. "all", "course:12", "gateway:stripe"
            $table->decimal('numeric_value', 14, 2)->default(0.00);
            $table->decimal('secondary_value', 14, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['date', 'metric_group', 'dimension_key'], 'uq_report_agg_date_group_dim');
            $table->index(['metric_group', 'date']);
        });

        // 2. Queued exports ledger with signed expiry tokens
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_type', 50)->index(); // enrollment_funnel, revenue, outstanding_dues, attendance_batch, course_completion, teacher_workload, certificate_issuance, event_registrations
            $table->string('format', 10)->default('csv'); // csv, xlsx
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, processing, completed, failed
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('report_daily_aggregates');
    }
};
