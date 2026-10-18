@extends('layouts.public')

@section('title', 'Master Academic Schedule & Timetable — ' . config('app.name', 'Pramana Institute'))
@section('meta_description', 'Live timetable of Yogic sadhana sessions, lectures, and upcoming cohorts at Pramana Institute. Subscribe to calendar feeds.')

@section('content')
<section class="py-12 bg-stone-900/40 border-b border-stone-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-3xl sm:text-4xl font-serif-title font-extrabold text-white mb-2">
                    Master Academic Schedule
                </h1>
                <p class="text-stone-300 text-sm max-w-2xl leading-relaxed">
                    Live schedule of daily ashram sadhana, theory lectures, practical evaluations, and online batches across Indian Standard Time (IST).
                </p>
            </div>
            <div>
                @auth
                    <a href="{{ auth()->user()->getIcsFeedUrl() }}" class="inline-flex items-center gap-2 bg-stone-900 hover:bg-stone-800 text-amber-400 border border-amber-500/30 text-xs font-bold px-4 py-2.5 rounded-xl transition-all shadow-md">
                        <span>📅 Subscribe to Personal ICS Feed</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-stone-900 hover:bg-stone-800 text-amber-400 border border-amber-500/30 text-xs font-bold px-4 py-2.5 rounded-xl transition-all">
                        <span>📅 Login to Sync Calendar Feed</span>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-12">
    {{-- Upcoming Scheduled Sessions (Next 14 Days) --}}
    <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-serif-title font-bold text-white">Live Classes & Lectures (Next 14 Days)</h2>
            <span class="text-xs text-stone-400 font-mono">All times in IST (UTC+05:30)</span>
        </div>

        @if($upcomingSessions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-stone-300">
                    <thead class="bg-stone-950 text-xs uppercase tracking-wider text-stone-400 border-b border-stone-800">
                        <tr>
                            <th class="py-3.5 px-4">Date & Time</th>
                            <th class="py-3.5 px-4">Session Topic</th>
                            <th class="py-3.5 px-4">Course & Batch</th>
                            <th class="py-3.5 px-4">Teacher / Acharya</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">ICS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800 text-xs">
                        @foreach($upcomingSessions as $session)
                            <tr class="hover:bg-stone-950/60 transition-colors">
                                <td class="py-3.5 px-4 font-mono">
                                    <span class="block text-white font-bold">{{ $session->starts_at->format('M d, Y') }}</span>
                                    <span class="text-amber-400 text-[11px]">{{ $session->starts_at->format('H:i') }} - {{ $session->ends_at->format('H:i') }} IST</span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-white">
                                    {{ $session->title }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="block text-stone-200 font-semibold">{{ $session->batch?->course?->title ?? 'General Program' }}</span>
                                    <span class="text-stone-500 text-[11px]">{{ $session->batch?->name }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-stone-300">
                                    {{ $session->teachers->first()?->full_name ?? 'Assigned Acharya' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $session->status->value === 'in_progress' ? 'bg-amber-950 text-amber-300 border border-amber-500/40 animate-pulse' : 'bg-stone-800 text-stone-300' }}">
                                        {{ str_replace('_', ' ', $session->status->value) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <a href="{{ route('calendar.session.ics', $session) }}" class="text-amber-400 hover:text-amber-300 font-mono text-xs">
                                        + .ics
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $upcomingSessions->links() }}
            </div>
        @else
            <div class="p-8 text-center bg-stone-950 rounded-xl">
                <p class="text-xs text-stone-400">No public sessions scheduled for the selected timeframe.</p>
            </div>
        @endif
    </section>

    {{-- Active Cohorts & Batches Overview --}}
    @if($activeBatches->isNotEmpty())
        <section class="bg-stone-900 rounded-2xl border border-stone-800 p-8">
            <h2 class="text-xl font-serif-title font-bold text-white mb-6">Current & Upcoming Cohorts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($activeBatches as $batch)
                    <div class="p-5 rounded-xl bg-stone-950 border border-stone-800 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-stone-400 mb-2">
                                <span class="px-2 py-0.5 rounded bg-stone-900 text-amber-300 font-semibold uppercase text-[10px]">{{ $batch->mode ?? 'Online' }}</span>
                                <span class="text-emerald-400 font-bold uppercase text-[10px]">{{ $batch->status->value }}</span>
                            </div>
                            <h3 class="text-base font-bold text-white mb-1">{{ $batch->name }}</h3>
                            <span class="text-xs text-stone-400 block mb-3">{{ $batch->course?->title }}</span>
                            <div class="text-xs text-stone-300 space-y-1 font-mono">
                                <div>Starts: {{ $batch->starts_at ? \Carbon\Carbon::parse($batch->starts_at)->format('M d, Y') : 'Rolling' }}</div>
                                @if($batch->ends_at)
                                    <div>Ends: {{ \Carbon\Carbon::parse($batch->ends_at)->format('M d, Y') }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="pt-4 border-t border-stone-800 mt-4">
                            @if($batch->course)
                                <a href="{{ route('courses.show', $batch->course->slug) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                                    Course Curriculum &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
