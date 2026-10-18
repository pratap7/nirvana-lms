<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCatalogController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Filterable Course Catalog.
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');
        $categorySlug = $request->query('category');
        $mode = $request->query('mode');
        $type = $request->query('type');
        $priceFilter = $request->query('price'); // 'free', 'paid'
        $language = $request->query('lang');

        $query = Course::query()
            ->where('status', 'published')
            ->with(['category', 'teachers', 'batches' => function ($b) {
                $b->whereIn('status', ['open', 'in_progress'])
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at');
            }]);

        // Search filter
        if (! empty($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('long_description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if (! empty($categorySlug) && $categorySlug !== 'all') {
            $query->whereHas('category', function (Builder $q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Delivery Mode filter (online, offline, hybrid)
        if (! empty($mode) && $mode !== 'all') {
            $query->where('default_delivery_mode', $mode);
        }

        // Course Type filter (long_term, short_term)
        if (! empty($type) && $type !== 'all') {
            $query->where('type', $type);
        }

        // Price filter (free vs paid)
        if ($priceFilter === 'free') {
            $query->where('base_fee_amount', 0);
        } elseif ($priceFilter === 'paid') {
            $query->where('base_fee_amount', '>', 0);
        }

        // Language filter
        if (! empty($language) && $language !== 'all') {
            $query->where('language', $language);
        }

        $courses = $query->orderBy('sort_order')->paginate(12)->withQueryString();
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('public.courses.index', compact(
            'courses',
            'categories',
            'search',
            'categorySlug',
            'mode',
            'type',
            'priceFilter',
            'language'
        ));
    }

    /**
     * Course Detail Page.
     */
    public function show(string $slug): View
    {
        $course = Course::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'category',
                'teachers',
                'modules.lessons',
                'approvedReviews.student',
                'batches' => function ($b) {
                    $b->whereIn('status', ['open', 'in_progress'])
                        ->orderBy('starts_at');
                },
            ])
            ->firstOrFail();

        $schema = $this->seoService->getCourseSchema($course);

        $relatedCourses = Course::query()
            ->where('status', 'published')
            ->where('id', '!=', $course->id)
            ->where('category_id', $course->category_id)
            ->with(['category', 'teachers'])
            ->take(3)
            ->get();

        return view('public.courses.show', compact('course', 'schema', 'relatedCourses'));
    }
}
