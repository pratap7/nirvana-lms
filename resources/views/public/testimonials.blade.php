@extends('layouts.public')

@section('title', 'Student Reviews & Testimonials — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Read authentic student reviews and teacher training testimonials from graduates of Pramana Institute of Yogic Studies in Rishikesh.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-2">
            Graduate Testimonials & Wall of Love
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            Real stories of transformation, authentic sadhana, and teaching careers from our worldwide community of seekers.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    @if($testimonials->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($testimonials as $t)
                <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6 flex flex-col justify-between hover:border-amber-500/30 transition-colors">
                    <div>
                        <div class="flex items-center justify-between text-xs text-amber-400 mb-4 font-mono">
                            <span>{{ str_repeat('★', $t->rating) }}</span>
                            @if($t->course)
                                <span class="text-stone-500 text-[10px] truncate max-w-[150px]">{{ $t->course->title }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-stone-300 italic leading-relaxed mb-6">
                            "{{ $t->quote }}"
                        </p>
                    </div>

                    <div class="pt-4 border-t border-stone-800 flex items-center justify-between">
                        <div>
                            <span class="block text-sm font-bold text-white">{{ $t->author_name }}</span>
                            @if($t->author_title)
                                <span class="block text-xs text-amber-400/90 font-medium">{{ $t->author_title }}</span>
                            @endif
                        </div>
                        @if($t->author_location)
                            <span class="text-[11px] text-stone-500 font-mono">{{ $t->author_location }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div>
            {{ $testimonials->links() }}
        </div>
    @else
        <div class="text-center py-16 px-4 bg-stone-900 rounded-2xl border border-stone-800">
            <h2 class="text-lg font-bold text-white mb-2">Testimonials will appear here shortly</h2>
            <p class="text-xs text-stone-400">Our graduates are submitting their program reviews.</p>
        </div>
    @endif
</div>
@endsection
