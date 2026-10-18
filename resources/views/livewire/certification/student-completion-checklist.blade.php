<div class="max-w-4xl mx-auto space-y-8 py-8" id="completion-checklist-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('student.dashboard') }}" class="text-xs text-amber-400 hover:text-amber-300 font-semibold flex items-center gap-1">
                    &larr; Back to Dashboard
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mt-1">Course Completion & Sadhana Audit</h1>
            <p class="text-stone-400 text-xs mt-0.5">{{ $course->title }} • Progress Checklist</p>
        </div>

        <div>
            @if($evaluation->is_passed)
                <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-950 text-emerald-400 border border-emerald-800 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Completion Criteria Satisfied</span>
                </span>
            @else
                <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-950 text-amber-400 border border-amber-800 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>In Progress</span>
                </span>
            @endif
        </div>
    </div>

    <!-- Feedback / Error Flash Banners -->
    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-800 text-emerald-300 text-xs flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $feedbackMessage }}</span>
        </div>
    @endif

    @if($errorMessage)
        <div class="p-4 rounded-xl bg-rose-950/40 border border-rose-800 text-rose-300 text-xs flex items-center gap-2">
            <svg class="w-5 h-5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <!-- Certificate Claim / Download Hero if Passed -->
    @if($existingCertificate)
        <div class="p-6 rounded-3xl bg-gradient-to-r from-amber-950/40 via-stone-900 to-amber-950/40 border border-amber-500/40 shadow-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="space-y-1">
                <span class="text-[10px] uppercase font-bold tracking-widest text-amber-400">Formal Conferment</span>
                <h3 class="text-lg font-bold text-white">Certificate Awarded & Verified</h3>
                <p class="text-xs text-stone-300 font-mono">Certificate #{{ $existingCertificate->certificate_number }} • Conferred {{ $existingCertificate->issue_date->format('M d, Y') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ $existingCertificate->getVerificationUrl() }}" target="_blank"
                   class="px-4 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-white font-semibold text-xs border border-stone-700 transition">
                    View Verification Page &rarr;
                </a>
                <a href="{{ route('certificates.pdf', ['publicToken' => $existingCertificate->public_token]) }}" target="_blank"
                   class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition">
                    Download Official PDF
                </a>
            </div>
        </div>
    @elseif($evaluation->is_passed)
        <div class="p-6 rounded-3xl bg-emerald-950/30 border border-emerald-800/50 shadow-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="space-y-1">
                <span class="text-[10px] uppercase font-bold tracking-widest text-emerald-400">Requirements Complete</span>
                <h3 class="text-lg font-bold text-white">You are eligible for graduation!</h3>
                <p class="text-xs text-stone-300">All attendance, curriculum modules, and assessment criteria have been satisfied.</p>
            </div>
            <button wire:click="claimCertificate"
                    class="px-6 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Confer & Claim Certificate</span>
            </button>
        </div>
    @endif

    <!-- Per-Requirement Evaluation Checklist -->
    <div class="space-y-4">
        <h2 class="text-base font-bold uppercase tracking-wider text-stone-300 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <span>Prescribed Completion Criteria Checklist</span>
        </h2>

        <div class="rounded-3xl bg-stone-900 border border-stone-800 divide-y divide-white/5 overflow-hidden shadow-xl">
            @foreach($evaluation->checklist_results as $item)
                <div class="p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:bg-white/[0.02] transition">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-xl {{ $item['is_satisfied'] ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-rose-950 text-rose-400 border border-rose-800' }} flex items-center justify-center shrink-0 mt-0.5">
                            @if($item['is_satisfied'])
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-bold text-white">{{ $item['label'] }}</h4>
                            <p class="text-xs text-stone-400">{{ $item['reason'] }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-mono shrink-0 pl-12 sm:pl-0">
                        <div class="text-right">
                            <span class="text-stone-500 block text-[10px] uppercase font-sans">Required</span>
                            <span class="text-stone-300 font-bold">{{ $item['required'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-stone-500 block text-[10px] uppercase font-sans">Achieved</span>
                            <span class="{{ $item['is_satisfied'] ? 'text-emerald-400' : 'text-rose-400' }} font-bold">{{ $item['achieved'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
