<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 text-slate-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="Pramana LMS - Student Learning & Sadhana Portal">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <title>{{ $title ?? 'Student Portal' }} - Pramana</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Styles -->
    <style>
        :root {
            --primary: #4338ca;
            --primary-hover: #3730a3;
            --primary-light: #e0e7ff;
            --surface: #ffffff;
            --surface-subtle: #f8fafc;
            --border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Nav */
        .portal-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
            padding: 0 1.5rem;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .portal-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: -0.02em;
        }

        .portal-brand-badge {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            transition: all 0.15s ease-in-out;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: var(--primary-light);
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 4px 10px;
            border-radius: 9999px;
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Main Container */
        .portal-main {
            flex: 1;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            box-sizing: border-box;
        }

        /* Glassmorphism & Cards */
        .card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.07);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-outline {
            background: transparent;
            border-color: #cbd5e1;
            color: var(--text-main);
        }

        .btn-outline:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0e7ff; color: #3730a3; }

        /* Progress Rings */
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform-origin: 50% 50%;
        }

        /* Video Player & Floating Watermark */
        .video-container {
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            width: 100%;
            aspect-ratio: 16 / 9;
        }

        .video-watermark {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            color: rgba(255, 255, 255, 0.75);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-family: 'JetBrains Mono', monospace;
            pointer-events: none;
            user-select: none;
            z-index: 10;
            animation: watermarkDrift 30s infinite alternate ease-in-out;
        }

        @keyframes watermarkDrift {
            0% { top: 20px; right: 20px; }
            50% { top: 75%; right: 60%; }
            100% { top: 30%; right: 15%; }
        }

        /* Accessibility Focus Rings */
        a:focus-visible, button:focus-visible, input:focus-visible {
            outline: 2px solid #6366f1;
            outline-offset: 2px;
        }

        @media (max-width: 768px) {
            .portal-nav { padding: 0 1rem; }
            .nav-links { display: none; }
            .portal-main { padding: 1rem; }
        }
    </style>

    @livewireStyles
</head>
<body>
    <header class="portal-nav" role="banner">
        <a href="/student/dashboard" class="portal-brand" aria-label="Pramana LMS Student Portal">
            <span>Pramana</span>
            <span class="portal-brand-badge">Student</span>
        </a>

        <nav class="nav-links" role="navigation" aria-label="Student Navigation">
            <a href="/student/dashboard" class="nav-link {{ request()->is('student/dashboard*') ? 'active' : '' }}">Dashboard</a>
            <a href="/student/schedule" class="nav-link {{ request()->is('student/schedule*') ? 'active' : '' }}">Schedule</a>
            <a href="/student/assignments" class="nav-link {{ request()->is('student/assignments*') ? 'active' : '' }}">Assignments</a>
            <a href="/student/quizzes" class="nav-link {{ request()->is('student/quizzes*') ? 'active' : '' }}">Quizzes</a>
            <a href="/student/payments" class="nav-link {{ request()->is('student/payments*') ? 'active' : '' }}">Billing</a>
            <a href="/student/profile" class="nav-link {{ request()->is('student/profile*') ? 'active' : '' }}">Profile</a>
        </nav>

        <div style="display: flex; align-items: center; gap: 1rem;">
            @auth
                <div class="user-chip">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                    <span>{{ auth()->user()->full_name }}</span>
                </div>
                <form action="/logout" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.8rem;">Logout</button>
                </form>
            @endauth
        </div>
    </header>

    <main class="portal-main" id="main-content" role="main">
        {{ $slot }}
    </main>

    <footer style="margin-top: auto; padding: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border);">
        Pramana Gurukula Learning System • Built with classical Vedic principles & modern pedagogical integrity
    </footer>

    @livewireScripts
    <script>
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW reg fail:', err));
            });
        }
    </script>
</body>
</html>
