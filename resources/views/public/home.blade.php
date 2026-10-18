@extends('layouts.public')

@section('title', __('site.brand_name') . ' — ' . __('site.tagline'))
@section('meta_description', 'Authentic classical Yogic science, traditional Hatha & Ashtanga sadhana, Pranayama, and Vedanta teacher training in Rishikesh and globally online.')

@section('content')
{{-- Hero Section --}}
<section class="relative overflow-hidden py-20 lg:py-28 border-b border-stone-800/80 bg-gradient-to-b from-stone-900/60 via-stone-950 to-stone-950">
    {{-- Subtle Spiritual Background Glow --}}
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[450px] bg-amber-500/10 blur-[130px] rounded-full pointer-events-none -z-10"></div>
    <div class="absolute top-1/2 right-10 w-[400px] h-[300px] bg-orange-600/10 blur-[110px] rounded-full pointer-events-none -z-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        {{-- Tradition Badge --}}
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-semibold uppercase tracking-wider mb-8">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            {{ __('site.hero.badge') }}
        </div>

        {{-- Single H1 for SEO --}}
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif-title font-extrabold text-white tracking-tight leading-tight max-w-4xl mx-auto mb-6">
            {{ __('site.hero.title') }}
        </h1>

        <p class="text-lg sm:text-xl text-stone-300 max-w-2xl mx-auto leading-relaxed mb-10">
            {{ __('site.hero.subtitle') }}
        </p>

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
            <a href="{{ route('courses.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-bold text-base px-8 py-3.5 rounded-xl shadow-xl shadow-amber-500/25 transition-all hover:scale-[1.02]">
                <span>{{ __('site.hero.cta_explore') }}</span>
                <span class="text-xl">&rarr;</span>
            </a>
            <a href="{{ route('schedule') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-stone-900/90 hover:bg-stone-800 text-stone-200 border border-stone-700 font-semibold text-base px-7 py-3.5 rounded-xl transition-all">
                <span>{{ __('site.hero.cta_schedule') }}</span>
            </a>
        </div>

        {{-- Trust & Tradition Stats Bar --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl mx-auto pt-8 border-t border-stone-800/80">
            <div class="p-4 rounded-xl bg-stone-900/40 border border-white/5">
                <span class="text-3xl font-extrabold text-amber-400 block font-serif">5,000+</span>
                <span class="text-xs text-stone-400 uppercase tracking-wider mt-1 block">{{ __('site.hero.stat_students') }}</span>
            </div>
            <div class="p-4 rounded-xl bg-stone-900/40 border border-white/5">
                <span class="text-3xl font-extrabold text-amber-400 block font-serif">40+</span>
                <span class="text-xs text-stone-400 uppercase tracking-wider mt-1 block">{{ __('site.hero.stat_teachers') }}</span>
            </div>
            <div class="p-4 rounded-xl bg-stone-900/40 border border-white/5">
                <span class="text-3xl font-extrabold text-amber-400 block font-serif">25+</span>
                <span class="text-xs text-stone-400 uppercase tracking-wider mt-1 block">{{ __('site.hero.stat_courses') }}</span>
            </div>
            <div class="p-4 rounded-xl bg-stone-900/40 border border-white/5">
                <span class="text-3xl font-extrabold text-amber-400 block font-serif">30+</span>
                <span class="text-xs text-stone-400 uppercase tracking-wider mt-1 block">{{ __('site.hero.stat_tradition') }}</span>
            </div>
        </div>
    </div>
</section>

{{-- Featured Courses Section --}}
<section class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Curriculum & Academics</span>
            <h2 class="text-3xl font-serif-title font-bold text-white">Featured Yogic Programs</h2>
        </div>
        <a href="{{ route('courses.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-400 hover:text-amber-300 mt-4 md:mt-0 transition-colors">
            View All Courses &rarr;
        </a>
    </div>

    @if($featuredCourses->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($featuredCourses as $course)
                <article class="bg-stone-900/80 rounded-2xl border border-stone-800 hover:border-amber-500/40 transition-all duration-300 flex flex-col overflow-hidden group hover:shadow-xl hover:shadow-amber-500/5">
                    {{-- Course Image / Banner --}}
                    <div class="relative h-48 bg-gradient-to-br from-stone-800 to-stone-900 overflow-hidden">
                        @if($course->cover_image_path)
                            <img src="{{ $course->cover_image_path }}" alt="{{ $course->title }}" width="600" height="340" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-amber-500/40 font-serif">
                                ॐ
                            </div>
                        @endif
                        {{-- Mode & Category Badges --}}
                        <div class="absolute top-3 left-3 flex items-center gap-1.5">
                            <span class="px-2.5 py-1 rounded-lg bg-stone-950/80 backdrop-blur-md text-amber-300 text-[11px] font-bold border border-white/10 uppercase tracking-wider">
                                {{ ucfirst($course->default_delivery_mode->value ?? 'online') }}
                            </span>
                            @if($course->category)
                                <span class="px-2.5 py-1 rounded-lg bg-stone-900/90 backdrop-blur-md text-stone-200 text-[11px] font-medium border border-white/10">
                                    {{ $course->category->name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Course Body --}}
                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs text-amber-400 mb-2 font-medium">
                                <span>★ {{ number_format($course->rating_average > 0 ? $course->rating_average : 4.9, 1) }}</span>
                                <span class="text-stone-500">•</span>
                                <span class="text-stone-400">{{ $course->duration_hours }} Hours Duration</span>
                                <span class="text-stone-500">•</span>
                                <span class="text-stone-400 uppercase">{{ $course->difficulty_level->value ?? 'All' }}</span>
                            </div>

                            <h3 class="text-xl font-bold text-white group-hover:text-amber-300 transition-colors line-clamp-1 mb-2">
                                <a href="{{ route('courses.show', $course->slug) }}">
                                    {{ $course->title }}
                                </a>
                            </h3>

                            <p class="text-sm text-stone-400 line-clamp-2 leading-relaxed mb-4">
                                {{ $course->short_description ?? strip_tags((string) $course->description) }}
                            </p>
                        </div>

                        {{-- Footer: Fee & Action --}}
                        <div class="pt-4 border-t border-stone-800/80 flex items-center justify-between mt-4">
                            <div>
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider font-semibold">Program Fee</span>
                                <span class="text-lg font-bold text-white">
                                    @if($course->base_fee_amount == 0)
                                        <span class="text-emerald-400">Complimentary / Free</span>
                                    @else
                                        ₹{{ number_format($course->base_fee_amount / 100) }}
                                    @endif
                                </span>
                            </div>
                            <a href="{{ route('courses.show', $course->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-stone-950 bg-amber-400 hover:bg-amber-300 px-3.5 py-2 rounded-xl transition-colors">
                                <span>Details</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="p-12 text-center rounded-2xl bg-stone-900 border border-stone-800">
            <p class="text-stone-400 text-sm">Academic course catalog is currently being prepared for publication.</p>
        </div>
    @endif
</section>

{{-- Lineage & Philosophical Foundation Banner --}}
<section class="py-16 bg-stone-900/50 border-y border-stone-800/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">The Pramana Way</span>
                <h2 class="text-3xl sm:text-4xl font-serif-title font-bold text-white leading-tight mb-6">
                    Rooted in Classical Shastras, Refined for Modern Seekers
                </h2>
                <div class="space-y-4 text-stone-300 text-sm leading-relaxed">
                    <p>
                        "Pramana" in Vedic epistemology means the valid means of acquiring true knowledge (pratyaksha, anumana, and shabda-agama). We treat Yogic science not as casual exercise, but as a complete system of consciousness transformation.
                    </p>
                    <p>
                        Under the guidance of master Acharyas in Rishikesh, students receive rigorous daily sadhana, alignment biomechanics, subtle pranayama mastery, and profound study of the Patanjali Yoga Sutras and Upanishads.
                    </p>
                </div>
                <div class="mt-8 flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-xl font-bold font-serif">✓</span>
                        <span class="text-xs font-semibold text-stone-200">Guru-Shishya Parampara</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-xl font-bold font-serif">✓</span>
                        <span class="text-xs font-semibold text-stone-200">Global Yoga Alliance Credentials</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-6 rounded-2xl bg-stone-900 border border-stone-800">
                    <span class="text-3xl mb-3 block">🧘‍♂️</span>
                    <h3 class="text-base font-bold text-white mb-2">Classical Hatha & Ashtanga</h3>
                    <p class="text-xs text-stone-400 leading-relaxed">Precise postural alignment, bandha engagement, and breath synchronization.</p>
                </div>
                <div class="p-6 rounded-2xl bg-stone-900 border border-stone-800">
                    <span class="text-3xl mb-3 block">🌬️</span>
                    <h3 class="text-base font-bold text-white mb-2">Traditional Pranayama</h3>
                    <p class="text-xs text-stone-400 leading-relaxed">Pranic regulation, kumbhaka retention, and therapeutic nervous system balancing.</p>
                </div>
                <div class="p-6 rounded-2xl bg-stone-900 border border-stone-800">
                    <span class="text-3xl mb-3 block">📜</span>
                    <h3 class="text-base font-bold text-white mb-2">Vedanta & Shastras</h3>
                    <p class="text-xs text-stone-400 leading-relaxed">Systematic study of the Mandukya, Katha Upanishads, and the Bhagavad Gita.</p>
                </div>
                <div class="p-6 rounded-2xl bg-stone-900 border border-stone-800">
                    <span class="text-3xl mb-3 block">🌿</span>
                    <h3 class="text-base font-bold text-white mb-2">Ayurveda Lifestyle</h3>
                    <p class="text-xs text-stone-400 leading-relaxed">Dinacharya daily routines, dosha diagnosis, and sattvic nutritional science.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Upcoming Events & Retreats --}}
@if($upcomingEvents->isNotEmpty())
<section class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-12">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Intensives & Gatherings</span>
            <h2 class="text-3xl font-serif-title font-bold text-white">Upcoming Events & Retreats</h2>
        </div>
        <a href="{{ route('events.calendar') }}" class="text-sm font-semibold text-amber-400 hover:text-amber-300 transition-colors">
            Full Events Calendar &rarr;
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach($upcomingEvents as $event)
            <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6 flex flex-col justify-between hover:border-amber-500/40 transition-colors">
                <div>
                    <div class="flex items-center justify-between text-xs text-stone-400 mb-3">
                        <span class="font-bold text-amber-400 uppercase tracking-wider">{{ $event->type ?? 'Workshop' }}</span>
                        <span class="bg-stone-800 px-2 py-0.5 rounded text-stone-300 font-mono">{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('M d, Y') : 'Upcoming' }}</span>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">
                        <a href="{{ route('events.show', $event->slug) }}" class="hover:text-amber-300 transition-colors">
                            {{ $event->name }}
                        </a>
                    </h3>
                    <p class="text-xs text-stone-400 line-clamp-3 leading-relaxed mb-4">
                        {{ $event->description }}
                    </p>
                </div>
                <div class="pt-4 border-t border-stone-800 flex items-center justify-between">
                    <span class="text-xs text-stone-400">📍 {{ ucfirst($event->mode ?? 'Rishikesh') }}</span>
                    <a href="{{ route('events.show', $event->slug) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                        Register &rarr;
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- Student Testimonials Wall Preview --}}
@if($testimonials->isNotEmpty())
<section class="py-20 bg-stone-900/30 border-t border-stone-800/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-14">
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Student Voices</span>
            <h2 class="text-3xl font-serif-title font-bold text-white mb-4">Transformational Experiences</h2>
            <p class="text-sm text-stone-400">Hear directly from graduates, international seekers, and certified Yoga teachers.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($testimonials as $t)
                <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6 flex flex-col justify-between">
                    <div>
                        <div class="text-amber-400 text-sm mb-3 font-mono">
                            {{ str_repeat('★', $t->rating) }}
                        </div>
                        <p class="text-xs text-stone-300 italic leading-relaxed mb-6">
                            "{{ $t->quote }}"
                        </p>
                    </div>
                    <div class="pt-4 border-t border-stone-800">
                        <span class="block text-sm font-bold text-white">{{ $t->author_name }}</span>
                        @if($t->author_title)
                            <span class="block text-[11px] text-amber-400/90">{{ $t->author_title }}</span>
                        @endif
                        @if($t->author_location)
                            <span class="block text-[10px] text-stone-500">{{ $t->author_location }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="text-center mt-10">
            <a href="{{ route('testimonials') }}" class="text-xs font-semibold text-amber-400 hover:underline">
                Read all graduate reviews on our Testimonials Wall &rarr;
            </a>
        </div>
    </div>
</section>
@endif

{{-- Journal / Articles Section --}}
@if($latestArticles->isNotEmpty())
<section class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-12">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Knowledge Journal</span>
            <h2 class="text-3xl font-serif-title font-bold text-white">Articles & Philosophy Insights</h2>
        </div>
        <a href="{{ route('articles.index') }}" class="text-sm font-semibold text-amber-400 hover:text-amber-300 transition-colors">
            All Articles &rarr;
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach($latestArticles as $article)
            <article class="bg-stone-900 rounded-2xl border border-stone-800 p-6 flex flex-col justify-between hover:border-amber-500/40 transition-colors">
                <div>
                    <div class="flex items-center justify-between text-xs text-stone-400 mb-3">
                        <span class="px-2 py-0.5 rounded bg-stone-800 text-amber-300 font-semibold">{{ $article->category }}</span>
                        <span>{{ $article->reading_time_minutes }} min read</span>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">
                        <a href="{{ route('articles.show', $article->slug) }}" class="hover:text-amber-300 transition-colors">
                            {{ $article->title }}
                        </a>
                    </h3>
                    <p class="text-xs text-stone-400 line-clamp-3 leading-relaxed mb-4">
                        {{ $article->excerpt ?? strip_tags($article->content) }}
                    </p>
                </div>
                <div class="pt-4 border-t border-stone-800 flex items-center justify-between text-xs text-stone-400">
                    <span>By {{ $article->author?->full_name ?? 'Pramana Faculty' }}</span>
                    <a href="{{ route('articles.show', $article->slug) }}" class="font-bold text-amber-400">Read &rarr;</a>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

{{-- Final Admissions CTA Section --}}
<section class="py-20 bg-gradient-to-r from-amber-600 via-amber-500 to-orange-600 text-stone-950 text-center">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl sm:text-4xl font-serif-title font-extrabold mb-4">
            Begin Your Sacred Yogic Journey Today
        </h2>
        <p class="text-base sm:text-lg font-medium text-stone-900 max-w-2xl mx-auto mb-8">
            Admissions for our Yoga Alliance 200h & 300h residential and online tracks are currently open. Speak directly with an admissions advisor or review program requirements.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('courses.index') }}" class="w-full sm:w-auto bg-stone-950 text-amber-400 hover:bg-stone-900 font-bold px-8 py-3.5 rounded-xl shadow-2xl transition-all">
                Browse Courses
            </a>
            <a href="{{ route('contact') }}" class="w-full sm:w-auto bg-white/20 hover:bg-white/30 text-stone-950 font-bold px-8 py-3.5 rounded-xl border border-stone-950/20 transition-all">
                Speak with Admissions
            </a>
        </div>
    </div>
</section>
@endsection
