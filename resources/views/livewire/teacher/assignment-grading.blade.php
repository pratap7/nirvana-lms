<div class="space-y-8" id="assignment-grading-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Assignment & Sadhana Review</h1>
            <p class="text-stone-400 text-sm mt-1">Review, provide rubric-based feedback, and grade student submissions in assigned cohorts.</p>
        </div>
        <!-- Filter Tabs -->
        <div class="flex gap-2">
            <button wire:click="$set('filter', 'all')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition {{ $filter === 'all' ? 'bg-amber-500 text-stone-950' : 'bg-stone-900 text-stone-400 hover:text-white' }}">
                All
            </button>
            <button wire:click="$set('filter', 'pending')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition {{ $filter === 'pending' ? 'bg-amber-500 text-stone-950' : 'bg-stone-900 text-stone-400 hover:text-white' }}">
                Pending Review
            </button>
            <button wire:click="$set('filter', 'graded')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition {{ $filter === 'graded' ? 'bg-amber-500 text-stone-950' : 'bg-stone-900 text-stone-400 hover:text-white' }}">
                Graded
            </button>
        </div>
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Submissions List -->
        <div class="lg:col-span-1 space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400">Submissions Queue</h2>
            @if($submissions->isEmpty())
                <div class="p-8 text-center rounded-2xl bg-stone-900/40 border border-white/5">
                    <p class="text-stone-400 text-xs">No assignment submissions match your filter.</p>
                </div>
            @else
                <div class="space-y-2 max-h-[700px] overflow-y-auto pr-1">
                    @foreach($submissions as $sub)
                        <button type="button" wire:click="selectSubmission('{{ $sub->ulid }}')"
                            class="w-full text-left p-4 rounded-2xl border transition flex flex-col gap-2 {{ $activeSubmission && $activeSubmission->id === $sub->id ? 'bg-amber-500/10 border-amber-500 text-white' : 'bg-stone-900/60 border-stone-800 text-stone-300 hover:bg-stone-900' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-white">{{ $sub->user?->full_name }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $sub->status === 'graded' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-amber-950 text-amber-400 border border-amber-800' }}">
                                    {{ $sub->status }}
                                </span>
                            </div>
                            <p class="text-xs text-stone-400 line-clamp-1">{{ $sub->assignment?->title }}</p>
                            <div class="flex items-center justify-between text-[10px] text-stone-500 font-mono">
                                <span>{{ $sub->submitted_at?->format('M d, H:i') }}</span>
                                @if($sub->score !== null)
                                    <span class="font-bold text-emerald-400">{{ $sub->score }} / {{ $sub->assignment?->max_score }} pts</span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Grading & Review Panel -->
        <div class="lg:col-span-2">
            @if(! $activeSubmission)
                <div class="p-16 text-center rounded-2xl bg-stone-900/40 border border-white/5 h-full flex flex-col items-center justify-center">
                    <svg class="w-12 h-12 text-stone-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-stone-400 text-sm">Select a submission from the left queue to review and grade.</p>
                </div>
            @else
                <div class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 md:p-8 space-y-6 shadow-xl">
                    <!-- Assignment & Student Details -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-4">
                        <div>
                            <span class="text-xs text-amber-400 font-semibold">{{ $activeSubmission->assignment?->course?->title }}</span>
                            <h3 class="text-xl font-bold text-white mt-0.5">{{ $activeSubmission->assignment?->title }}</h3>
                            <p class="text-xs text-stone-400 mt-1">Student: <strong class="text-white">{{ $activeSubmission->user?->full_name }}</strong> ({{ $activeSubmission->user?->email }})</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-stone-400 block font-mono">Max Score: {{ $activeSubmission->assignment?->max_score }} pts</span>
                            <span class="text-[10px] text-stone-500 font-mono">Attempt #{{ $activeSubmission->submission_number }}</span>
                        </div>
                    </div>

                    <!-- Submission Content View -->
                    <div class="space-y-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400">Student Response</label>
                        @if($activeSubmission->text_content)
                            <div class="p-4 rounded-xl bg-stone-950 border border-stone-800 text-sm text-stone-200 leading-relaxed whitespace-pre-wrap font-sans">
                                {{ $activeSubmission->text_content }}
                            </div>
                        @endif

                        @if($activeSubmission->link_url)
                            <div class="p-3 rounded-xl bg-stone-950 border border-stone-800 flex items-center justify-between text-xs">
                                <span class="text-stone-400">External Resource / Repository Link:</span>
                                <a href="{{ $activeSubmission->link_url }}" target="_blank" rel="noopener noreferrer" class="text-amber-400 hover:text-amber-300 font-mono underline">
                                    {{ $activeSubmission->link_url }}
                                </a>
                            </div>
                        @endif

                        @if($activeSubmission->file_path)
                            <div class="p-3 rounded-xl bg-stone-950 border border-stone-800 flex items-center justify-between text-xs">
                                <span class="text-stone-400">Attached File Submission:</span>
                                <a href="{{ asset('storage/' . $activeSubmission->file_path) }}" target="_blank" download class="text-amber-400 hover:text-amber-300 font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Download File
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Rubric Guide if specified on assignment -->
                    @if($activeSubmission->assignment?->rubric)
                        <div class="p-4 rounded-xl bg-stone-950/70 border border-stone-800 space-y-2">
                            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider block">Grading Rubric Criteria</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                @foreach($activeSubmission->assignment->rubric as $criterion => $pts)
                                    <div class="flex justify-between p-2 rounded bg-stone-900 border border-stone-800/80">
                                        <span class="text-stone-300">{{ ucfirst(str_replace('_', ' ', (string) $criterion)) }}</span>
                                        <span class="text-amber-400 font-mono font-bold">{{ $pts }} pts</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Evaluation Form -->
                    <form wire:submit="gradeSubmission" class="space-y-4 pt-4 border-t border-white/5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="score_input" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">
                                    Score (Out of {{ $activeSubmission->assignment?->max_score }})
                                </label>
                                <input type="number" id="score_input" wire:model="score" min="0" max="{{ $activeSubmission->assignment?->max_score }}"
                                    class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-white text-sm font-mono focus:border-amber-500 focus:outline-none" required>
                                @error('score') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="feedback_input" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">
                                Faculty Feedback & Sadhana Guidance
                            </label>
                            <textarea id="feedback_input" wire:model="feedback" rows="4" placeholder="Provide constructive feedback on alignment, breath awareness, reflections..."
                                class="w-full bg-stone-950 border border-stone-800 rounded-xl p-4 text-white text-xs placeholder-stone-600 focus:border-amber-500 focus:outline-none"></textarea>
                            @error('feedback') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20">
                                Save Grade & Feedback
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
