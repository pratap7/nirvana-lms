<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Course;
use App\Domain\Cms\Models\Article;
use App\Domain\Cms\Models\ContactInquiry;
use App\Domain\Cms\Models\Faq;
use App\Domain\Cms\Models\Testimonial;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Services\SeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session as SessionFacade;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * 1. Public Homepage.
     */
    public function home(): View
    {
        $featuredCourses = Course::query()
            ->where('status', 'published')
            ->with(['category', 'teachers'])
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        $upcomingEvents = Event::query()
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->take(3)
            ->get();

        $testimonials = Testimonial::query()
            ->featured()
            ->take(4)
            ->get();

        $latestArticles = Article::query()
            ->published()
            ->with('author')
            ->latest('published_at')
            ->take(3)
            ->get();

        $featuredFaculty = User::query()
            ->faculty()
            ->whereNotNull('bio')
            ->take(4)
            ->get();

        $schema = $this->seoService->getOrganizationSchema();

        return view('public.home', compact(
            'featuredCourses',
            'upcomingEvents',
            'testimonials',
            'latestArticles',
            'featuredFaculty',
            'schema'
        ));
    }

    /**
     * 2. About Page (Tradition, Lineage, Mission, Campuses).
     */
    public function about(): View
    {
        $facultyCount = User::query()->faculty()->count();
        $courseCount = Course::query()->where('status', 'published')->count();
        $schema = $this->seoService->getOrganizationSchema();

        return view('public.about', compact('facultyCount', 'courseCount', 'schema'));
    }

    /**
     * 3. Master Public Schedule Timetable.
     */
    public function schedule(Request $request): View
    {
        $now = now();
        $upcomingSessions = Session::query()
            ->where('starts_at', '>=', $now->startOfDay())
            ->where('starts_at', '<=', $now->copy()->addDays(14)->endOfDay())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with(['batch.course', 'teachers'])
            ->orderBy('starts_at')
            ->paginate(25);

        $activeBatches = Batch::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->with(['course.teachers'])
            ->orderBy('start_date')
            ->get();

        return view('public.schedule', compact('upcomingSessions', 'activeBatches'));
    }

    /**
     * 4. Contact Form Page.
     */
    public function contact(): View
    {
        $courses = Course::query()->where('status', 'published')->pluck('name', 'name');
        $schema = $this->seoService->getOrganizationSchema();
        $submissionToken = encrypt(time());

        return view('public.contact', compact('courses', 'schema', 'submissionToken'));
    }

    /**
     * 5. Contact Form Submission with Spam Protection.
     */
    public function contactSubmit(Request $request): RedirectResponse
    {
        // 1. IP Rate Limiting (max 5 requests per 60 seconds)
        $ip = (string) $request->ip();
        $rateKey = 'contact-submit:'.$ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()->withInput()->with('error', "Too many submissions. Please wait {$seconds} seconds before trying again.");
        }
        RateLimiter::hit($rateKey, 60);

        // 2. Honeypot check: hidden field 'hp_website' must be completely empty
        if (! empty($request->input('hp_website'))) {
            // Silently discard bot spam submission
            return back()->with('success', 'Thank you for reaching out. We have received your message and will respond shortly.');
        }

        // 3. Timing Token check: if submitted in under 2 seconds, it is automated script spam
        $submissionToken = $request->input('_sub_token');
        if (! empty($submissionToken)) {
            try {
                $formLoadedAt = (int) decrypt((string) $submissionToken);
                if (time() - $formLoadedAt < 2) {
                    // Bot filled form instantaneously
                    return back()->with('success', 'Thank you for reaching out. We have received your message.');
                }
            } catch (\Throwable) {
                // Ignore decrypt error and proceed
            }
        }

        // 4. Validate user input
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'subject' => 'nullable|string|max:200',
            'course_interest' => 'nullable|string|max:100',
            'message' => 'required|string|min:10|max:3000',
        ]);

        ContactInquiry::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? 'General Inquiry',
            'course_interest' => $validated['course_interest'] ?? null,
            'message' => $validated['message'],
            'ip_address' => $ip,
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'status' => 'new',
            'is_spam' => false,
        ]);

        return back()->with('success', 'Thank you for reaching out to Pramana Institute. An Acharya or admissions advisor will contact you within 24 hours.');
    }

    /**
     * 6. Frequently Asked Questions (FAQ).
     */
    public function faq(Request $request): View
    {
        $search = $request->query('q');
        $selectedCategory = $request->query('category');

        $query = Faq::query()->published();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if (! empty($selectedCategory) && $selectedCategory !== 'all') {
            $query->where('category', $selectedCategory);
        }

        $faqs = $query->get();
        $categories = Faq::query()->published()->distinct()->pluck('category')->filter();
        $schema = $this->seoService->getFaqSchema($faqs);

        return view('public.faq', compact('faqs', 'categories', 'search', 'selectedCategory', 'schema'));
    }

    /**
     * 7. Testimonials Wall.
     */
    public function testimonials(): View
    {
        $testimonials = Testimonial::query()
            ->with('course')
            ->orderBy('sort_order')
            ->paginate(12);

        return view('public.testimonials', compact('testimonials'));
    }

    /**
     * 8. Dynamic Sitemap XML.
     */
    public function sitemap(): Response
    {
        $courses = Course::query()->where('status', 'published')->get(['slug', 'updated_at']);
        $events = Event::query()->where('status', 'published')->get(['slug', 'updated_at']);
        $articles = Article::query()->published()->get(['slug', 'updated_at']);
        $faculty = User::query()->faculty()->whereNotNull('bio')->get(['ulid', 'updated_at']);

        $content = view('public.sitemap', compact('courses', 'events', 'articles', 'faculty'))->render();

        return response($content, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * 9. Robots.txt.
     */
    public function robots(): Response
    {
        $sitemapUrl = url('/sitemap.xml');
        $robotsContent = <<<ROBOTS
User-agent: *
Allow: /
Disallow: /admin
Disallow: /moderator
Disallow: /student
Disallow: /teacher
Disallow: /impersonation

Sitemap: {$sitemapUrl}
ROBOTS;

        return response($robotsContent, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * 10. Switch Locale Route.
     */
    public function setLocale(string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'hi'], true)) {
            SessionFacade::put('locale', $locale);
            Cookie::queue('pramana_locale', $locale, 60 * 24 * 365);
        }

        return back();
    }
}
