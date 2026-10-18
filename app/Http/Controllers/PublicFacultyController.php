<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Services\SeoService;
use Illuminate\View\View;

class PublicFacultyController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Faculty Profiles Directory.
     */
    public function index(): View
    {
        $faculty = User::query()
            ->faculty()
            ->with(['taughtCourses'])
            ->orderByDesc('is_featured_faculty')
            ->paginate(12);

        $schema = $this->seoService->getOrganizationSchema();

        return view('public.faculty.index', compact('faculty', 'schema'));
    }

    /**
     * Individual Faculty Profile Detail.
     */
    public function show(User $user): View
    {
        $user->load(['taughtCourses' => function ($q) {
            $q->where('status', 'published');
        }]);

        $schema = $this->seoService->getPersonSchema($user);

        return view('public.faculty.show', compact('user', 'schema'));
    }
}
