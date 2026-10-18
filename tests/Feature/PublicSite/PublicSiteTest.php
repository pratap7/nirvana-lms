<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\CourseReview;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Module;
use App\Domain\Cms\Models\Article;
use App\Domain\Cms\Models\Faq;
use App\Domain\Cms\Models\Testimonial;
use App\Domain\Events\Enums\EventFormat;
use App\Domain\Events\Enums\EventPricingType;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $acharya;

    protected Category $category;

    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Tapovan Ashram',
            'code' => 'TAP',
            'city' => 'Rishikesh',
            'state' => 'Uttarakhand',
            'country' => 'IN',
            'postal_code' => '249192',
        ]);

        $this->acharya = User::create([
            'first_name' => 'Swami',
            'last_name' => 'Shivamurti',
            'email' => 'shivamurti@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'headline' => 'Senior Vedanta Acharya',
            'bio' => 'Over 30 years of traditional Himalayan sadhana and scriptural study.',
            'lineage' => 'Dashanami Sannyasa',
            'is_featured_faculty' => true,
            'status' => UserStatus::Active,
            'primary_branch_id' => $this->branch->id,
        ]);

        $this->category = Category::create([
            'name' => 'Classical Hatha',
            'slug' => 'classical-hatha',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'name' => '200-Hour Classical Hatha Yoga Teacher Training',
            'slug' => 'ryt-200-classical-hatha',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'short_description' => 'Comprehensive foundation in classical asana, pranayama, and pedagogy.',
            'long_description' => 'Complete Gurukula immersion in Rishikesh with daily morning sadhana and theory.',
            'type' => CourseType::LongTerm,
            'default_delivery_mode' => DeliveryMode::Offline,
            'difficulty_level' => DifficultyLevel::Intermediate,
            'duration_hours' => 200,
            'base_fee_amount' => 8500000,
            'base_fee_currency' => 'INR',
            'status' => CourseStatus::Published,
            'language' => 'en',
            'rating_average' => 4.95,
            'reviews_count' => 15,
            'created_by' => $this->acharya->id,
            'published_at' => now()->subMonth(),
        ]);

        $this->course->teachers()->attach($this->acharya->id, ['role' => 'primary', 'sort_order' => 1]);

        // Module & Lesson
        $mod = Module::create([
            'course_id' => $this->course->id,
            'name' => 'Module 1: Asana Alignment',
            'sort_order' => 1,
        ]);
        Lesson::create([
            'module_id' => $mod->id,
            'title' => 'Tadasana Alignment & Biomechanics',
            'slug' => 'tadasana-alignment',
            'duration_minutes' => 60,
            'is_free_preview' => true,
            'sort_order' => 1,
        ]);

        // Batch
        Batch::create([
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'name' => 'Spring 2027 Cohort',
            'delivery_mode' => DeliveryMode::Offline,
            'status' => BatchStatus::Open,
            'start_date' => now()->addDays(25)->toDateString(),
            'end_date' => now()->addDays(55)->toDateString(),
            'capacity' => 20,
        ]);

        // Review
        $student = User::create([
            'first_name' => 'Aditi',
            'last_name' => 'Sharma',
            'email' => 'aditi@example.com',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
            'primary_branch_id' => $this->branch->id,
        ]);
        CourseReview::create([
            'course_id' => $this->course->id,
            'user_id' => $student->id,
            'rating' => 5,
            'review_text' => 'The most transformative month of my spiritual life. Highly recommended!',
            'status' => 'approved',
        ]);
    }

    public function test_home_page_loads_with_single_h1_opengraph_and_organization_schema(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Pramana Institute', false);
        $response->assertSee('200-Hour Classical Hatha Yoga Teacher Training', false);
        $response->assertSee('EducationalOrganization', false);
        $response->assertSee('Tapovan Ashram, Rishikesh', false);
        $response->assertSee('og:title', false);
        $response->assertSee('pramana_cookie_consent', false);

        // Verify accessible single H1 on home
        $content = $response->getContent();
        $this->assertSame(1, substr_count((string) $content, '<h1'), 'Expected exactly one <h1> tag on home page for SEO.');
    }

    public function test_course_catalog_loads_and_filters_accurately(): void
    {
        // 1. All courses
        $response = $this->get(route('courses.index'));
        $response->assertStatus(200);
        $response->assertSee('200-Hour Classical Hatha Yoga Teacher Training', false);

        // 2. Filter by category
        $catResponse = $this->get(route('courses.index', ['category' => 'classical-hatha']));
        $catResponse->assertStatus(200);
        $catResponse->assertSee('200-Hour Classical Hatha Yoga Teacher Training', false);

        // 3. Filter by delivery mode
        $modeResponse = $this->get(route('courses.index', ['mode' => 'offline']));
        $modeResponse->assertStatus(200);
        $modeResponse->assertSee('200-Hour Classical Hatha Yoga Teacher Training', false);

        // 4. Non-matching filter returns empty result message
        $emptyResponse = $this->get(route('courses.index', ['mode' => 'online', 'q' => 'NonExistentXYZ']));
        $emptyResponse->assertStatus(200);
        $emptyResponse->assertSee('No courses match your selected filter criteria', false);
    }

    public function test_course_detail_page_renders_curriculum_preview_teachers_reviews_batches_and_schema(): void
    {
        $response = $this->get(route('courses.show', $this->course->slug));

        $response->assertStatus(200);
        $response->assertSee($this->course->title, false);
        $response->assertSee('Module 1: Asana Alignment', false);
        $response->assertSee('Tadasana Alignment & Biomechanics');
        $response->assertSee('Free Preview', false);
        $response->assertSee('Swami Shivamurti', false);
        $response->assertSee('Spring 2027 Cohort', false);
        $response->assertSee('The most transformative month of my spiritual life', false);
        $response->assertSee('"@type": "Course"', false);
        $response->assertSee('Yoga Alliance Accredited', false);

        $content = $response->getContent();
        $this->assertSame(1, substr_count((string) $content, '<h1'), 'Expected exactly one <h1> tag on course detail page.');
    }

    public function test_events_calendar_displays_published_events(): void
    {
        $event = Event::create([
            'title' => 'Ganga Aarti & Satsang Gathering',
            'slug' => 'ganga-aarti-satsang-2027',
            'description' => 'Evening meditation by the sacred river with Vedic chanting.',
            'format' => EventFormat::InPerson,
            'pricing_type' => EventPricingType::Free,
            'status' => EventStatus::Published,
            'starts_at' => now()->addDays(10)->startOfDay(),
            'ends_at' => now()->addDays(10)->endOfDay(),
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'capacity' => 100,
            'branch_id' => $this->branch->id,
            'created_by' => $this->acharya->id,
        ]);

        $response = $this->get(route('events.calendar'));

        $response->assertStatus(200);
        $response->assertSee('Ganga Aarti & Satsang Gathering');
        $response->assertSee('Events, Workshops & Retreats');
    }

    public function test_faculty_directory_and_profile_views(): void
    {
        $listResponse = $this->get(route('faculty.index'));
        $listResponse->assertStatus(200);
        $listResponse->assertSee('Swami Shivamurti', false);
        $listResponse->assertSee('Senior Vedanta Acharya', false);

        $profileResponse = $this->get(route('faculty.show', $this->acharya->ulid));
        $profileResponse->assertStatus(200);
        $profileResponse->assertSee('Swami Shivamurti', false);
        $profileResponse->assertSee('Dashanami Sannyasa', false);
        $profileResponse->assertSee('200-Hour Classical Hatha Yoga Teacher Training', false);
        $profileResponse->assertSee('"@type": "Person"', false);
    }

    public function test_schedule_page_displays_timetable(): void
    {
        $response = $this->get(route('schedule'));

        $response->assertStatus(200);
        $response->assertSee('Master Academic Schedule', false);
        $response->assertSee('Spring 2027 Cohort', false);
    }

    public function test_about_page_renders_lineage_and_organization_schema(): void
    {
        $response = $this->get(route('about'));

        $response->assertStatus(200);
        $response->assertSee('What is "Pramana"?', false);
        $response->assertSee('Guru-Shishya Parampara', false);
        $response->assertSee('Yoga Alliance International', false);
        $response->assertSee('EducationalOrganization', false);
    }

    public function test_contact_form_submits_successfully_with_valid_token(): void
    {
        $validToken = encrypt(time() - 10); // submitted after 10 seconds (human)

        $response = $this->post(route('contact.submit'), [
            'name' => 'Claire Dupont',
            'email' => 'claire.dupont@example.com',
            'phone' => '+33 612345678',
            'subject' => 'Admissions for Spring YTT',
            'course_interest' => $this->course->name,
            'message' => 'I would like to know if accommodation is available for international students.',
            'hp_website' => '', // honeypot empty
            '_sub_token' => $validToken,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contact_inquiries', [
            'name' => 'Claire Dupont',
            'email' => 'claire.dupont@example.com',
            'is_spam' => false,
        ]);
    }

    public function test_contact_form_blocks_honeypot_spam(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Spam Bot',
            'email' => 'spambot@spammer.org',
            'message' => 'Buy cheap links now!',
            'hp_website' => 'http://malicious-spam-url.com', // honeypot filled by bot
        ]);

        $response->assertRedirect();
        // Should silently discard bot without saving to db
        $this->assertDatabaseMissing('contact_inquiries', [
            'email' => 'spambot@spammer.org',
        ]);
    }

    public function test_articles_index_and_detail_views(): void
    {
        $article = Article::create([
            'title' => 'Pranayama and the Autonomic Nervous System',
            'slug' => 'pranayama-autonomic-nervous-system',
            'excerpt' => 'A scientific study into heart rate variability during Bhastrika and Nadi Shodhana.',
            'content' => 'Full research paper detailing vagal tone enhancement through Kumbhaka retention.',
            'category' => 'Research',
            'author_id' => $this->acharya->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'reading_time_minutes' => 7,
            'locale' => 'en',
        ]);

        $listResponse = $this->get(route('articles.index'));
        $listResponse->assertStatus(200);
        $listResponse->assertSee('Pranayama and the Autonomic Nervous System', false);

        $detailResponse = $this->get(route('articles.show', $article->slug));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Pranayama and the Autonomic Nervous System', false);
        $detailResponse->assertSee('7 min read', false);
        $detailResponse->assertSee('"@type": "Article"', false);
    }

    public function test_faq_page_loads_and_filters(): void
    {
        Faq::create([
            'question' => 'How does residential ashram accommodation work?',
            'answer' => 'All rooms are clean, ventilated, and include daily sattvic organic meals.',
            'category' => 'Ashram',
            'sort_order' => 1,
            'is_published' => true,
            'locale' => 'en',
        ]);

        $response = $this->get(route('faq'));
        $response->assertStatus(200);
        $response->assertSee('Frequently Asked Questions', false);
        $response->assertSee('How does residential ashram accommodation work?', false);
        $response->assertSee('FAQPage', false);
    }

    public function test_testimonials_wall_displays_graduate_reviews(): void
    {
        Testimonial::create([
            'author_name' => 'Michael Chen',
            'author_title' => 'RYT-200 Teacher',
            'author_location' => 'Vancouver, Canada',
            'rating' => 5,
            'quote' => 'Studying with Swami Shivamurti in Tapovan gave me clarity I had sought for years.',
            'is_featured' => true,
            'locale' => 'en',
        ]);

        $response = $this->get(route('testimonials'));
        $response->assertStatus(200);
        $response->assertSee('Michael Chen', false);
        $response->assertSee('Studying with Swami Shivamurti', false);
    }

    public function test_sitemap_xml_generates_valid_xml_with_canonical_urls(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<urlset', false);
        $response->assertSee(route('courses.show', $this->course->slug), false);
        $response->assertSee(route('faculty.show', $this->acharya->ulid), false);
        $response->assertSee(route('home'), false);
    }

    public function test_robots_txt_serves_valid_disallow_rules_and_points_to_sitemap(): void
    {
        $response = $this->get(route('robots'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /moderator', false);
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_multi_language_locale_switcher(): void
    {
        $response = $this->get(route('set-locale', 'hi'));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'hi');
        $response->assertCookie('pramana_locale', 'hi');

        // Visit home page in Hindi
        $hiResponse = $this->withSession(['locale' => 'hi'])->get(route('home'));
        $hiResponse->assertStatus(200);
        $hiResponse->assertSee('प्रमाण संस्थान', false);
        $hiResponse->assertSee('पाठ्यक्रम', false);
    }
}
