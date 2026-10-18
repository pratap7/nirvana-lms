<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Pramana Institute') }} — Academic Moderator Console</title>

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
                    <a href="{{ route('moderator.console') }}" class="flex items-center gap-2 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                            M
                        </div>
                        <div>
                            <span class="text-base font-bold tracking-tight text-white block leading-none">Pramana LMS</span>
                            <span class="text-[10px] uppercase tracking-wider text-indigo-400 font-semibold">Academic Moderator Console</span>
                        </div>
                    </a>
                </div>

                <!-- Right: Profile & Admin link -->
                <div class="flex items-center gap-4">
                    <a href="/admin" class="text-xs text-stone-400 hover:text-white border border-stone-800 rounded-lg px-2.5 py-1 bg-stone-900">
                        Filament Admin
                    </a>
                    <a href="{{ route('teacher.dashboard') }}" class="text-xs text-stone-400 hover:text-white border border-stone-800 rounded-lg px-2.5 py-1 bg-stone-900">
                        Teacher Portal
                    </a>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-xs font-bold text-indigo-400">
                            {{ substr(auth()->user()->first_name, 0, 1) }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <span class="block text-xs font-semibold text-white leading-tight">{{ auth()->user()->full_name }}</span>
                            <span class="block text-[10px] text-indigo-400 font-mono">Academic Moderator</span>
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
        <p>&copy; {{ date('Y') }} Pramana Institute of Yoga & Indology. Moderator Console.</p>
    </footer>

    @livewireScripts
</body>
</html>
