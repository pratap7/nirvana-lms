<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Pramana Institute') }} — Teacher Portal</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    @livewireStyles
</head>
<body class="bg-stone-950 text-stone-100 font-sans antialiased selection:bg-amber-500 selection:text-stone-950 min-h-screen flex flex-col">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-40 bg-stone-950/80 backdrop-blur-md border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-6">
                    <a href="{{ route('teacher.dashboard') }}" class="flex items-center gap-2 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center font-bold text-stone-950 shadow-lg shadow-amber-500/20 group-hover:scale-105 transition-transform">
                            P
                        </div>
                        <div>
                            <span class="text-base font-bold tracking-tight text-white block leading-none">Pramana LMS</span>
                            <span class="text-[10px] uppercase tracking-wider text-amber-400 font-semibold">Teacher Portal</span>
                        </div>
                    </a>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="{{ route('teacher.dashboard') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.dashboard') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('teacher.roster') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.roster*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Batches & Rosters
                        </a>
                        <a href="{{ route('teacher.attendance') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.attendance*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Attendance
                        </a>
                        <a href="{{ route('teacher.grading') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.grading*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Grading
                        </a>
                        <a href="{{ route('teacher.sessions') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.sessions*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Sessions
                        </a>
                        <a href="{{ route('teacher.announcements') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.announcements*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Announcements
                        </a>
                        <a href="{{ route('teacher.messages') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.messages*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-stone-400 hover:text-white hover:bg-stone-900' }}">
                            Messages
                        </a>
                        <a href="{{ route('teacher.concerns') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('teacher.concerns*') ? 'bg-rose-500/10 text-rose-400 border border-rose-500/30' : 'text-rose-400 hover:text-rose-300 hover:bg-rose-950/30' }}">
                            Report Concern 🚨
                        </a>
                    </nav>
                </div>

                <!-- Right: Profile & Switcher -->
                <div class="flex items-center gap-4">
                    @if(auth()->user()->hasRole(['Admin', 'Super Admin', 'Moderator']))
                        <a href="/admin" class="text-xs text-stone-400 hover:text-white border border-stone-800 rounded-lg px-2.5 py-1 bg-stone-900">
                            Admin Panel
                        </a>
                    @endif
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-xs font-bold text-amber-400">
                            {{ substr(auth()->user()->first_name, 0, 1) }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <span class="block text-xs font-semibold text-white leading-tight">{{ auth()->user()->full_name }}</span>
                            <span class="block text-[10px] text-amber-400 font-mono">Faculty Member</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="border-t border-white/5 py-6 bg-stone-950 text-center text-xs text-stone-500">
        <p>&copy; {{ date('Y') }} Pramana Institute of Yoga & Indology. Teacher Portal.</p>
    </footer>

    @livewireScripts
</body>
</html>
