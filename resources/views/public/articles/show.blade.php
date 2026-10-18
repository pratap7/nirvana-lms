@extends('layouts.public')

@section('title', ($article->seo_title ?? $article->title) . ' — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', $article->seo_description ?? $article->excerpt ?? 'Read ' . $article->title . ' on Pramana Institute Journal.')

@section('content')
<article class="py-14 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-xs text-stone-400 mb-6" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-amber-400">Home</a>
            <span>/</span>
            <a href="{{ route('articles.index') }}" class="hover:text-amber-400">Journal</a>
            <span>/</span>
            <span class="text-stone-300 truncate">{{ $article->title }}</span>
        </nav>

        {{-- Meta Badges --}}
        <div class="flex items-center gap-3 text-xs text-stone-400 mb-4">
            <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-300 border border-amber-500/30 font-bold uppercase text-[11px]">
                {{ $article->category }}
            </span>
            <span>•</span>
            <span class="font-mono">{{ $article->reading_time_minutes }} min read</span>
            <span>•</span>
            <span>Published {{ $article->published_at ? $article->published_at->format('M d, Y') : $article->created_at->format('M d, Y') }}</span>
        </div>

        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-serif-title font-extrabold text-white leading-tight mb-6">
            {{ $article->title }}
        </h1>

        @if($article->author)
            <div class="flex items-center gap-3 pt-4 border-t border-stone-800">
                <div class="w-10 h-10 rounded-full bg-amber-500/20 text-amber-300 font-bold flex items-center justify-center">
                    {{ substr($article->author->first_name, 0, 1) }}
                </div>
                <div>
                    <span class="block text-sm font-bold text-white leading-none">{{ $article->author->full_name }}</span>
                    <span class="text-xs text-stone-400">{{ $article->author->headline ?? 'Pramana Institute Faculty' }}</span>
                </div>
            </div>
        @endif
    </div>
</article>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    {{-- Article Body --}}
    <div class="prose prose-invert max-w-none text-stone-300 text-base leading-relaxed space-y-6">
        {!! nl2br(e($article->content)) !!}
    </div>

    {{-- Author Bio Card --}}
    @if($article->author)
        <div class="mt-14 p-6 rounded-2xl bg-stone-900 border border-stone-800 flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-stone-950 font-serif font-bold text-2xl flex items-center justify-center shrink-0">
                {{ substr($article->author->first_name, 0, 1) }}
            </div>
            <div>
                <h3 class="text-base font-bold text-white">{{ $article->author->full_name }}</h3>
                <span class="text-xs text-amber-400 block mb-2">{{ $article->author->headline ?? 'Acharya' }}</span>
                <p class="text-xs text-stone-400 leading-relaxed">
                    {{ $article->author->bio ?? 'Traditional scholar and yogic practitioner at Pramana Institute.' }}
                </p>
            </div>
        </div>
    @endif

    {{-- Related Programs --}}
    @if($relatedCourses->isNotEmpty())
        <div class="mt-16 pt-10 border-t border-stone-800">
            <h3 class="text-xl font-serif-title font-bold text-white mb-6">Deepen Your Study with Accredited Courses</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($relatedCourses as $c)
                    <div class="p-5 rounded-2xl bg-stone-900 border border-stone-800 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-amber-400 block mb-1">{{ $c->default_delivery_mode->value ?? 'Online' }}</span>
                            <h4 class="text-sm font-bold text-white hover:text-amber-300 transition-colors mb-2">
                                <a href="{{ route('courses.show', $c->slug) }}">{{ $c->title }}</a>
                            </h4>
                            <p class="text-xs text-stone-400 line-clamp-2 leading-relaxed">
                                {{ $c->short_description ?? strip_tags((string) $c->description) }}
                            </p>
                        </div>
                        <a href="{{ route('courses.show', $c->slug) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300 mt-4 block">
                            View Syllabus &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
