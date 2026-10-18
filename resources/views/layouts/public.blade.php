<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Title & Meta --}}
    <title>@yield('title', config('app.name', 'Pramana Institute') . ' — ' . __('site.tagline'))</title>
    <meta name="description" content="@yield('meta_description', 'Pramana Institute of Yogic Studies — Authentic classical Hatha, Ashtanga, Pranayama, and Vedanta teacher training in Rishikesh and globally online. Yoga Alliance and Ayush accredited.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph Meta --}}
    <meta property="og:site_name" content="{{ config('app.name', 'Pramana Institute') }}">
    <meta property="og:title" content="@yield('og_title', config('app.name', 'Pramana Institute') . ' — Centre of Yogic Science')">
    <meta property="og:description" content="@yield('meta_description', 'Authentic Yogic science and Vedantic wisdom from traditional Acharyas in Rishikesh.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', url('/images/pramana-og.jpg'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', config('app.name', 'Pramana Institute'))">
    <meta name="twitter:description" content="@yield('meta_description', 'Authentic Yogic science and Vedantic wisdom from traditional Acharyas in Rishikesh.')">

    {{-- Performance & Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    {{-- Styles --}}
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            brand: {
                                50: '#fffbeb',
                                100: '#fef3c7',
                                200: '#fde68a',
                                300: '#fcd34d',
                                400: '#fbbf24',
                                500: '#f59e0b',
                                600: '#d97706',
                                700: '#b45309',
                                800: '#92400e',
                                900: '#78350f',
                            },
                            vedic: {
                                dark: '#0c0a09',
                                surface: '#1c1917',
                                card: '#292524',
                                gold: '#d4af37',
                                saffron: '#e06d10',
                            }
                        },
                        fontFamily: {
                            sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                            serif: ['Cinzel', 'Georgia', 'serif'],
                            mono: ['JetBrains Mono', 'monospace'],
                        }
                    }
                }
            }
        </script>
    @endif

    {{-- Schema.org JSON-LD Slot --}}
    @if(isset($schema))
        <script type="application/ld+json">
            {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endif
    @yield('schema_extra')

    <style>
        .font-serif-title { font-family: 'Cinzel', Georgia, serif; }
        .glass-header {
            background: rgba(12, 10, 9, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
    </style>
</head>
<body class="bg-stone-950 text-stone-100 font-sans antialiased selection:bg-amber-500 selection:text-stone-950 min-h-screen flex flex-col">
    {{-- Accessible Skip to Main Content Link --}}
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 z-50 bg-amber-500 text-stone-950 px-4 py-2 rounded-lg font-bold shadow-xl">
        Skip to main content
    </a>

    {{-- Top Announcement Bar --}}
    <div class="bg-gradient-to-r from-amber-700 via-amber-600 to-amber-700 text-stone-950 text-xs py-1.5 px-4 font-semibold text-center border-b border-amber-500/20">
        <span>✨ {{ __('site.hero.badge') }}</span>
        <span class="mx-2 opacity-60">•</span>
        <a href="{{ route('courses.index') }}" class="underline hover:text-stone-900 transition-colors">
            Admissions Open for Winter & Spring 2027 Batches &rarr;
        </a>
    </div>

    {{-- Main Navigation Header --}}
    <header class="sticky top-0 z-40 glass-header border-b border-stone-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                {{-- Institute Logo & Brand --}}
                <div class="flex items-center gap-8">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 group" aria-label="Pramana Institute Homepage">
                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-amber-700 flex items-center justify-center font-serif text-white font-bold text-xl shadow-lg shadow-amber-500/20 group-hover:scale-105 transition-transform border border-amber-300/30">
                            ॐ
                        </div>
                        <div>
                            <span class="text-xl font-serif-title font-bold tracking-wider text-amber-100 group-hover:text-amber-400 transition-colors block leading-none">
                                {{ __('site.brand_name') }}
                            </span>
                            <span class="text-[10px] tracking-widest uppercase text-stone-400 font-medium block mt-1">
                                {{ __('site.tagline') }}
                            </span>
                        </div>
                    </a>

                    {{-- Desktop Navigation Links --}}
                    <nav class="hidden lg:flex items-center gap-1 xl:gap-2 text-sm font-medium text-stone-300" aria-label="Main Navigation">
                        <a href="{{ route('home') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('home') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.home') }}
                        </a>
                        <a href="{{ route('courses.index') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('courses.*') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.courses') }}
                        </a>
                        <a href="{{ route('schedule') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('schedule') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.schedule') }}
                        </a>
                        <a href="{{ route('events.calendar') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('events.*') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.events') }}
                        </a>
                        <a href="{{ route('faculty.index') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('faculty.*') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.faculty') }}
                        </a>
                        <a href="{{ route('about') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('about') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.about') }}
                        </a>
                        <a href="{{ route('articles.index') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('articles.*') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.articles') }}
                        </a>
                        <a href="{{ route('faq') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('faq') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.faq') }}
                        </a>
                        <a href="{{ route('contact') }}" class="px-3 py-2 rounded-lg hover:text-amber-400 hover:bg-stone-900/60 transition-colors {{ request()->routeIs('contact') ? 'text-amber-400 bg-stone-900/80 font-semibold' : '' }}">
                            {{ __('site.nav.contact') }}
                        </a>
                    </nav>
                </div>

                {{-- Right Actions: Language Switcher & Portal Login --}}
                <div class="flex items-center gap-3">
                    {{-- Language Switcher --}}
                    <div class="flex items-center bg-stone-900/90 border border-stone-800 rounded-lg p-0.5 text-xs font-semibold">
                        <a href="{{ route('set-locale', 'en') }}" class="px-2 py-1 rounded {{ app()->getLocale() === 'en' ? 'bg-amber-500 text-stone-950 font-bold' : 'text-stone-400 hover:text-stone-200' }}">
                            EN
                        </a>
                        <a href="{{ route('set-locale', 'hi') }}" class="px-2 py-1 rounded {{ app()->getLocale() === 'hi' ? 'bg-amber-500 text-stone-950 font-bold' : 'text-stone-400 hover:text-stone-200' }}">
                            हिन्दी
                        </a>
                    </div>

                    {{-- Portal Button --}}
                    @auth
                        <a href="{{ route('student.dashboard') }}" class="inline-flex items-center gap-2 bg-stone-900 hover:bg-stone-800 text-stone-200 border border-stone-700/80 text-xs font-semibold px-3.5 py-2 rounded-xl transition-all shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            {{ __('site.nav.dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold text-xs px-4 py-2 rounded-xl shadow-lg shadow-amber-500/20 transition-all hover:scale-[1.02]">
                            {{ __('site.nav.portal_login') }}
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    {{-- Main Content Landmark --}}
    <main id="main-content" class="flex-1">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <div class="bg-emerald-950/80 border border-emerald-500/40 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between text-sm shadow-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <div class="bg-red-950/80 border border-red-500/40 text-red-200 px-4 py-3 rounded-xl flex items-center justify-between text-sm shadow-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-stone-900 border-t border-stone-800 text-stone-300 mt-20 pt-16 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                {{-- Col 1: Institute Brand --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 flex items-center justify-center font-serif text-stone-950 font-bold text-lg">
                            ॐ
                        </div>
                        <div>
                            <span class="text-lg font-serif-title font-bold text-white block leading-none">
                                {{ __('site.brand_name') }}
                            </span>
                            <span class="text-[10px] tracking-wider uppercase text-amber-400 font-semibold">
                                {{ __('site.tagline') }}
                            </span>
                        </div>
                    </div>
                    <p class="text-sm text-stone-400 leading-relaxed">
                        {{ __('site.footer.about_text') }}
                    </p>
                    <div class="text-xs text-amber-400/90 font-medium flex items-center gap-2">
                        <span>🏛️ Tapovan Ashram, Rishikesh (UK), India</span>
                    </div>
                </div>

                {{-- Col 2: Academic Programs --}}
                <div>
                    <h3 class="text-white font-bold text-sm tracking-wider uppercase mb-4">{{ __('site.footer.academic_programs') }}</h3>
                    <ul class="space-y-2.5 text-sm text-stone-400">
                        <li><a href="{{ route('courses.index', ['type' => 'long_term']) }}" class="hover:text-amber-400 transition-colors">200-Hour Yoga Teacher Training (Rishikesh)</a></li>
                        <li><a href="{{ route('courses.index', ['type' => 'long_term']) }}" class="hover:text-amber-400 transition-colors">300-Hour Advanced Classical Sadhana</a></li>
                        <li><a href="{{ route('courses.index', ['mode' => 'online']) }}" class="hover:text-amber-400 transition-colors">Pranayama & Kriya Intensive (Online)</a></li>
                        <li><a href="{{ route('courses.index', ['type' => 'short_term']) }}" class="hover:text-amber-400 transition-colors">Patanjali Yoga Sutras Masterclass</a></li>
                        <li><a href="{{ route('courses.index') }}" class="hover:text-amber-400 transition-colors">Ayurvedic Nutrition & Lifestyle</a></li>
                    </ul>
                </div>

                {{-- Col 3: Quick Navigation --}}
                <div>
                    <h3 class="text-white font-bold text-sm tracking-wider uppercase mb-4">{{ __('site.footer.quick_links') }}</h3>
                    <ul class="space-y-2.5 text-sm text-stone-400">
                        <li><a href="{{ route('schedule') }}" class="hover:text-amber-400 transition-colors">{{ __('site.nav.schedule') }}</a></li>
                        <li><a href="{{ route('events.calendar') }}" class="hover:text-amber-400 transition-colors">{{ __('site.nav.events') }}</a></li>
                        <li><a href="{{ route('faculty.index') }}" class="hover:text-amber-400 transition-colors">{{ __('site.nav.faculty') }}</a></li>
                        <li><a href="{{ route('testimonials') }}" class="hover:text-amber-400 transition-colors">{{ __('site.nav.testimonials') }}</a></li>
                        <li><a href="{{ route('articles.index') }}" class="hover:text-amber-400 transition-colors">{{ __('site.nav.articles') }}</a></li>
                        <li><a href="{{ route('faq') }}" class="hover:text-amber-400 transition-colors">{{ __('site.nav.faq') }}</a></li>
                    </ul>
                </div>

                {{-- Col 4: Contact & Accreditations --}}
                <div>
                    <h3 class="text-white font-bold text-sm tracking-wider uppercase mb-4">{{ __('site.footer.contact_us') }}</h3>
                    <div class="space-y-3 text-sm text-stone-400">
                        <p class="flex items-center gap-2">
                            <span>📞</span> <a href="tel:+911352430000" class="hover:text-white">+91 (135) 243-0000</a>
                        </p>
                        <p class="flex items-center gap-2">
                            <span>✉️</span> <a href="mailto:admissions@pramana.edu" class="hover:text-white">admissions@pramana.edu</a>
                        </p>
                        <div class="pt-2">
                            <span class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Accreditations</span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-1 bg-stone-800 rounded border border-stone-700 text-xs font-bold text-amber-300">Yoga Alliance RYS 200/300/500</span>
                                <span class="px-2 py-1 bg-stone-800 rounded border border-stone-700 text-xs font-bold text-orange-300">YCB Ayush Ministry</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-8 border-t border-stone-800 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
                <p>&copy; {{ date('Y') }} {{ __('site.brand_name') }}. {{ __('site.footer.rights_reserved') }}</p>
                <div class="flex items-center gap-6">
                    <a href="{{ route('sitemap') }}" class="hover:text-stone-300 transition-colors">Sitemap XML</a>
                    <a href="{{ route('robots') }}" class="hover:text-stone-300 transition-colors">Robots.txt</a>
                    <a href="{{ route('about') }}" class="hover:text-stone-300 transition-colors">{{ __('site.footer.privacy_policy') }}</a>
                    <a href="{{ route('about') }}" class="hover:text-stone-300 transition-colors">{{ __('site.footer.terms_of_service') }}</a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Cookie Consent Banner --}}
    <aside id="cookie-consent" role="region" aria-label="{{ __('site.cookies.title') }}" class="fixed bottom-4 right-4 max-w-md bg-stone-900/95 border border-amber-500/30 text-stone-200 p-5 rounded-2xl shadow-2xl backdrop-blur-md z-50 transform transition-all duration-300 hidden">
        <div class="flex items-start gap-3">
            <span class="text-2xl">🍪</span>
            <div>
                <h4 class="text-sm font-bold text-white mb-1">{{ __('site.cookies.title') }}</h4>
                <p class="text-xs text-stone-400 leading-relaxed mb-4">
                    {{ __('site.cookies.message') }}
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" id="cookie-accept-all" class="bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs px-3.5 py-1.5 rounded-lg transition-colors">
                        {{ __('site.cookies.accept') }}
                    </button>
                    <button type="button" id="cookie-essential" class="bg-stone-800 hover:bg-stone-700 text-stone-300 text-xs px-3 py-1.5 rounded-lg transition-colors">
                        {{ __('site.cookies.essential_only') }}
                    </button>
                </div>
            </div>
        </div>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var consentBanner = document.getElementById('cookie-consent');
            var acceptBtn = document.getElementById('cookie-accept-all');
            var essentialBtn = document.getElementById('cookie-essential');

            var existingConsent = localStorage.getItem('pramana_cookie_consent') || document.cookie.indexOf('pramana_cookie_consent=') !== -1;
            if (!existingConsent && consentBanner) {
                consentBanner.classList.remove('hidden');
            }

            function setConsent(choice) {
                localStorage.setItem('pramana_cookie_consent', choice);
                document.cookie = 'pramana_cookie_consent=' + choice + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
                if (consentBanner) {
                    consentBanner.classList.add('hidden');
                }
            }

            if (acceptBtn) acceptBtn.addEventListener('click', function() { setConsent('all'); });
            if (essentialBtn) essentialBtn.addEventListener('click', function() { setConsent('essential'); });
        });
    </script>
</body>
</html>
