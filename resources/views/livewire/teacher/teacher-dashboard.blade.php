<div class="space-y-10" id="teacher-dashboard">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Faculty Dashboard</h1>
            <p class="text-stone-400 text-sm mt-1">Manage assigned batches, evaluate student sadhana & assignments, and schedule sessions.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('teacher.attendance') }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 flex items-center gap-1.5 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Mark Attendance
            </a>
            <a href="{{ route('teacher.concerns') }}" class="px-4 py-2 rounded-xl bg-rose-950/60 hover:bg-rose-900/60 border border-rose-800/40 text-rose-300 font-bold text-xs flex items-center gap-1.5 transition">
                <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Report Concern
            </a>
        </div>
    </div>

    <!-- Stats Quick Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800 shadow-lg">
            <span class="text-xs uppercase font-semibold tracking-wider text-stone-400">Assigned Batches</span>
            <p class="text-3xl font-extrabold text-white mt-2 font-mono">{{ $batches->count() }}</p>
            <span class="text-xs text-stone-500 mt-1 block">Active cohorts instructed</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800 shadow-lg">
            <span class="text-xs uppercase font-semibold tracking-wider text-stone-400">Total Enrolled Students</span>
            <p class="text-3xl font-extrabold text-amber-400 mt-2 font-mono">{{ $totalActiveStudents }}</p>
            <span class="text-xs text-stone-500 mt-1 block">Students across your batches</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800 shadow-lg">
            <span class="text-xs uppercase font-semibold tracking-wider text-stone-400">Pending Submissions</span>
            <p class="text-3xl font-extrabold text-white mt-2 font-mono">{{ $pendingSubmissions }}</p>
            <a href="{{ route('teacher.grading') }}" class="text-xs text-amber-400 hover:text-amber-300 font-semibold mt-1 inline-block">Grade submissions &rarr;</a>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800 shadow-lg">
            <span class="text-xs uppercase font-semibold tracking-wider text-stone-400">At-Risk Students</span>
            <p class="text-3xl font-extrabold text-rose-400 mt-2 font-mono">{{ count($atRiskStudents) }}</p>
            <span class="text-xs text-stone-500 mt-1 block">Low attendance (&lt;75%)</span>
        </div>
    </div>

    <!-- At-Risk Warning Box if any -->
    @if(count($atRiskStudents) > 0)
        <div class="p-6 rounded-2xl bg-rose-950/20 border border-rose-800/40 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-rose-900/50 text-rose-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-white">Participation &amp; At-Risk Alert</h2>
                        <p class="text-xs text-stone-400">Students falling below minimum attendance requirements or missing practical sadhana.</p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($atRiskStudents as $item)
                    <div class="p-3.5 rounded-xl bg-stone-900/80 border border-rose-900/30 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-white">{{ $item['student']->full_name }}</p>
                            <p class="text-xs text-stone-400">{{ $item['batch']->name ?? 'Batch' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-800">
                                {{ $item['reason'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Assigned Batches Section -->
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            Assigned Batches & Rosters
        </h2>
        @if($batches->isEmpty())
            <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5">
                <p class="text-stone-400">No batches have been assigned to your faculty profile yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($batches as $batch)
                    <div class="p-6 rounded-2xl bg-stone-900/60 border border-stone-800 shadow-xl flex flex-col justify-between space-y-4 hover:border-amber-500/40 transition">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                    {{ $batch->delivery_mode->label() ?? 'Online' }}
                                </span>
                                <span class="text-xs text-stone-400 font-mono">{{ $batch->enrollments_count }} active students</span>
                            </div>
                            <h3 class="text-lg font-bold text-white">{{ $batch->name }}</h3>
                            <p class="text-xs text-stone-400">{{ $batch->course?->title ?? 'Course' }}</p>
                        </div>
                        <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                            <span class="text-xs text-stone-500 font-mono">{{ $batch->start_date?->format('M Y') }} – {{ $batch->end_date?->format('M Y') }}</span>
                            <a href="{{ route('teacher.roster', ['batch' => $batch->ulid]) }}"
                               class="text-xs font-bold text-amber-400 hover:text-amber-300 flex items-center gap-1">
                                View Roster &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Upcoming Sessions -->
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Upcoming Class Sessions
        </h2>
        @if($upcomingSessions->isEmpty())
            <div class="p-8 text-center rounded-2xl bg-stone-900/40 border border-white/5">
                <p class="text-stone-400 text-sm">No upcoming sessions scheduled.</p>
            </div>
        @else
            <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-lg">
                <div class="divide-y divide-white/5 font-mono">
                    @foreach($upcomingSessions as $sess)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:bg-white/[0.02] transition">
                            <div>
                                <span class="text-xs text-amber-400 font-sans font-semibold block">{{ $sess->batch?->name }}</span>
                                <h4 class="text-sm font-bold text-white font-sans mt-0.5">{{ $sess->title }}</h4>
                                <p class="text-xs text-stone-400 mt-0.5">{{ $sess->starts_at->format('l, M d, Y • h:i A') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                @if($sess->meeting_link)
                                    <a href="{{ $sess->meeting_link }}" target="_blank" rel="noopener noreferrer"
                                       class="px-3 py-1.5 rounded-lg bg-emerald-950/60 border border-emerald-800/40 text-emerald-300 text-xs font-sans font-semibold hover:bg-emerald-900/60 transition">
                                        Join Meeting
                                    </a>
                                @endif
                                <a href="{{ route('teacher.attendance', ['session' => $sess->ulid]) }}"
                                   class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-sans font-bold transition">
                                    Take Attendance
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
