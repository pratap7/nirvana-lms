@extends('layouts.public')

@section('title', $user->full_name . ' — Faculty Profile — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Faculty bio and courses taught by ' . $user->full_name . ' (' . ($user->headline ?? 'Acharya') . ') at Pramana Institute of Yogic Studies.')

@section('content')
<section class="py-14 bg-stone-900/60 border-b border-stone-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-xs text-stone-400 mb-6" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-amber-400">Home</a>
            <span>/</span>
            <a href="{{ route('faculty.index') }}" class="hover:text-amber-400">Faculty</a>
            <span>/</span>
            <span class="text-stone-200">{{ $user->full_name }}</span>
        </nav>

        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
            <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-amber-700 text-stone-950 font-serif font-bold text-4xl flex items-center justify-center shrink-0 shadow-2xl shadow-amber-500/20 border-2 border-amber-300/30">
                {{ substr($user->first_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-1">
                    {{ $user->full_name }}
                </h1>
                <span class="text-sm sm:text-base text-amber-400 font-semibold block mb-2">
                    {{ $user->headline ?? 'Acharya & Yoga Master' }}
                </span>
                @if($user->lineage)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-stone-800 text-xs text-stone-300 border border-stone-700">
                        Lineage: {{ $user->lineage }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</section>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
    {{-- Bio Section --}}
    <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
        <h2 class="text-xl font-serif-title font-bold text-white mb-4">Biographical Journey & Lineage</h2>
        <div class="prose prose-invert max-w-none text-stone-300 text-sm leading-relaxed space-y-4">
            {!! nl2br(e($user->bio ?? 'Dedicated teacher and practitioner guiding seekers in traditional Yogic sciences and Vedanta at Pramana Institute.')) !!}
        </div>
    </section>

    {{-- Courses Taught by this Faculty Member --}}
    @if($user->taughtCourses->isNotEmpty())
        <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
            <h2 class="text-xl font-serif-title font-bold text-white mb-6">Programs Taught by {{ $user->first_name }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($user->taughtCourses as $course)
                    <div class="p-5 rounded-xl bg-stone-950 border border-stone-800 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs text-amber-400 mb-2">
                                <span class="uppercase font-semibold">{{ $course->default_delivery_mode->value ?? 'Online' }}</span>
                                <span>•</span>
                                <span>{{ $course->duration_hours }} Hours</span>
                            </div>
                            <h3 class="text-base font-bold text-white mb-2">
                                <a href="{{ route('courses.show', $course->slug) }}" class="hover:text-amber-300">
                                    {{ $course->title }}
                                </a>
                            </h3>
                            <p class="text-xs text-stone-400 line-clamp-2 leading-relaxed mb-4">
                                {{ $course->short_description ?? strip_tags((string) $course->description) }}
                            </p>
                        </div>
                        <a href="{{ route('courses.show', $course->slug) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                            View Syllabus &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
