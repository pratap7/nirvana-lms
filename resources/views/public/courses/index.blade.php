@extends('layouts.public')

@section('title', __('site.catalog.title') . ' — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Browse accredited Yogic science programs, teacher trainings (RYT-200, RYT-300), Pranayama immersions, and Vedanta study courses at Pramana Institute.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-3">
            {{ __('site.catalog.title') }}
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            {{ __('site.catalog.subtitle') }}
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Search & Filters Form --}}
    <form method="GET" action="{{ route('courses.index') }}" class="bg-stone-900 rounded-2xl border border-stone-800 p-6 mb-10 shadow-lg">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
            {{-- Search Bar --}}
            <div class="lg:col-span-2">
                <label for="q" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Search Courses</label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    value="{{ $search }}"
                    placeholder="{{ __('site.catalog.search_placeholder') }}"
                    class="w-full bg-stone-950 border border-stone-700/80 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
            </div>

            {{-- Category Filter --}}
            <div>
                <label for="category" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Tradition / Category</label>
                <select id="category" name="category" class="w-full bg-stone-950 border border-stone-700/80 rounded-xl px-3 py-2.5 text-sm text-stone-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="all">{{ __('site.catalog.all_categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->slug }}" {{ $categorySlug === $cat->slug ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Delivery Mode Filter --}}
            <div>
                <label for="mode" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Mode</label>
                <select id="mode" name="mode" class="w-full bg-stone-950 border border-stone-700/80 rounded-xl px-3 py-2.5 text-sm text-stone-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="all">{{ __('site.catalog.all_modes') }}</option>
                    <option value="online" {{ $mode === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ $mode === 'offline' ? 'selected' : '' }}>Ashram / Offline</option>
                    <option value="hybrid" {{ $mode === 'hybrid' ? 'selected' : '' }}>Hybrid</option>
                </select>
            </div>

            {{-- Type Filter --}}
            <div>
                <label for="type" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Course Type</label>
                <select id="type" name="type" class="w-full bg-stone-950 border border-stone-700/80 rounded-xl px-3 py-2.5 text-sm text-stone-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="all">{{ __('site.catalog.all_types') }}</option>
                    <option value="long_term" {{ $type === 'long_term' ? 'selected' : '' }}>Teacher Training (Long)</option>
                    <option value="short_term" {{ $type === 'short_term' ? 'selected' : '' }}>Intensive (Short)</option>
                </select>
            </div>

            {{-- Price Filter --}}
            <div>
                <label for="price" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Fee</label>
                <select id="price" name="price" class="w-full bg-stone-950 border border-stone-700/80 rounded-xl px-3 py-2.5 text-sm text-stone-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="all">All Fees</option>
                    <option value="free" {{ $priceFilter === 'free' ? 'selected' : '' }}>Free / Complimentary</option>
                    <option value="paid" {{ $priceFilter === 'paid' ? 'selected' : '' }}>Paid Programs</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between mt-5 pt-4 border-t border-stone-800">
            <span class="text-xs text-stone-400">
                Found <strong class="text-white">{{ $courses->total() }}</strong> courses
            </span>
            <div class="flex items-center gap-3">
                <a href="{{ route('courses.index') }}" class="text-xs text-stone-400 hover:text-white transition-colors">
                    {{ __('site.catalog.reset_button') }}
                </a>
                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs px-5 py-2 rounded-xl transition-colors">
                    {{ __('site.catalog.filter_button') }}
                </button>
            </div>
        </div>
    </form>

    {{-- Course Cards Grid --}}
    @if($courses->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($courses as $course)
                <article class="bg-stone-900 rounded-2xl border border-stone-800 hover:border-amber-500/40 transition-all duration-300 flex flex-col overflow-hidden group hover:shadow-xl hover:shadow-amber-500/5">
                    {{-- Cover Banner --}}
                    <div class="relative h-48 bg-gradient-to-br from-stone-800 to-stone-950 overflow-hidden">
                        @if($course->cover_image_path)
                            <img src="{{ $course->cover_image_path }}" alt="{{ $course->title }}" width="600" height="340" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-amber-500/30 font-serif">
                                ॐ
                            </div>
                        @endif

                        <div class="absolute top-3 left-3 flex items-center gap-1.5 flex-wrap">
                            <span class="px-2.5 py-1 rounded-lg bg-stone-950/80 backdrop-blur-md text-amber-300 text-[11px] font-bold border border-white/10 uppercase tracking-wider">
                                {{ ucfirst($course->default_delivery_mode->value ?? 'online') }}
                            </span>
                            @if($course->category)
                                <span class="px-2.5 py-1 rounded-lg bg-stone-900/90 backdrop-blur-md text-stone-200 text-[11px] font-medium border border-white/10">
                                    {{ $course->category->name }}
                                </span>
                            @endif
                        </div>

                        {{-- Upcoming Batch badge if any --}}
                        @if($course->batches->isNotEmpty())
                            <div class="absolute bottom-3 left-3">
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-950/90 text-emerald-300 text-[10px] font-bold border border-emerald-500/40 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Next Batch: {{ $course->batches->first()->starts_at ? \Carbon\Carbon::parse($course->batches->first()->starts_at)->format('M d') : 'Open' }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs text-amber-400 mb-2 font-medium">
                                <span>★ {{ number_format($course->rating_average > 0 ? $course->rating_average : 4.9, 1) }}</span>
                                <span class="text-stone-600">•</span>
                                <span class="text-stone-400">{{ $course->duration_hours }} Hours</span>
                                <span class="text-stone-600">•</span>
                                <span class="text-stone-400 uppercase">{{ $course->difficulty_level->value ?? 'All' }}</span>
                            </div>

                            <h2 class="text-xl font-bold text-white group-hover:text-amber-300 transition-colors line-clamp-1 mb-2">
                                <a href="{{ route('courses.show', $course->slug) }}">
                                    {{ $course->title }}
                                </a>
                            </h2>

                            <p class="text-xs text-stone-400 line-clamp-2 leading-relaxed mb-4">
                                {{ $course->short_description ?? strip_tags((string) $course->description) }}
                            </p>

                            {{-- Teachers preview --}}
                            @if($course->teachers->isNotEmpty())
                                <div class="text-[11px] text-stone-400 mb-3 flex items-center gap-1.5">
                                    <span class="text-stone-500">Lead Faculty:</span>
                                    <span class="text-stone-200 font-semibold">{{ $course->teachers->pluck('full_name')->join(', ') }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Card Footer --}}
                        <div class="pt-4 border-t border-stone-800 flex items-center justify-between mt-4">
                            <div>
                                <span class="block text-[10px] text-stone-500 uppercase tracking-wider font-semibold">Tuition</span>
                                <span class="text-lg font-bold text-white">
                                    @if($course->base_fee_amount == 0)
                                        <span class="text-emerald-400">Free</span>
                                    @else
                                        ₹{{ number_format($course->base_fee_amount / 100) }}
                                    @endif
                                </span>
                            </div>
                            <a href="{{ route('courses.show', $course->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-stone-950 bg-amber-400 hover:bg-amber-300 px-4 py-2 rounded-xl transition-colors">
                                <span>View Course</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-8">
            {{ $courses->links() }}
        </div>
    @else
        <div class="text-center py-16 px-4 bg-stone-900 rounded-2xl border border-stone-800">
            <span class="text-4xl block mb-3">🔍</span>
            <h2 class="text-lg font-bold text-white mb-2">{{ __('site.catalog.no_courses_found') }}</h2>
            <a href="{{ route('courses.index') }}" class="inline-block mt-3 text-xs font-bold text-amber-400 hover:underline">
                Clear all filters and search terms
            </a>
        </div>
    @endif
</div>
@endsection
