<div class="space-y-8" id="attendance-marker-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Mark Session Attendance</h1>
            <p class="text-stone-400 text-sm mt-1">One-tap mobile-friendly marking for live online and offline sessions.</p>
        </div>
        @if($currentSession)
            <div class="flex items-center gap-3">
                @if($isLocked)
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-rose-950/80 text-rose-300 border border-rose-800/60">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Attendance Locked (Over {{ $windowDays }} Days Old)
                    </span>
                @else
                    <button wire:click="markAllPresent"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/20 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Bulk Mark All Present
                    </button>
                @endif
            </div>
        @endif
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    @if($errorMessage)
        <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-700/50 text-rose-200 text-sm flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Moderator Override Modal for Locked Attendance -->
    @if($correctingUserId && $pendingCorrectionStatus)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-stone-900 border border-stone-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center gap-2 text-rose-400 font-bold text-base">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Locked Attendance Override
                </div>
                <p class="text-xs text-stone-300">
                    This session occurred over {{ $windowDays }} days ago and is locked. Academic policy requires an audited explanation for retroactive adjustments.
                </p>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Mandatory Correction Reason</label>
                    <textarea wire:model="correctionReason" rows="3" placeholder="e.g. Approved medical certificate provided on 18 Oct by Moderator..."
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl p-3 text-xs text-white placeholder-stone-600 focus:outline-none focus:border-amber-500"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button wire:click="$set('correctingUserId', null)" type="button" class="px-3 py-1.5 text-xs text-stone-400 hover:text-white">Cancel</button>
                    <button wire:click="submitLockedCorrection" type="button" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold shadow-lg">
                        Submit Audited Override
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Session Picker -->
    <div class="flex flex-wrap gap-2">
        @foreach($sessions as $s)
            <button wire:click="selectSession('{{ $s->ulid }}')"
                class="px-4 py-2.5 rounded-xl text-left border transition {{ $currentSession && $currentSession->id === $s->id ? 'bg-amber-500/10 border-amber-500 text-white' : 'bg-stone-900 border-stone-800 text-stone-400 hover:text-white hover:bg-stone-800' }}">
                <span class="block text-xs font-bold">{{ $s->title }}</span>
                <span class="block text-[10px] text-stone-500 font-mono">{{ $s->starts_at->format('M d, Y • H:i') }}</span>
            </button>
        @endforeach
    </div>

    @if($currentSession)
        <!-- Session Overview Details -->
        <div class="p-6 rounded-2xl bg-stone-900/60 border border-stone-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="text-xs text-amber-400 font-semibold uppercase tracking-wider">{{ $currentSession->batch?->name }}</span>
                <h2 class="text-xl font-bold text-white mt-0.5">{{ $currentSession->title }}</h2>
                <p class="text-xs text-stone-400 mt-1">
                    Scheduled: {{ $currentSession->starts_at->format('l, F j, Y • g:i A') }} to {{ $currentSession->ends_at->format('g:i A') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if($currentSession->meeting_link)
                    <a href="{{ $currentSession->meeting_link }}" target="_blank"
                       class="px-4 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-stone-200 text-xs font-semibold transition">
                        Open Meeting Link
                    </a>
                @endif
            </div>
        </div>

        <!-- Student Attendance Grid (One-Tap Mobile Responsive Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($roster as $item)
                <div class="p-5 rounded-2xl bg-stone-900/80 border {{ $item['status'] === 'present' ? 'border-emerald-700/40 bg-emerald-950/10' : ($item['status'] === 'absent' ? 'border-rose-700/40 bg-rose-950/10' : 'border-stone-800') }} flex flex-col justify-between gap-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">{{ $item['student']->full_name }}</h3>
                            <span class="text-xs text-stone-400 font-mono">{{ $item['student']->email }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                            {{ $item['status'] === 'present' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' :
                               ($item['status'] === 'late' ? 'bg-amber-950 text-amber-400 border border-amber-800' :
                               ($item['status'] === 'absent' ? 'bg-rose-950 text-rose-400 border border-rose-800' :
                               ($item['status'] === 'excused' ? 'bg-blue-950 text-blue-400 border border-blue-800' : 'bg-stone-800 text-stone-400'))) }}">
                            {{ $item['status'] }}
                        </span>
                    </div>

                    <!-- One-Tap Buttons -->
                    <div class="grid grid-cols-4 gap-1.5 pt-2 border-t border-white/5">
                        <button wire:click="markStatus({{ $item['student']->id }}, 'present')"
                            class="py-2 text-[11px] font-bold rounded-lg transition {{ $item['status'] === 'present' ? 'bg-emerald-500 text-stone-950' : 'bg-stone-950 text-stone-300 hover:bg-emerald-950/50 hover:text-emerald-300 border border-stone-800' }}">
                            Present
                        </button>
                        <button wire:click="markStatus({{ $item['student']->id }}, 'late')"
                            class="py-2 text-[11px] font-bold rounded-lg transition {{ $item['status'] === 'late' ? 'bg-amber-500 text-stone-950' : 'bg-stone-950 text-stone-300 hover:bg-amber-950/50 hover:text-amber-300 border border-stone-800' }}">
                            Late
                        </button>
                        <button wire:click="markStatus({{ $item['student']->id }}, 'absent')"
                            class="py-2 text-[11px] font-bold rounded-lg transition {{ $item['status'] === 'absent' ? 'bg-rose-600 text-white' : 'bg-stone-950 text-stone-300 hover:bg-rose-950/50 hover:text-rose-300 border border-stone-800' }}">
                            Absent
                        </button>
                        <button wire:click="markStatus({{ $item['student']->id }}, 'excused')"
                            class="py-2 text-[11px] font-bold rounded-lg transition {{ $item['status'] === 'excused' ? 'bg-blue-500 text-white' : 'bg-stone-950 text-stone-300 hover:bg-blue-950/50 hover:text-blue-300 border border-stone-800' }}">
                            Excused
                        </button>
                    </div>

                    @if($item['record'] && $item['record']->correction_reason)
                        <div class="p-2 rounded bg-stone-950 border border-stone-800 text-[10px] text-amber-400/90 font-mono">
                            Override Reason: {{ $item['record']->correction_reason }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
