@extends('layouts.public')

@section('title', 'Events, Workshops & Retreats — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Discover upcoming Yogic workshops, spiritual retreats in Rishikesh, international webinars, and satsangs hosted by Pramana Institute.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-3">
            Events, Workshops & Retreats
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            Join experiential intensives, spiritual pilgrimages in the Himalayas, and live global masterclasses with our Acharyas.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Filter Bar --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-10 pb-6 border-b border-stone-800">
        {{-- Timeframe Tabs --}}
        <div class="flex items-center bg-stone-900 p-1 rounded-xl border border-stone-800 text-xs font-semibold">
            <a href="{{ route('events.calendar', ['timeframe' => 'upcoming']) }}" class="px-4 py-2 rounded-lg {{ $timeframe === 'upcoming' ? 'bg-amber-500 text-stone-950 font-bold' : 'text-stone-400 hover:text-white' }}">
                Upcoming Events
            </a>
            <a href="{{ route('events.calendar', ['timeframe' => 'past']) }}" class="px-4 py-2 rounded-lg {{ $timeframe === 'past' ? 'bg-amber-500 text-stone-950 font-bold' : 'text-stone-400 hover:text-white' }}">
                Past Gatherings
            </a>
            <a href="{{ route('events.calendar', ['timeframe' => 'all']) }}" class="px-4 py-2 rounded-lg {{ $timeframe === 'all' ? 'bg-amber-500 text-stone-950 font-bold' : 'text-stone-400 hover:text-white' }}">
                All
            </a>
        </div>

        <div class="text-xs text-stone-400">
            Showing <strong class="text-white">{{ $events->total() }}</strong> events
        </div>
    </div>

    @if($events->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($events as $event)
                <article class="bg-stone-900 rounded-2xl border border-stone-800 hover:border-amber-500/40 transition-all flex flex-col justify-between p-6">
                    <div>
                        <div class="flex items-center justify-between text-xs text-stone-400 mb-3">
                            <span class="px-2.5 py-0.5 rounded bg-stone-800 text-amber-300 font-bold uppercase tracking-wider text-[10px]">
                                {{ $event->type ?? 'Workshop' }}
                            </span>
                            <span class="text-stone-400 font-mono">
                                {{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('M d, Y') : 'Date TBA' }}
                            </span>
                        </div>

                        <h2 class="text-xl font-bold text-white hover:text-amber-300 transition-colors mb-2">
                            <a href="{{ route('events.show', $event->slug) }}">
                                {{ $event->name }}
                            </a>
                        </h2>

                        <p class="text-xs text-stone-400 line-clamp-3 leading-relaxed mb-6">
                            {{ $event->description }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-stone-800 flex items-center justify-between">
                        <div>
                            <span class="block text-[10px] text-stone-500 uppercase tracking-wider">Format</span>
                            <span class="text-xs font-semibold text-stone-200">
                                📍 {{ ucfirst($event->mode ?? 'Rishikesh') }}
                            </span>
                        </div>
                        <a href="{{ route('events.show', $event->slug) }}" class="bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs px-4 py-2 rounded-xl transition-colors">
                            Event Details &rarr;
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $events->links() }}
        </div>
    @else
        <div class="text-center py-16 px-4 bg-stone-900 rounded-2xl border border-stone-800">
            <span class="text-4xl block mb-3">🗓️</span>
            <h2 class="text-lg font-bold text-white mb-2">No events currently found in this category</h2>
            <p class="text-xs text-stone-400">Please check back soon or join our newsletter to stay notified of upcoming retreats.</p>
        </div>
    @endif
</div>
@endsection
