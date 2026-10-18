@extends('layouts.public')

@section('title', 'Frequently Asked Questions (FAQ) — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Find answers regarding Yoga Alliance certifications, admissions criteria, Tapovan ashram accommodations, and course fees at Pramana Institute.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-2">
            Frequently Asked Questions
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            Clear guidance regarding course accreditations, ashram living, enrollment procedures, and fee policies.
        </p>
    </div>
</section>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Search & Category Filter --}}
    <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6 mb-10 shadow-lg">
        <form method="GET" action="{{ route('faq') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search questions or keywords (e.g. Yoga Alliance, refund, visa)..."
                    class="w-full bg-stone-950 border border-stone-700 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
            </div>
            <div>
                <select name="category" onchange="this.form.submit()" class="w-full bg-stone-950 border border-stone-700 rounded-xl px-3 py-2.5 text-sm text-stone-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="all">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $selectedCategory === $cat ? 'selected' : '' }}>
                            {{ $cat }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- FAQs Accordion List --}}
    @if($faqs->isNotEmpty())
        <div class="space-y-4">
            @foreach($faqs as $index => $faq)
                <details class="group bg-stone-900 rounded-2xl border border-stone-800 overflow-hidden" {{ $index === 0 ? 'open' : '' }}>
                    <summary class="flex items-center justify-between p-6 cursor-pointer select-none text-white font-bold text-base group-hover:text-amber-300 transition-colors">
                        <span class="flex items-center gap-3">
                            <span class="text-amber-400 font-mono text-sm">Q.</span>
                            <span>{{ $faq->question }}</span>
                        </span>
                        <span class="text-amber-400 font-mono text-lg transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <div class="px-6 pb-6 text-sm text-stone-300 leading-relaxed border-t border-stone-800/80 pt-4">
                        {!! nl2br(e($faq->answer)) !!}
                    </div>
                </details>
            @endforeach
        </div>
    @else
        <div class="text-center py-16 px-4 bg-stone-900 rounded-2xl border border-stone-800">
            <h2 class="text-lg font-bold text-white mb-2">No matching questions found</h2>
            <p class="text-xs text-stone-400 mb-4">Have an unaddressed question? Our team is available directly.</p>
            <a href="{{ route('contact') }}" class="inline-block bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs px-5 py-2.5 rounded-xl transition-colors">
                Contact Admissions &rarr;
            </a>
        </div>
    @endif
</div>
@endsection
