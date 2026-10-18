@extends('layouts.public')

@section('title', 'Faculty & Acharyas — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Meet the traditional scholars, master yogis, and Acharyas leading programs at Pramana Institute of Yogic Studies.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-3">
            Our Acharyas & Faculty
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            Our faculty combines decades of traditional ashram sadhana in the Himalayas with university-level research in anatomy, physiology, and philosophy.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    @if($faculty->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($faculty as $member)
                <article class="bg-stone-900 rounded-2xl border border-stone-800 hover:border-amber-500/40 transition-all p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-stone-950 font-serif font-bold text-xl flex items-center justify-center shrink-0 shadow-lg shadow-amber-500/20">
                                {{ substr($member->first_name, 0, 1) }}
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-white hover:text-amber-300 transition-colors">
                                    <a href="{{ route('faculty.show', $member->ulid) }}">
                                        {{ $member->full_name }}
                                    </a>
                                </h2>
                                <span class="text-xs text-amber-400 font-medium block">
                                    {{ $member->headline ?? 'Acharya' }}
                                </span>
                                @if($member->lineage)
                                    <span class="text-[10px] text-stone-400 block mt-0.5">Lineage: {{ $member->lineage }}</span>
                                @endif
                            </div>
                        </div>

                        <p class="text-xs text-stone-400 line-clamp-3 leading-relaxed mb-4">
                            {{ $member->bio ?? 'Dedicated teacher and practitioner guiding seekers in traditional Yogic sciences and Vedanta.' }}
                        </p>

                        @if($member->taughtCourses->isNotEmpty())
                            <div class="pt-3 border-t border-stone-800">
                                <span class="text-[10px] font-semibold text-stone-400 uppercase tracking-wider block mb-1">Courses Taught:</span>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($member->taughtCourses->take(2) as $c)
                                        <span class="px-2 py-0.5 rounded bg-stone-950 border border-stone-800 text-[10px] text-stone-300">{{ $c->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-stone-800 mt-4 flex items-center justify-between">
                        <a href="{{ route('faculty.show', $member->ulid) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                            View Full Bio &rarr;
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div>
            {{ $faculty->links() }}
        </div>
    @else
        <div class="text-center py-16 px-4 bg-stone-900 rounded-2xl border border-stone-800">
            <h2 class="text-lg font-bold text-white mb-2">Faculty profiles are currently being updated</h2>
            <p class="text-xs text-stone-400">Please check back shortly.</p>
        </div>
    @endif
</div>
@endsection
