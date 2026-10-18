<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Section H Communication.
     */
    public function up(): void
    {
        // 1. Notification Templates
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->string('category', 50)->default('academic'); // academic, billing, reminders, safety, system
            $table->json('channels'); // ['mail', 'database', 'whatsapp', 'sms']
            $table->boolean('is_mandatory')->default(false)->index(); // payments, safety bypass quiet hours & unsubscribe
            $table->json('default_variables')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Notification Template Translations (per-language, per-channel)
        Schema::create('notification_template_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->string('locale', 10)->default('en'); // en, hi
            $table->string('channel', 20)->default('mail'); // mail, database, whatsapp, sms
            $table->string('subject')->nullable(); // for email
            $table->longText('body');
            $table->string('whatsapp_template_name')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['template_id', 'locale', 'channel'], 'tpl_locale_channel_unique');
        });

        // 3. Notification Outbox (reliable delivery, retries, backoff, idempotency)
        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20); // mail, database, whatsapp, sms
            $table->string('template_key', 80);
            $table->string('notification_type', 50);
            $table->string('recipient', 255); // email, phone, user ID
            $table->string('subject')->nullable();
            $table->longText('content');
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('queued')->index(); // queued, sent, failed, delivered, held_for_quiet_hours
            $table->boolean('is_mandatory')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->string('idempotency_key', 120)->unique()->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'channel']);
            $table->index(['status', 'created_at']);
        });

        // 4. Quiet Hours on Users table
        Schema::table('users', function (Blueprint $table): void {
            $table->time('quiet_hours_start')->nullable()->default('22:00:00');
            $table->time('quiet_hours_end')->nullable()->default('07:00:00');
        });

        // 5. Announcement Read Receipts
        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });

        // 6. Enhanced Teacher-Student Batch Message Moderation & Flags
        Schema::table('batch_messages', function (Blueprint $table): void {
            $table->timestamp('flagged_at')->nullable()->index();
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('flag_reason')->nullable();
            $table->boolean('is_reviewed_by_admin')->default(false)->index();
            $table->text('admin_review_notes')->nullable();
            $table->foreignId('admin_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_reviewed_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_messages', function (Blueprint $table): void {
            $table->dropForeign(['flagged_by']);
            $table->dropForeign(['admin_reviewed_by']);
            $table->dropColumn([
                'flagged_at',
                'flagged_by',
                'flag_reason',
                'is_reviewed_by_admin',
                'admin_review_notes',
                'admin_reviewed_by',
                'admin_reviewed_at',
                'archived_at',
            ]);
        });

        Schema::dropIfExists('announcement_reads');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['quiet_hours_start', 'quiet_hours_end']);
        });

        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notification_template_translations');
        Schema::dropIfExists('notification_templates');
    }
};
