<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Catalog\Models\Course;
use App\Domain\Cms\Models\Article;
use App\Domain\Cms\Models\Faq;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * Generate Schema.org EducationalOrganization JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function getOrganizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => 'Pramana Institute of Yogic Science',
            'url' => url('/'),
            'logo' => url('/images/pramana-logo.png'),
            'description' => 'Centre of authentic Yogic science, Vedantic wisdom, and Yoga Alliance accredited teacher trainings.',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Badrinath Marg, Tapovan',
                'addressLocality' => 'Rishikesh',
                'addressRegion' => 'Uttarakhand',
                'postalCode' => '249192',
                'addressCountry' => 'IN',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+91-135-2430000',
                'contactType' => 'admissions',
                'areaServed' => ['IN', 'US', 'EU', 'GB'],
                'availableLanguage' => ['English', 'Hindi', 'Sanskrit'],
            ],
            'sameAs' => [
                'https://www.youtube.com/@pramanainstitute',
                'https://www.instagram.com/pramanainstitute',
                'https://www.facebook.com/pramanainstitute',
            ],
        ];
    }

    /**
     * Generate Schema.org Course JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function getCourseSchema(Course $course): array
    {
        $instructors = $course->teachers->map(fn (User $teacher) => [
            '@type' => 'Person',
            'name' => $teacher->full_name,
            'jobTitle' => $teacher->headline ?? 'Acharya',
        ])->toArray();

        $batches = $course->batches->map(fn ($batch) => [
            '@type' => 'CourseInstance',
            'courseMode' => $batch->mode ?? $course->default_delivery_mode->value,
            'startDate' => $batch->starts_at?->toIso8601String(),
            'endDate' => $batch->ends_at?->toIso8601String(),
            'name' => $batch->name,
        ])->toArray();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $course->title,
            'description' => $course->seo_description ?? $course->short_description ?? strip_tags((string) $course->description),
            'courseCode' => 'PRM-'.$course->id,
            'url' => route('courses.show', $course->slug),
            'provider' => [
                '@type' => 'EducationalOrganization',
                'name' => 'Pramana Institute',
                'url' => url('/'),
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (float) round($course->base_fee_amount / 100, 2),
                'priceCurrency' => $course->base_fee_currency ?? 'INR',
                'availability' => 'https://schema.org/InStock',
                'validFrom' => now()->startOfYear()->toIso8601String(),
            ],
            'instructor' => $instructors ?: [
                ['@type' => 'Person', 'name' => 'Pramana Senior Faculty'],
            ],
            'hasCourseInstance' => $batches,
            'educationalCredentialAwarded' => 'Accredited Yoga Alliance Certificate of Yogic Science',
            'timeRequired' => 'PT'.$course->duration_hours.'H',
        ];
    }

    /**
     * Generate Schema.org Event JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function getEventSchema(Event $event): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->name,
            'description' => $event->description,
            'startDate' => $event->start_date?->toIso8601String() ?? now()->toIso8601String(),
            'endDate' => $event->end_date?->toIso8601String() ?? now()->addDays(2)->toIso8601String(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $event->mode === 'online'
                ? 'https://schema.org/OnlineEventAttendanceMode'
                : 'https://schema.org/OfflineEventAttendanceMode',
            'location' => [
                '@type' => 'Place',
                'name' => 'Pramana Ashram Campus',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Rishikesh',
                    'addressRegion' => 'Uttarakhand',
                    'addressCountry' => 'IN',
                ],
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (float) round(($event->fee_amount ?? 0) / 100, 2),
                'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock',
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'Pramana Institute',
                'url' => url('/'),
            ],
        ];
    }

    /**
     * Generate Schema.org Person JSON-LD for Faculty.
     *
     * @return array<string, mixed>
     */
    public function getPersonSchema(User $user): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $user->full_name,
            'jobTitle' => $user->headline ?? 'Acharya & Yoga Master',
            'description' => $user->bio ?? 'Traditional Yogic scholar and master teacher at Pramana Institute.',
            'worksFor' => [
                '@type' => 'EducationalOrganization',
                'name' => 'Pramana Institute',
                'url' => url('/'),
            ],
            'knowsAbout' => [
                'Classical Hatha Yoga',
                'Patanjali Yoga Sutras',
                'Pranayama',
                'Vedanta Philosophy',
                'Sanskrit',
            ],
        ];
    }

    /**
     * Generate Schema.org Article JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function getArticleSchema(Article $article): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->excerpt ?? Str::limit(strip_tags($article->content), 160),
            'datePublished' => $article->published_at?->toIso8601String() ?? $article->created_at->toIso8601String(),
            'dateModified' => $article->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $article->author instanceof User ? $article->author->full_name : 'Pramana Research Faculty',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Pramana Institute',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => url('/images/pramana-logo.png'),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => route('articles.show', $article->slug),
            ],
        ];
    }

    /**
     * Generate Schema.org FAQPage JSON-LD.
     *
     * @param  Collection<int, Faq>  $faqs
     * @return array<string, mixed>
     */
    public function getFaqSchema(Collection $faqs): array
    {
        $entities = $faqs->map(fn (Faq $faq) => [
            '@type' => 'Question',
            'name' => $faq->question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq->answer,
            ],
        ])->toArray();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
