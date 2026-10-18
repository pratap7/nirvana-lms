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
        // 1. Articles / Blog posts table
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category', 60)->default('Philosophy');
            $table->json('tags')->nullable();
            $table->string('featured_image_path', 500)->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, archived
            $table->timestamp('published_at')->nullable()->index();
            $table->string('locale', 10)->default('en')->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedSmallInteger('reading_time_minutes')->default(5);
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. FAQs table
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 50)->default('General')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->string('locale', 10)->default('en')->index();
            $table->timestamps();
        });

        // 3. Testimonials table
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->string('author_name');
            $table->string('author_title')->nullable();
            $table->string('author_location')->nullable();
            $table->string('avatar_path', 500)->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('quote');
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->boolean('is_featured')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->string('locale', 10)->default('en')->index();
            $table->timestamps();
        });

        // 4. Contact Inquiries table with spam scoring
        Schema::create('contact_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->string('course_interest', 100)->nullable();
            $table->string('status', 20)->default('new')->index(); // new, in_review, replied, spam
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_spam')->default(false)->index();
            $table->timestamps();
        });

        // 5. Enhance Users table for faculty profiles
        Schema::table('users', function (Blueprint $table): void {
            $table->string('headline', 255)->nullable()->after('last_name');
            $table->text('bio')->nullable()->after('headline');
            $table->string('lineage', 255)->nullable()->after('bio');
            $table->boolean('is_featured_faculty')->default(false)->after('lineage')->index();
            $table->json('social_links')->nullable()->after('is_featured_faculty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_featured_faculty']);
            $table->dropColumn(['headline', 'bio', 'lineage', 'is_featured_faculty', 'social_links']);
        });

        Schema::dropIfExists('contact_inquiries');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('articles');
    }
};
