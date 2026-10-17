<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invoice sequence counter for gap-free sequential numbers per Financial Year
        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->string('financial_year', 10)->primary(); // e.g. "2026-27"
            $table->unsignedBigInteger('current_number')->default(0);
            $table->timestamps();
        });

        // 2. Invoices ledger master
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('invoice_number', 50)->unique();
            $table->string('financial_year', 10)->index();
            $table->unsignedBigInteger('sequence_number');
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->unsignedBigInteger('subtotal_amount'); // in minor units (paise/cents)
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3)->default('INR');
            $table->string('status', 30)->default('pending')->index(); // derived: pending, partially_paid, paid, failed, refunded
            $table->date('due_date');
            $table->timestamp('issued_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('tax_breakup')->nullable(); // CGST, SGST, IGST, SAC code
            $table->string('billing_name');
            $table->string('billing_email');
            $table->text('billing_address')->nullable();
            $table->string('billing_gstin', 20)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
            $table->index(['financial_year', 'sequence_number']);
        });

        // 3. Invoice line items
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('item_type', 50)->default('course_fee'); // course_fee, installment, registration, material, discount
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_amount');
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3)->default('INR');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 4. Installment plans
        Schema::create('installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedTinyInteger('total_installments')->default(2);
            $table->unsignedInteger('frequency_days')->default(30);
            $table->unsignedTinyInteger('grace_days')->default(7);
            $table->string('status', 30)->default('active')->index(); // active, completed, defaulted, cancelled
            $table->timestamps();
        });

        // 5. Individual installments
        Schema::create('installments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('INR');
            $table->date('due_date');
            $table->date('grace_date');
            $table->boolean('is_overdue')->default(false)->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['installment_plan_id', 'installment_number']);
            $table->index(['due_date', 'is_overdue']);
        });

        // 6. Payments ledger entries
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('INR');
            $table->string('method', 50)->default('stripe'); // stripe, upi, bank_transfer, cash, scholarship
            $table->string('gateway', 50)->default('stripe'); // stripe, manual, internal
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('status', 30)->default('pending')->index(); // pending, verified, failed, refunded
            $table->string('reference_number', 100)->nullable();
            $table->string('proof_path', 1000)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); // maker
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete(); // checker
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });

        // 7. Refunds ledger
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('INR');
            $table->string('reason');
            $table->string('policy_applied', 100)->nullable(); // 100_percent_before_start, 50_percent_week_one, discretionary
            $table->string('gateway_refund_id')->nullable()->index();
            $table->string('status', 30)->default('processed')->index(); // pending, processed, failed
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Coupons (discounts)
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 50)->unique();
            $table->string('discount_type', 30)->default('percentage'); // percentage, fixed
            $table->unsignedInteger('discount_value'); // e.g. 20 for 20%, or minor units for fixed
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 9. Coupon redemptions
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->char('currency', 3)->default('INR');
            $table->timestamps();

            $table->unique(['coupon_id', 'user_id', 'invoice_id']);
        });

        // 10. Scholarships
        Schema::create('scholarships', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('type', 30)->default('percentage'); // full, percentage, fixed
            $table->unsignedInteger('value'); // 100 for full, or percentage, or fixed amount in minor units
            $table->text('reason');
            $table->string('status', 30)->default('requested')->index(); // requested, approved, rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // 11. Gateway webhook events table for idempotent deduplication and raw payload storage
        Schema::create('gateway_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('gateway', 50)->default('stripe')->index();
            $table->string('gateway_event_id', 255)->unique(); // e.g. evt_...
            $table->string('event_type', 100)->index(); // e.g. payment_intent.succeeded
            $table->text('signature')->nullable();
            $table->longText('payload');
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_webhook_events');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
