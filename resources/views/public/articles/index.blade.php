@extends('layouts.public')

@section('title', 'Pramana Journal — Yogic Philosophy & Research — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Read research essays, classical scriptural commentaries, asana alignment analyses, and lifestyle articles by the Acharyas of Pramana Institute.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-2">
            Pramana Journal & Research
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            Rigorous philosophical commentaries, physiological research on pranayama, and experiential insights from traditional masters.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Search and Categories Filter --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10 pb-6 border-b border-stone-800">
        {{-- Category Pills --}}
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('articles.index') }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold {{ empty($category) || $category === 'all' ? 'bg-amber-500 text-stone-950 font-bold' : 'bg-stone-900 text-stone-300 hover:bg-stone-800' }}">
                All Topics
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('articles.index', ['category' => $cat]) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold {{ $category === $cat ? 'bg-amber-500 text-stone-950 font-bold' : 'bg-stone-900 text-stone-300 hover:bg-stone-800' }}">
                    {{ $cat }}
                </a>
            @endforeach
        </div>

        {{-- Search Input --}}
        <form method="GET" action="{{ route('articles.index') }}" class="w-full md:w-72">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Search articles..."
                class="w-full bg-stone-900 border border-stone-800 rounded-xl px-3.5 py-2 text-xs text-stone-100 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
            >
        </form>
    </div>

    @if($articles->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($articles as $article)
                <article class="bg-stone-900 rounded-2xl border border-stone-800 hover:border-amber-500/40 transition-all flex flex-col justify-between p-6">
                    <div>
                        <div class="flex items-center justify-between text-xs text-stone-400 mb-3">
                            <span class="px-2.5 py-0.5 rounded bg-stone-800 text-amber-300 font-bold text-[10px] uppercase">
                                {{ $article->category }}
                            </span>
                            <span class="text-stone-500 font-mono text-[11px]">{{ $article->reading_time_minutes }} min read</span>
                        </div>

                        <h2 class="text-xl font-bold text-white hover:text-amber-300 transition-colors mb-2">
                            <a href="{{ route('articles.show', $article->slug) }}">
                                {{ $article->title }}
                            </a>
                        </h2>

                        <p class="text-xs text-stone-400 line-clamp-3 leading-relaxed mb-6">
                            {{ $article->excerpt ?? strip_tags($article->content) }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-stone-800 flex items-center justify-between text-xs text-stone-400">
                        <span>By {{ $article->author?->full_name ?? 'Pramana Faculty' }}</span>
                        <a href="{{ route('articles.show', $article->slug) }}" class="font-bold text-amber-400 hover:text-amber-300">
                            Read Essay &rarr;
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div>
            {{ $articles->links() }}
        </div>
    @else
        <div class="text-center py-16 px-4 bg-stone-900 rounded-2xl border border-stone-800">
            <h2 class="text-lg font-bold text-white mb-2">No articles match your query</h2>
            <p class="text-xs text-stone-400">Try browsing all categories or clear search terms.</p>
        </div>
    @endif
</div>
@endsection
