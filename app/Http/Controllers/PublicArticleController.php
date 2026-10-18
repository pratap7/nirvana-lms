<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Course;
use App\Domain\Cms\Models\Article;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Articles & Blog Index.
     */
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search = $request->query('q');

        $query = Article::query()->published()->with('author');

        if (! empty($category) && $category !== 'all') {
            $query->where('category', $category);
        }

        if (! empty($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $articles = $query->latest('published_at')->paginate(9)->withQueryString();
        $categories = Article::query()->published()->distinct()->pluck('category')->filter();

        return view('public.articles.index', compact('articles', 'categories', 'category', 'search'));
    }

    /**
     * Single Article Detail.
     */
    public function show(string $slug): View
    {
        $article = Article::query()
            ->where('slug', $slug)
            ->published()
            ->with('author')
            ->firstOrFail();

        // Increment view count quietly
        $article->incrementQuietly('view_count');

        $schema = $this->seoService->getArticleSchema($article);

        $relatedCourses = Course::query()
            ->where('status', 'published')
            ->take(3)
            ->get();

        $recentArticles = Article::query()
            ->published()
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('public.articles.show', compact('article', 'schema', 'relatedCourses', 'recentArticles'));
    }
}
