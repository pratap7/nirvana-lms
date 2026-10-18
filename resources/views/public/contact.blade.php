@extends('layouts.public')

@section('title', 'Contact Admissions & Ashram — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Contact Pramana Institute in Tapovan, Rishikesh. Inquire regarding 200h/300h teacher trainings, ashram accommodations, or online study.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-2">
            Contact & Inquiries
        </h1>
        <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
            Our admissions Acharyas are available to answer curriculum questions, guide accommodation selection, or evaluate prerequisite qualifications.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
        {{-- Contact Information Cards --}}
        <div class="space-y-6">
            <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6 space-y-4">
                <h2 class="text-lg font-serif-title font-bold text-white mb-2">Rishikesh Ashram Campus</h2>
                <div class="space-y-3 text-xs text-stone-300">
                    <p class="flex items-start gap-2.5">
                        <span class="text-amber-400 text-sm">🏛️</span>
                        <span>
                            <strong>Pramana Institute of Yogic Studies</strong><br>
                            Badrinath Marg, Tapovan<br>
                            Rishikesh, Uttarakhand 249192, India
                        </span>
                    </p>
                    <p class="flex items-center gap-2.5">
                        <span class="text-amber-400 text-sm">📞</span>
                        <a href="tel:+911352430000" class="hover:text-white font-mono">+91 (135) 243-0000</a>
                    </p>
                    <p class="flex items-center gap-2.5">
                        <span class="text-amber-400 text-sm">✉️</span>
                        <a href="mailto:admissions@pramana.edu" class="hover:text-white">admissions@pramana.edu</a>
                    </p>
                    <p class="flex items-center gap-2.5">
                        <span class="text-amber-400 text-sm">⏰</span>
                        <span>Visiting Hours: 08:00 – 18:00 IST (Mon – Sat)</span>
                    </p>
                </div>
            </div>

            <div class="bg-stone-900 rounded-2xl border border-stone-800 p-6 space-y-3 text-xs text-stone-400">
                <h3 class="text-white font-bold text-sm">Admissions Assistance</h3>
                <p>
                    Need assistance choosing between the 200-Hour Residential Teacher Training and the Online Sadhana Track? Schedule a personal 15-minute consultation with a senior teacher.
                </p>
            </div>
        </div>

        {{-- Contact Form with Honeypot Spam Protection --}}
        <div class="lg:col-span-2">
            <div class="bg-stone-900 rounded-2xl border border-stone-800 p-8 shadow-xl">
                <h2 class="text-2xl font-serif-title font-bold text-white mb-2">Send an Inquiry</h2>
                <p class="text-xs text-stone-400 mb-6">
                    Fill out the form below. We review each application and inquiry personally.
                </p>

                <form action="{{ route('contact.submit') }}" method="POST" class="space-y-5">
                    @csrf

                    {{-- Honeypot Spam Field: hidden from real users, filled by bots --}}
                    <div style="display:none;" aria-hidden="true">
                        <label for="hp_website">Do not fill this field</label>
                        <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
                    </div>

                    {{-- Form Submission Timestamp Token (blocks < 2s bot submission) --}}
                    <input type="hidden" name="_sub_token" value="{{ $submissionToken }}">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="name" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                                Full Name <span class="text-amber-400">*</span>
                            </label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                required
                                value="{{ old('name') }}"
                                class="w-full bg-stone-950 border border-stone-700 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="Acharya / Student Name"
                            >
                            @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                                Email Address <span class="text-amber-400">*</span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                value="{{ old('email') }}"
                                class="w-full bg-stone-950 border border-stone-700 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="name@domain.com"
                            >
                            @error('email') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="phone" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                                Phone / WhatsApp
                            </label>
                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="{{ old('phone') }}"
                                class="w-full bg-stone-950 border border-stone-700 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="+91 98765 43210"
                            >
                            @error('phone') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="course_interest" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                                Program of Interest
                            </label>
                            <select
                                id="course_interest"
                                name="course_interest"
                                class="w-full bg-stone-950 border border-stone-700 rounded-xl px-4 py-2.5 text-sm text-stone-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                                <option value="">Select a Program (Optional)</option>
                                @foreach($courses as $courseName)
                                    <option value="{{ $courseName }}" {{ old('course_interest', request('interest')) === $courseName ? 'selected' : '' }}>
                                        {{ $courseName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="subject" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                            Subject
                        </label>
                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            value="{{ old('subject') }}"
                            class="w-full bg-stone-950 border border-stone-700 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="Inquiry Topic"
                        >
                    </div>

                    <div>
                        <label for="message" class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                            Your Message <span class="text-amber-400">*</span>
                        </label>
                        <textarea
                            id="message"
                            name="message"
                            rows="5"
                            required
                            class="w-full bg-stone-950 border border-stone-700 rounded-xl p-4 text-sm text-stone-100 placeholder-stone-600 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="Please tell us about your background, sadhana experience, and any questions regarding admission..."
                        >{{ old('message') }}</textarea>
                        @error('message') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <button
                        type="submit"
                        class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-extrabold text-sm px-8 py-3.5 rounded-xl shadow-lg shadow-amber-500/20 transition-all hover:scale-[1.01]"
                    >
                        Submit Inquiry &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
