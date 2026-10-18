<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PublicSiteSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first() ?? Branch::create([
            'name' => 'Tapovan Rishikesh Ashram',
            'code' => 'RSH',
            'city' => 'Rishikesh',
            'state' => 'Uttarakhand',
            'country' => 'IN',
            'postal_code' => '249192',
        ]);

        // 1. Seed Faculty Members / Acharyas
        $acharya1 = User::firstOrCreate(
            ['email' => 'acharya.shivam@pramana.edu'],
            [
                'first_name' => 'Swami',
                'last_name' => 'Shivamurti',
                'headline' => 'Mahamandaleshwar & Senior Vedanta Acharya',
                'bio' => 'Initiated in the sacred dashanami sannyasa lineage in Rishikesh. Over 35 years of immersive contemplation on the Prasthanatrayi and Patanjali Yoga Sutras. Guides senior teacher trainees in classical Sanskrit and non-dual contemplation.',
                'lineage' => 'Dashanami Sannyasa • Advaita Vedanta',
                'is_featured_faculty' => true,
                'password' => Hash::make('Secret123!'),
                'status' => UserStatus::Active,
                'primary_branch_id' => $branch->id,
            ]
        );

        $acharya2 = User::firstOrCreate(
            ['email' => 'devi.ananda@pramana.edu'],
            [
                'first_name' => 'Acharyaa',
                'last_name' => 'Anandapriya',
                'headline' => 'Master of Classical Hatha & Subtle Pranayama',
                'bio' => 'Trained since childhood under classical masters of Kerala and the Himalayas. Specializes in prana samyama, kumbhaka biomechanics, and therapeutic alignment. Lead instructor for the RYT-500 Master Teacher Track.',
                'lineage' => 'Classical Hatha Yoga • Sivananda Lineage',
                'is_featured_faculty' => true,
                'password' => Hash::make('Secret123!'),
                'status' => UserStatus::Active,
                'primary_branch_id' => $branch->id,
            ]
        );

        // 2. Seed Categories
        $catHatha = Category::firstOrCreate(['slug' => 'classical-hatha'], [
            'name' => 'Classical Hatha Yoga',
            'description' => 'Traditional asana alignment, bandhas, and therapeutic sadhana.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $catVedanta = Category::firstOrCreate(['slug' => 'vedanta-philosophy'], [
            'name' => 'Vedanta & Philosophy',
            'description' => 'Study of Upanishads, Bhagavad Gita, and contemplative meditation.',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $catPranayama = Category::firstOrCreate(['slug' => 'pranayama-breathwork'], [
            'name' => 'Pranayama & Kriya',
            'description' => 'Shatkriyas, pranic regulation, and subtle nervous system purification.',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // 3. Seed Courses with Modules, Lessons & Batches
        $course1 = Course::firstOrCreate(['slug' => 'ryt-200-classical-hatha-teacher-training'], [
            'name' => '200-Hour Classical Hatha Yoga Teacher Training',
            'short_description' => 'Comprehensive Yoga Alliance accredited foundation training covering classical asana, alignment, pranayama, anatomy, and pedagogy in Rishikesh.',
            'long_description' => "Immerse in authentic Himalayan Gurukula living at our Tapovan Ashram.\n\nThis 200-hour program systematically guides students through primary classical asanas, experiential anatomy, subtle energy channels (nadis and chakras), teaching methodology, and the philosophy of Patanjali.\n\nGraduates receive an internationally accredited RYS-200 diploma and lifetime mentorship.",
            'category_id' => $catHatha->id,
            'branch_id' => $branch->id,
            'type' => CourseType::LongTerm,
            'default_delivery_mode' => DeliveryMode::Offline,
            'difficulty_level' => DifficultyLevel::Intermediate,
            'duration_hours' => 200,
            'base_fee_amount' => 9500000, // ₹95,000 in paise
            'base_fee_currency' => 'INR',
            'status' => CourseStatus::Published,
            'language' => 'en',
            'rating_average' => 4.95,
            'reviews_count' => 38,
            'created_by' => $acharya1->id,
            'published_at' => now()->subMonths(3),
        ]);

        // Assign Teachers
        $course1->teachers()->syncWithoutDetaching([
            $acharya1->id => ['role' => 'primary', 'sort_order' => 1],
            $acharya2->id => ['role' => 'assistant', 'sort_order' => 2],
        ]);

        // Create Modules & Lessons
        if ($course1->modules()->count() === 0) {
            $mod1 = Module::create([
                'course_id' => $course1->id,
                'name' => 'Module 1: Foundations of Asana & Biomechanics',
                'sort_order' => 1,
            ]);
            Lesson::create([
                'module_id' => $mod1->id,
                'title' => 'Tadasana and Spinal Axial Extension',
                'slug' => 'tadasana-spinal-extension',
                'content_type' => 'video',
                'duration_minutes' => 60,
                'is_free_preview' => true,
                'sort_order' => 1,
            ]);
            Lesson::create([
                'module_id' => $mod1->id,
                'title' => 'Surya Namaskar: Classical 12 Mantras and Breath Synch',
                'slug' => 'surya-namaskar-classical-mantras',
                'content_type' => 'video',
                'duration_minutes' => 75,
                'is_free_preview' => false,
                'sort_order' => 2,
            ]);

            $mod2 = Module::create([
                'course_id' => $course1->id,
                'name' => 'Module 2: Pranayama, Shatkarmas & Subtle Anatomy',
                'sort_order' => 2,
            ]);
            Lesson::create([
                'module_id' => $mod2->id,
                'title' => 'Nadi Shodhana & Anuloma Viloma: Ratio Progressions',
                'slug' => 'nadi-shodhana-ratio-progressions',
                'content_type' => 'video',
                'duration_minutes' => 45,
                'is_free_preview' => true,
                'sort_order' => 1,
            ]);
        }

        // Create Upcoming Batches
        if ($course1->batches()->count() === 0) {
            Batch::create([
                'course_id' => $course1->id,
                'branch_id' => $branch->id,
                'name' => 'Winter Immersion Cohort 2027',
                'delivery_mode' => DeliveryMode::Offline,
                'status' => BatchStatus::Open,
                'start_date' => now()->addDays(20)->toDateString(),
                'end_date' => now()->addDays(48)->toDateString(),
                'capacity' => 24,
            ]);
        }

        // Course 2: Online Vedanta
        $course2 = Course::firstOrCreate(['slug' => 'mandukya-upanishad-contemplation-masterclass'], [
            'name' => 'Mandukya Upanishad & Karika: Contemplative Masterclass',
            'short_description' => 'A verse-by-verse exposition of the twelve sacred mantras on Aum, the four states of consciousness, and non-dual realization.',
            'long_description' => "The Mandukya Upanishad is celebrated by Adi Shankaracharya and Gaudapada as containing the complete essence of Advaita Vedanta.\n\nOver 40 live interactive lectures, Swami Shivamurti unpacks the Gaudapada Karika, dissecting waking, dreaming, deep sleep, and the transcendent Turiya state.",
            'category_id' => $catVedanta->id,
            'branch_id' => $branch->id,
            'type' => CourseType::ShortTerm,
            'default_delivery_mode' => DeliveryMode::Online,
            'difficulty_level' => DifficultyLevel::Advanced,
            'duration_hours' => 50,
            'base_fee_amount' => 1800000, // ₹18,000 in paise
            'base_fee_currency' => 'INR',
            'status' => CourseStatus::Published,
            'language' => 'en',
            'rating_average' => 4.98,
            'reviews_count' => 52,
            'created_by' => $acharya1->id,
            'published_at' => now()->subMonths(2),
        ]);
        $course2->teachers()->syncWithoutDetaching([
            $acharya1->id => ['role' => 'primary', 'sort_order' => 1],
        ]);

        // 4. Seed Articles / Blog Posts
        Article::firstOrCreate(['slug' => 'the-science-of-prana-samyama'], [
            'title' => 'The Epistemology of Prana: How Breath Regulation Transforms the Mind',
            'excerpt' => 'An exploration into the physiological and subtle energetic mechanisms of Kumbhaka breath retention as documented in the Hatha Yoga Pradipika.',
            'content' => "In the classical texts of Hatha Yoga, the mind and prana are likened to two milk-fed calves bound to the same yoke. Where prana moves, citta (mental fluctuations) follows.\n\nModern neurobiology confirms that intentional prolongation of exhalation activates the parasympathetic vagal brake, reducing sympathetic amygdalar arousal within minutes.\n\nThrough systematic practice of Sahita Kumbhaka under an experienced Acharya, practitioners develop unwavering mental sthirata (stability) necessary for meditative absorption.",
            'category' => 'Pranayama',
            'author_id' => $acharya2->id,
            'status' => 'published',
            'published_at' => now()->subDays(5),
            'reading_time_minutes' => 6,
            'is_featured' => true,
            'locale' => 'en',
        ]);

        Article::firstOrCreate(['slug' => 'understanding-the-three-pramanas'], [
            'title' => 'The Three Pramanas: Why True Yoga is an Empirical Science',
            'excerpt' => 'Why Patanjali placed Pramana (valid cognition) at the foundation of mental clarity, distinguishing authentic realization from delusion and imagination.',
            'content' => "Yoga is frequently misunderstood in popular culture as either blind faith or physical contortionism. In contrast, the Patanjali Yoga Darshana establishes a rigorous cognitive science based on Pramana.\n\nDirect perception (Pratyaksha) must be refined through sensory stillness. Inference (Anumana) guarantees logical integrity. Scriptural testimony (Agama) provides maps from those who have traversed the summit of consciousness before us.",
            'category' => 'Philosophy',
            'author_id' => $acharya1->id,
            'status' => 'published',
            'published_at' => now()->subDays(12),
            'reading_time_minutes' => 8,
            'is_featured' => true,
            'locale' => 'en',
        ]);

        // 5. Seed FAQs
        Faq::firstOrCreate(['question' => 'Are Pramana Institute diplomas recognized by Yoga Alliance?'], [
            'answer' => 'Yes. Pramana Institute is an accredited Registered Yoga School (RYS 200, RYS 300, and RYS 500) with Yoga Alliance International. Upon successful completion of evaluation and practical exams, graduates can immediately register as RYT credentialed teachers globally.',
            'category' => 'Certifications',
            'sort_order' => 1,
            'is_published' => true,
            'locale' => 'en',
        ]);

        Faq::firstOrCreate(['question' => 'What is included in the Tapovan Ashram residential tuition fee?'], [
            'answer' => 'Residential tuition covers private or shared ashram room accommodation, three freshly prepared Sattvic organic vegetarian meals daily, all course manuals, study shastras, yoga props, weekend pilgrimage excursions in Rishikesh, and the examination/certification fee.',
            'category' => 'General',
            'sort_order' => 2,
            'is_published' => true,
            'locale' => 'en',
        ]);

        Faq::firstOrCreate(['question' => 'Can international students attend on an Indian tourist or e-visa?'], [
            'answer' => 'International students typically attend our residential workshops and teacher training programs on standard e-tourist or student visas. We provide official letters of admission and invitation upon confirmation of your registration.',
            'category' => 'Admissions',
            'sort_order' => 3,
            'is_published' => true,
            'locale' => 'en',
        ]);

        // 6. Seed Testimonials
        Testimonial::firstOrCreate(['author_name' => 'Elena Rostova'], [
            'author_title' => 'RYT-500 Certified Yoga Teacher',
            'author_location' => 'Zurich, Switzerland',
            'rating' => 5,
            'quote' => 'Pramana Institute in Rishikesh changed my entire understanding of yoga. The depth of Vedanta with Swami Shivamurti and the precision of asana alignment cannot be matched by Western studios. I feel completely grounded in my teaching.',
            'course_id' => $course1->id,
            'is_featured' => true,
            'sort_order' => 1,
            'locale' => 'en',
        ]);

        Testimonial::firstOrCreate(['author_name' => 'Dr. Rajesh Deshmukh'], [
            'author_title' => 'Ayurvedic Physician & Graduate',
            'author_location' => 'Pune, India',
            'rating' => 5,
            'quote' => 'The authentic integration of Patanjali sutras with therapeutic breathwork is unmatched. The Acharyas uphold the ancient parampara while ensuring each student receives personalized corrections.',
            'course_id' => $course1->id,
            'is_featured' => true,
            'sort_order' => 2,
            'locale' => 'en',
        ]);

        // 7. Seed an Upcoming Event
        Event::firstOrCreate(['slug' => 'himalayan-meditation-retreat-2027'], [
            'title' => 'Himalayan Silence & Upanishadic Sadhana Retreat',
            'description' => 'A 7-day silent contemplative retreat in Rishikesh with daily morning Ganga sadhana, guided Pranayama, and Mandukya Upanishad meditation sessions.',
            'format' => EventFormat::InPerson,
            'pricing_type' => EventPricingType::Paid,
            'status' => EventStatus::Published,
            'starts_at' => now()->addDays(30)->startOfDay(),
            'ends_at' => now()->addDays(37)->endOfDay(),
            'fee_amount' => 4500000,
            'fee_currency' => 'INR',
            'capacity' => 30,
            'branch_id' => $branch->id,
            'created_by' => $acharya1->id,
        ]);
    }
}
