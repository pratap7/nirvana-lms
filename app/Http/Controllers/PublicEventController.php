<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Events\Models\Event;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Events Calendar & List.
     */
    public function calendar(Request $request): View
    {
        $timeframe = $request->query('timeframe', 'upcoming'); // upcoming, past, all
        $mode = $request->query('mode'); // online, offline
        $type = $request->query('type'); // workshop, retreat, satsang, webinar

        $query = Event::query()->where('status', 'published');

        if ($timeframe === 'upcoming') {
            $query->where('starts_at', '>=', now())->orderBy('starts_at');
        } elseif ($timeframe === 'past') {
            $query->where('ends_at', '<', now())->orderByDesc('starts_at');
        } else {
            $query->orderBy('starts_at');
        }

        if (! empty($mode) && $mode !== 'all') {
            $query->where('mode', $mode);
        }

        if (! empty($type) && $type !== 'all') {
            $query->where('type', $type);
        }

        $events = $query->paginate(9)->withQueryString();
        $schema = $this->seoService->getOrganizationSchema();

        return view('public.events.index', compact('events', 'timeframe', 'mode', 'type', 'schema'));
    }
}
