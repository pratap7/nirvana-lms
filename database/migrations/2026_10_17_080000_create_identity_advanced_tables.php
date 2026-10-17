<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add Profile, Address, 2FA, Deactivation to users table
        Schema::table('users', function (Blueprint $table): void {
            $table->string('address_line_1')->nullable()->after('primary_branch_id');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city', 100)->nullable()->after('address_line_2');
            $table->string('state', 100)->nullable()->after('city');
            $table->char('country', 2)->default('IN')->after('state');
            $table->string('postal_code', 20)->nullable()->after('country');

            // 2FA TOTP columns
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // Account deactivation / soft lifecycle
            $table->boolean('is_deactivated')->default(false)->after('status')->index();
            $table->timestamp('deactivated_at')->nullable()->after('is_deactivated');
        });

        // 2. Phone OTPs for optional passwordless phone authentication
        Schema::create('phone_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('otp_hash');
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // 3. Custom role_assignments table per requirement:
        // (scope_type/scope_id: global, course, batch, event, starts_at, expires_at, assigned_by)
        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 50)->default('global')->index(); // global, course, batch, event
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'role_id']);
            $table->index(['scope_type', 'scope_id']);
        });

        // 4. Guest Teacher invitations with email, token, named scope & auto-expiry
        Schema::create('guest_teacher_invitations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('email')->index();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('scope_type', 50); // e.g. batch, course
            $table->unsignedBigInteger('scope_id');
            $table->string('token', 64)->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        // 5. Versioned Consent records (terms, privacy policy, health/data-processing)
        Schema::create('user_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('consent_type', 50)->index(); // terms_and_conditions, privacy_policy, health_data_processing
            $table->string('version', 20); // e.g. 1.0, 2026.1
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('consented_at')->useCurrent();

            $table->index(['user_id', 'consent_type']);
        });

        // 6. Student sensitive profile: emergency contacts & PAR-Q encrypted at rest
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('emergency_contact_name')->nullable(); // encrypted
            $table->text('emergency_contact_phone')->nullable(); // encrypted
            $table->text('emergency_contact_relationship')->nullable(); // encrypted
            $table->longText('parq_answers')->nullable(); // encrypted json
            $table->longText('medical_conditions')->nullable(); // encrypted text
            $table->longText('allergies')->nullable(); // encrypted text
            $table->text('dietary_requirements')->nullable();
            $table->timestamps();
        });

        // 7. GDPR/DPDP-style data deletion requests workflow
        Schema::create('user_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index(); // pending, approved, rejected, completed
            $table->text('reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_deletion_requests');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('guest_teacher_invitations');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('phone_otps');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'country',
                'postal_code',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'is_deactivated',
                'deactivated_at',
            ]);
        });
    }
};
