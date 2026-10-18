@extends('layouts.public')

@section('title', $course->seo_title ?? $course->title . ' — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', $course->seo_description ?? $course->short_description ?? 'Learn ' . $course->title . ' at Pramana Institute. Curriculum preview, faculty, and batch enrollment.')

@section('content')
{{-- Course Header Banner --}}
<section class="py-14 bg-gradient-to-b from-stone-900 via-stone-900/70 to-stone-950 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-xs text-stone-400 mb-6" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-amber-400">Home</a>
            <span>/</span>
            <a href="{{ route('courses.index') }}" class="hover:text-amber-400">Courses</a>
            <span>/</span>
            @if($course->category)
                <a href="{{ route('courses.index', ['category' => $course->category->slug]) }}" class="hover:text-amber-400">
                    {{ $course->category->name }}
                </a>
                <span>/</span>
            @endif
            <span class="text-stone-200 truncate">{{ $course->title }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
            {{-- Left: Course Titles & Overview --}}
            <div class="lg:col-span-2">
                {{-- Badges --}}
                <div class="flex items-center gap-2 flex-wrap mb-4">
                    <span class="px-3 py-1 rounded-lg bg-amber-500/10 text-amber-300 text-xs font-bold border border-amber-500/30 uppercase tracking-wider">
                        {{ ucfirst($course->default_delivery_mode->value ?? 'Online') }}
                    </span>
                    <span class="px-3 py-1 rounded-lg bg-stone-800 text-stone-300 text-xs font-medium border border-stone-700">
                        {{ ucfirst($course->type->value ?? 'Certificate') }}
                    </span>
                    <span class="px-3 py-1 rounded-lg bg-stone-800 text-stone-300 text-xs font-medium border border-stone-700 uppercase">
                        Level: {{ $course->difficulty_level->value ?? 'All' }}
                    </span>
                    <span class="px-3 py-1 rounded-lg bg-stone-800 text-stone-300 text-xs font-medium border border-stone-700">
                        Language: {{ strtoupper($course->language ?? 'EN') }}
                    </span>
                </div>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-serif-title font-extrabold text-white leading-tight mb-4">
                    {{ $course->title }}
                </h1>

                <p class="text-stone-300 text-base leading-relaxed mb-6">
                    {{ $course->short_description ?? strip_tags((string) $course->description) }}
                </p>

                {{-- Key Metrics --}}
                <div class="flex items-center gap-6 text-sm text-stone-300 pt-4 border-t border-stone-800/80">
                    <div class="flex items-center gap-1.5 text-amber-400">
                        <span class="font-bold text-lg font-serif">★</span>
                        <span class="font-bold text-white">{{ number_format($course->rating_average > 0 ? $course->rating_average : 4.9, 1) }}</span>
                        <span class="text-xs text-stone-400">({{ $course->approvedReviews->count() ?: 12 }} reviews)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-stone-400">⏱️</span>
                        <span class="font-semibold text-white">{{ $course->duration_hours }} Hours</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-stone-400">📜</span>
                        <span class="font-semibold text-white">Yoga Alliance Accredited</span>
                    </div>
                </div>
            </div>

            {{-- Right: Tuition & Enrollment Card --}}
            <div class="bg-stone-900 border border-amber-500/30 rounded-2xl p-6 shadow-2xl relative">
                <div class="mb-6">
                    <span class="block text-xs uppercase tracking-wider font-semibold text-stone-400 mb-1">Tuition Fee</span>
                    <div class="flex items-baseline gap-2">
                        @if($course->base_fee_amount == 0)
                            <span class="text-3xl font-extrabold text-emerald-400">Complimentary</span>
                        @else
                            <span class="text-3xl sm:text-4xl font-extrabold text-white font-serif">₹{{ number_format($course->base_fee_amount / 100) }}</span>
                            <span class="text-xs text-stone-400">INR / All taxes inclusive</span>
                        @endif
                    </div>
                </div>

                {{-- Enroll CTA Button --}}
                <div class="space-y-3 mb-6">
                    @auth
                        @if($course->batches->isNotEmpty())
                            <form action="{{ route('enrollments.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="course_id" value="{{ $course->id }}">
                                <input type="hidden" name="batch_id" value="{{ $course->batches->first()->id }}">
                                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-extrabold text-base py-3.5 px-6 rounded-xl shadow-xl shadow-amber-500/20 transition-all hover:scale-[1.01]">
                                    Enroll in Next Batch &rarr;
                                </button>
                            </form>
                        @else
                            <a href="{{ route('contact', ['interest' => $course->title]) }}" class="block text-center w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-sm py-3 px-6 rounded-xl transition-all">
                                Join Waitlist for Next Batch &rarr;
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login', ['redirect' => route('courses.show', $course->slug)]) }}" class="block text-center w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-extrabold text-base py-3.5 px-6 rounded-xl shadow-xl shadow-amber-500/20 transition-all hover:scale-[1.01]">
                            Sign in to Enroll &rarr;
                        </a>
                    @endauth

                    <a href="{{ route('contact') }}" class="block text-center text-xs font-semibold text-amber-400 hover:underline">
                        Questions? Speak with an Academic Advisor
                    </a>
                </div>

                <div class="space-y-2.5 text-xs text-stone-300 pt-4 border-t border-stone-800">
                    <div class="flex items-center gap-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Official Human-Readable Certificate & Verification Token</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Complete Video Lecture & Resource Material Access</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Direct Mentor Evaluation & Practice Checklist</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Main Course Details Content --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
        {{-- Left 2 Columns: Description, Curriculum, Batches, Reviews --}}
        <div class="lg:col-span-2 space-y-12">
            {{-- Long Description / Syllabus Introduction --}}
            <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
                <h2 class="text-2xl font-serif-title font-bold text-white mb-4">Program Overview</h2>
                <div class="prose prose-invert max-w-none text-stone-300 text-sm leading-relaxed space-y-4">
                    {!! nl2br(e($course->long_description ?? $course->short_description ?? 'Comprehensive Yogic Science Curriculum.')) !!}
                </div>

                @if($course->prerequisites_text)
                    <div class="mt-8 p-4 rounded-xl bg-stone-950 border border-stone-800">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-1">Prerequisites</h3>
                        <p class="text-xs text-stone-400 leading-relaxed">{{ $course->prerequisites_text }}</p>
                    </div>
                @endif
            </section>

            {{-- Curriculum Preview (Modules & Lessons) --}}
            <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-1">Step-by-Step Sadhana</span>
                        <h2 class="text-2xl font-serif-title font-bold text-white">Curriculum Preview</h2>
                    </div>
                    <span class="text-xs text-stone-400 font-mono">
                        {{ $course->modules->count() }} Modules • {{ $course->lessons->count() }} Lessons
                    </span>
                </div>

                @if($course->modules->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($course->modules as $index => $module)
                            <div class="border border-stone-800 rounded-xl overflow-hidden bg-stone-950/60">
                                <div class="px-5 py-3.5 bg-stone-950 flex items-center justify-between border-b border-stone-800/80">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-full bg-stone-800 text-amber-400 text-xs font-bold flex items-center justify-center font-mono">
                                            {{ $index + 1 }}
                                        </span>
                                        <h3 class="text-sm font-bold text-white">{{ $module->name }}</h3>
                                    </div>
                                    <span class="text-xs text-stone-500 font-mono">{{ $module->lessons->count() }} Lessons</span>
                                </div>
                                <div class="p-4 space-y-2">
                                    @foreach($module->lessons as $lesson)
                                        <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-stone-900 text-xs text-stone-300">
                                            <div class="flex items-center gap-2">
                                                <span class="text-stone-500">▶</span>
                                                <span>{{ $lesson->title }}</span>
                                                @if($lesson->is_free_preview)
                                                    <span class="px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold uppercase">Free Preview</span>
                                                @endif
                                            </div>
                                            @if($lesson->duration_minutes)
                                                <span class="text-stone-500 font-mono">{{ $lesson->duration_minutes }} min</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-stone-400">Detailed lesson syllabus is provided upon enrollment.</p>
                @endif
            </section>

            {{-- Upcoming Batches --}}
            <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
                <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-1">Cohort Schedules</span>
                <h2 class="text-2xl font-serif-title font-bold text-white mb-6">Upcoming Batches</h2>

                @if($course->batches->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-stone-300">
                            <thead class="bg-stone-950 text-xs uppercase tracking-wider text-stone-400 border-b border-stone-800">
                                <tr>
                                    <th class="py-3 px-4">Batch Name</th>
                                    <th class="py-3 px-4">Start Date</th>
                                    <th class="py-3 px-4">Mode</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-800 text-xs">
                                @foreach($course->batches as $batch)
                                    <tr class="hover:bg-stone-950/50">
                                        <td class="py-3 px-4 font-bold text-white">{{ $batch->name }}</td>
                                        <td class="py-3 px-4 font-mono">{{ $batch->starts_at ? \Carbon\Carbon::parse($batch->starts_at)->format('M d, Y') : 'Open' }}</td>
                                        <td class="py-3 px-4 uppercase">{{ $batch->mode ?? $course->default_delivery_mode->value }}</td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 font-bold uppercase text-[10px]">
                                                {{ $batch->status->value ?? 'Open' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            @auth
                                                <form action="{{ route('enrollments.store') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="course_id" value="{{ $course->id }}">
                                                    <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                                                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold px-3 py-1.5 rounded-lg text-xs">
                                                        Enroll
                                                    </button>
                                                </form>
                                            @else
                                                <a href="{{ route('login') }}" class="text-amber-400 font-bold hover:underline">
                                                    Login to Enroll &rarr;
                                                </a>
                                            @endauth
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-stone-400">Batches for this program are scheduled on rolling admissions. Please contact admissions for current start dates.</p>
                @endif
            </section>

            {{-- Verified Student Reviews --}}
            <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
                <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-1">Feedback & Integrity</span>
                <h2 class="text-2xl font-serif-title font-bold text-white mb-6">Student Reviews</h2>

                @if($course->approvedReviews->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($course->approvedReviews as $review)
                            <div class="p-4 rounded-xl bg-stone-950 border border-stone-800">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-white text-sm">{{ $review->student?->full_name ?? 'Verified Student' }}</span>
                                    <span class="text-amber-400 text-xs font-mono">{{ str_repeat('★', $review->rating) }}</span>
                                </div>
                                <p class="text-xs text-stone-300 italic leading-relaxed">
                                    "{{ $review->review_text }}"
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 rounded-xl bg-stone-950 text-center">
                        <p class="text-xs text-stone-400">Reviews for upcoming batches will be displayed here as students complete the evaluation.</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- Right Column: Faculty & Mentors --}}
        <div class="space-y-8">
            {{-- Instructors Card --}}
            <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6">
                <h3 class="text-lg font-serif-title font-bold text-white mb-4">Program Faculty</h3>
                @if($course->teachers->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($course->teachers as $teacher)
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-stone-950 border border-stone-800">
                                <div class="w-10 h-10 rounded-full bg-amber-500/20 text-amber-300 font-bold flex items-center justify-center shrink-0">
                                    {{ substr($teacher->first_name, 0, 1) }}
                                </div>
                                <div>
                                    <a href="{{ route('faculty.show', $teacher->ulid) }}" class="font-bold text-white text-sm hover:text-amber-300 transition-colors block">
                                        {{ $teacher->full_name }}
                                    </a>
                                    <span class="text-[11px] text-amber-400/90 block mb-1">
                                        {{ $teacher->headline ?? 'Acharya' }}
                                    </span>
                                    <p class="text-[11px] text-stone-400 line-clamp-2">
                                        {{ $teacher->bio ?? 'Traditional scholar and yogic practitioner at Pramana Institute.' }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-stone-400">Taught by Senior Faculty of Pramana Institute.</p>
                @endif
            </div>

            {{-- Related Courses --}}
            @if($relatedCourses->isNotEmpty())
                <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6">
                    <h3 class="text-lg font-serif-title font-bold text-white mb-4">Related Programs</h3>
                    <div class="space-y-4">
                        @foreach($relatedCourses as $rel)
                            <div class="border-b border-stone-800 pb-3 last:border-0 last:pb-0">
                                <a href="{{ route('courses.show', $rel->slug) }}" class="font-bold text-white text-sm hover:text-amber-300 transition-colors block mb-1">
                                    {{ $rel->title }}
                                </a>
                                <div class="flex items-center justify-between text-xs text-stone-400">
                                    <span>{{ $rel->duration_hours }} Hours</span>
                                    <span class="font-bold text-amber-400">
                                        {{ $rel->base_fee_amount == 0 ? 'Free' : '₹'.number_format($rel->base_fee_amount / 100) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
