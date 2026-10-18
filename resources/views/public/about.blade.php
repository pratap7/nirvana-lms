@extends('layouts.public')

@section('title', 'About Us — Lineage, Philosophy & Ashram — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Learn about Pramana Institute of Yogic Science, our authentic Guru-Shishya Parampara, Rishikesh Tapovan ashram, and accredited teacher training faculty.')

@section('content')
<section class="py-16 bg-stone-900/60 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center max-w-3xl mx-auto">
        <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Guru-Shishya Parampara</span>
        <h1 class="text-4xl sm:text-5xl font-serif-title font-extrabold text-white mb-4">
            Preserving Sacred Knowledge Through Living Practice
        </h1>
        <p class="text-stone-300 text-base leading-relaxed">
            Founded on the banks of the sacred Ganga in Tapovan, Rishikesh, Pramana Institute bridges the pristine lineage of classical Himalayan yoga with rigorous modern pedagogical standards.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">
    {{-- Core Philosophy & The 3 Pramanas --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Epistemology of Yoga</span>
            <h2 class="text-3xl font-serif-title font-bold text-white mb-4">
                What is "Pramana"?
            </h2>
            <div class="space-y-4 text-stone-300 text-sm leading-relaxed">
                <p>
                    In the Patanjali Yoga Sutras (Chapter 1, Sutra 7), <em>Pramana</em> is defined as the valid proof or cognition of truth. The classical tradition recognizes three valid means of knowledge:
                </p>
                <div class="space-y-3 pl-4 border-l-2 border-amber-500/40 my-4">
                    <div>
                        <strong class="text-amber-300 block">1. Pratyaksha (Direct Perception)</strong>
                        <span class="text-xs text-stone-400">Direct experiential observation through disciplined asana, sensory withdrawal (pratyahara), and samadhi.</span>
                    </div>
                    <div>
                        <strong class="text-amber-300 block">2. Anumana (Logical Inference)</strong>
                        <span class="text-xs text-stone-400">Rigorous philosophical reasoning and biomechanical deduction aligned with experiential anatomy.</span>
                    </div>
                    <div>
                        <strong class="text-amber-300 block">3. Agama / Shabda (Scriptural Testimony)</strong>
                        <span class="text-xs text-stone-400">The timeless revelations of the Upanishads, the Yoga Sutras, Hatha Yoga Pradipika, and realized masters.</span>
                    </div>
                </div>
                <p>
                    Our curriculum does not teach yoga as fitness dogma or commercial trends, but as an experiential science verified by your own consciousness.
                </p>
            </div>
        </div>

        {{-- Accreditations & Standards Card --}}
        <div class="bg-stone-900 rounded-3xl border border-stone-800 p-8 space-y-6">
            <h3 class="text-xl font-serif-title font-bold text-white">Global Accreditations</h3>
            <div class="space-y-4 text-sm">
                <div class="p-4 rounded-2xl bg-stone-950 border border-stone-800">
                    <span class="text-amber-400 font-bold block mb-1">Yoga Alliance International (RYS 200, RYS 300, RYS 500)</span>
                    <p class="text-xs text-stone-400">Graduates are internationally recognized and immediately eligible to register as Registered Yoga Teachers (RYT) globally.</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-950 border border-stone-800">
                    <span class="text-amber-400 font-bold block mb-1">Yoga Certification Board (YCB) • Ministry of Ayush</span>
                    <p class="text-xs text-stone-400">Government of India recognized protocol standards for Yoga Protocol Instructors and Wellness Instructors.</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-950 border border-stone-800">
                    <span class="text-amber-400 font-bold block mb-1">Open Badges & Digital Credentialing</span>
                    <p class="text-xs text-stone-400">Every diploma includes cryptographic verification tokens, dynamic QR codes, and tamper-proof verification.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Ashram Facilities & Tapovan Campus --}}
    <section class="bg-stone-900 rounded-3xl border border-stone-800 p-8 lg:p-12">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">Sacred Space</span>
            <h2 class="text-3xl font-serif-title font-bold text-white mb-3">Tapovan Ashram Campus</h2>
            <p class="text-sm text-stone-400">Designed for uninterrupted sadhana, contemplative study, and deep rejuvenation.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-6 rounded-2xl bg-stone-950 border border-stone-800">
                <span class="text-3xl mb-4 block">🧘</span>
                <h3 class="text-base font-bold text-white mb-2">Ganga-Facing Sadhana Halls</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Spacious natural wooden yoga shalas overlooking the Himalayas with natural ventilation and prop support.</p>
            </div>
            <div class="p-6 rounded-2xl bg-stone-950 border border-stone-800">
                <span class="text-3xl mb-4 block">🍲</span>
                <h3 class="text-base font-bold text-white mb-2">Ayurvedic Sattvic Annapurna</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Three fresh, organic, seasonal vegetarian meals daily prepared according to classical tridoshic principles.</p>
            </div>
            <div class="p-6 rounded-2xl bg-stone-950 border border-stone-800">
                <span class="text-3xl mb-4 block">📚</span>
                <h3 class="text-base font-bold text-white mb-2">Vedic Library & Archives</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Over 2,500 classical commentaries, Sanskrit lexicons, and modern academic journals on neurobiology.</p>
            </div>
        </div>
    </section>
</div>
@endsection
