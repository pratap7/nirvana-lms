<div class="space-y-8" id="batch-roster-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Batch Student Rosters</h1>
            <p class="text-stone-400 text-sm mt-1">View enrolled students, tracking attendance records and practical submission progress.</p>
        </div>
        @if($currentBatch)
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-950/60 text-emerald-400 border border-emerald-800/40">
                    Scoped: {{ $currentBatch->name }}
                </span>
            </div>
        @endif
    </div>

    <!-- Batch Selector Pills -->
    <div class="flex flex-wrap gap-2">
        @foreach($assignedBatches as $b)
            <button wire:click="selectBatch('{{ $b->ulid }}')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentBatch && $currentBatch->id === $b->id ? 'bg-amber-500 text-stone-950 shadow-lg shadow-amber-500/20' : 'bg-stone-900 text-stone-300 hover:bg-stone-800 border border-stone-800' }}">
                <span>{{ $b->name }}</span>
                <span class="opacity-70 text-[10px] font-mono">({{ $b->course?->title ?? 'Course' }})</span>
            </button>
        @endforeach
    </div>

    @if(! $currentBatch)
        <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5">
            <p class="text-stone-400">You do not currently have any assigned batch rosters.</p>
        </div>
    @else
        <!-- Filter & Search Bar -->
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="relative w-full sm:w-80">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by student name or email..."
                    class="w-full bg-stone-900 border border-stone-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-amber-500">
            </div>
            <div class="text-xs text-stone-400 font-mono">
                Showing {{ count($rosterData) }} Active Students
            </div>
        </div>

        <!-- Roster Table -->
        <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-stone-300">
                    <thead class="bg-stone-950/80 text-xs uppercase font-semibold text-stone-400 border-b border-stone-800">
                        <tr>
                            <th class="p-4">Student</th>
                            <th class="p-4">Contact</th>
                            <th class="p-4">Attendance Rate</th>
                            <th class="p-4">Assignments</th>
                            <th class="p-4">Status & Indicators</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 font-sans">
                        @forelse($rosterData as $row)
                            <tr class="hover:bg-white/[0.02] transition {{ $row['isAtRisk'] ? 'bg-rose-950/10' : '' }}">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-xs font-bold text-amber-400">
                                            {{ substr($row['student']->first_name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-white text-sm leading-tight">{{ $row['student']->full_name }}</p>
                                            <span class="text-xs text-stone-500 font-mono">ULID: {{ substr($row['student']->ulid, 0, 12) }}...</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-xs font-mono">
                                    <p class="text-stone-300">{{ $row['student']->email }}</p>
                                    <p class="text-stone-500">{{ $row['student']->phone ?? 'No phone' }}</p>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-20 bg-stone-800 rounded-full h-2 overflow-hidden">
                                            <div class="h-2 rounded-full {{ $row['attendanceRate'] >= 75 ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width: {{ $row['attendanceRate'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-bold font-mono {{ $row['attendanceRate'] >= 75 ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ $row['attendanceRate'] }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-xs font-mono font-semibold text-stone-300">
                                    {{ $row['submissions'] }}
                                </td>
                                <td class="p-4">
                                    @if($row['isAtRisk'])
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-950/80 text-rose-300 border border-rose-800/60">
                                            <svg class="w-3 h-3 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            At Risk
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-950/40 text-emerald-400 border border-emerald-800/30">
                                            Good Standing
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('teacher.messages', ['student' => $row['student']->ulid, 'batch' => $currentBatch->ulid]) }}"
                                           class="p-2 rounded-lg bg-stone-800 hover:bg-stone-700 text-stone-300 text-xs font-semibold title='Message Student'">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        </a>
                                        <a href="{{ route('teacher.concerns', ['student' => $row['student']->ulid, 'batch' => $currentBatch->ulid]) }}"
                                           class="p-2 rounded-lg bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/40 text-rose-300 text-xs font-semibold" title="Report Wellbeing Concern">
                                            <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-stone-400 text-sm">
                                    No students found enrolled in this batch.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
