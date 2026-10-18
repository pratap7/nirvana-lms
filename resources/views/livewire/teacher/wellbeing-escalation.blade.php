<div class="space-y-8" id="wellbeing-escalation-view">
    <!-- Header -->
    <div class="border-b border-white/5 pb-6">
        <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-rose-950 text-rose-400 border border-rose-800/60 flex items-center justify-center shrink-0">
                🚨
            </span>
            Student Wellbeing & Crisis Escalation
        </h1>
        <p class="text-stone-400 text-sm mt-1">
            Dedicated confidential reporting channel for academic distress, psychological crisis, or student safety concerns. Encrypted and audited.
        </p>
    </div>

    <!-- Crisis Helpline Guidance Banner (Mandatory Requirement) -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-rose-950/40 via-stone-900 to-amber-950/30 border border-rose-700/40 shadow-xl space-y-3">
        <div class="flex items-center gap-2 text-rose-300 font-bold text-sm">
            <svg class="w-5 h-5 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            IMMEDIATE CRISIS & PSYCHOLOGICAL HELPLINE GUIDANCE
        </div>
        <p class="text-xs text-stone-300 leading-relaxed">
            If a student or practitioner is in immediate danger or severe mental health crisis, do not wait for portal review. Contact 24/7 national counseling and emergency resources immediately:
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
            <div class="p-3 rounded-xl bg-stone-950/60 border border-stone-800">
                <span class="text-[10px] uppercase font-bold text-rose-400 block tracking-wider">Tele-MANAS (Govt. 24/7)</span>
                <span class="text-sm font-bold text-white font-mono block mt-0.5">14416 / 1800-891-4416</span>
                <span class="text-[10px] text-stone-400">Toll-free mental health support</span>
            </div>
            <div class="p-3 rounded-xl bg-stone-950/60 border border-stone-800">
                <span class="text-[10px] uppercase font-bold text-amber-400 block tracking-wider">Vandrevala Foundation</span>
                <span class="text-sm font-bold text-white font-mono block mt-0.5">+91 9999 666 555</span>
                <span class="text-[10px] text-stone-400">Free, confidential 24/7 crisis support</span>
            </div>
            <div class="p-3 rounded-xl bg-stone-950/60 border border-stone-800">
                <span class="text-[10px] uppercase font-bold text-emerald-400 block tracking-wider">Emergency Services</span>
                <span class="text-sm font-bold text-white font-mono block mt-0.5">112 / 108</span>
                <span class="text-[10px] text-stone-400">Immediate medical & ambulance response</span>
            </div>
        </div>
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Confidential Report Form -->
        <div class="lg:col-span-1">
            <form wire:submit="reportConcern" class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 space-y-4 shadow-xl">
                <h2 class="text-sm font-bold uppercase tracking-wider text-rose-400 border-b border-white/5 pb-2">
                    Lodge Confidential Concern
                </h2>

                <div>
                    <label for="student_id" class="block text-xs text-stone-400 font-semibold mb-1">Student of Concern (Optional)</label>
                    <select id="student_id" wire:model="student_id" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500">
                        <option value="">-- General / Non-Specific Cohort Concern --</option>
                        @foreach($enrolledStudents as $s)
                            <option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="concern_category" class="block text-xs text-stone-400 font-semibold mb-1">Category</label>
                    <select id="concern_category" wire:model="category" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500">
                        <option value="academic_stress">Academic Distress & Burnout</option>
                        <option value="mental_health">Emotional / Mental Health Concern</option>
                        <option value="physical_safety">Physical Safety / Living Hazard</option>
                        <option value="substance_use">Substance Abuse</option>
                        <option value="harassment">Interpersonal Conflict / Harassment</option>
                        <option value="other">Other Sensitive Concern</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-stone-400 font-semibold mb-1">Urgency Level</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" wire:click="$set('urgency', 'low')"
                            class="py-1.5 text-[10px] font-bold rounded-lg border transition {{ $urgency === 'low' ? 'bg-stone-700 text-white border-stone-500' : 'bg-stone-950 text-stone-400 border-stone-800' }}">
                            Low
                        </button>
                        <button type="button" wire:click="$set('urgency', 'medium')"
                            class="py-1.5 text-[10px] font-bold rounded-lg border transition {{ $urgency === 'medium' ? 'bg-amber-600 text-stone-950 border-amber-500' : 'bg-stone-950 text-stone-400 border-stone-800' }}">
                            Medium
                        </button>
                        <button type="button" wire:click="$set('urgency', 'high')"
                            class="py-1.5 text-[10px] font-bold rounded-lg border transition {{ $urgency === 'high' ? 'bg-orange-600 text-white border-orange-500' : 'bg-stone-950 text-stone-400 border-stone-800' }}">
                            High
                        </button>
                        <button type="button" wire:click="$set('urgency', 'critical')"
                            class="py-1.5 text-[10px] font-bold rounded-lg border transition {{ $urgency === 'critical' ? 'bg-rose-600 text-white border-rose-500 animate-pulse' : 'bg-stone-950 text-stone-400 border-stone-800' }}">
                            Critical
                        </button>
                    </div>
                </div>

                <div>
                    <label for="concern_description" class="block text-xs text-stone-400 font-semibold mb-1">Detailed Description (Encrypted at Rest)</label>
                    <textarea id="concern_description" wire:model="description" rows="5" placeholder="Document specific observations, behavioral changes, or student disclosures factually..."
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl p-3 text-xs text-white placeholder-stone-600 focus:outline-none focus:border-rose-500" required></textarea>
                    @error('description') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-lg shadow-rose-600/30 transition">
                        Submit Confidential Report 🔒
                    </button>
                    <span class="block text-[10px] text-stone-500 text-center mt-2">
                        Stored with 256-bit envelope encryption. Never shown in ordinary messaging.
                    </span>
                </div>
            </form>
        </div>

        <!-- Receipts & Review Area -->
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400">
                {{ $hasReviewPermission ? 'Wellbeing Review Queue (Moderator Privileged)' : 'My Submitted Concern Receipts' }}
            </h2>

            @if($concerns->isEmpty())
                <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5 text-stone-400 text-xs">
                    {{ $hasReviewPermission ? 'No wellbeing concerns currently escalated.' : 'You have not submitted any wellbeing concerns.' }}
                </div>
            @else
                <div class="space-y-3">
                    @foreach($concerns as $con)
                        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800 space-y-3 shadow-lg">
                            <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase
                                        {{ $con->urgency === 'critical' ? 'bg-rose-950 text-rose-400 border border-rose-800' :
                                           ($con->urgency === 'high' ? 'bg-orange-950 text-orange-400 border border-orange-800' : 'bg-stone-800 text-stone-400') }}">
                                        {{ $con->urgency }} Urgency
                                    </span>
                                    <span class="text-xs font-mono font-bold text-white">CONCERN-{{ substr($con->ulid, -8) }}</span>
                                </div>
                                <span class="text-[10px] font-mono text-stone-500">{{ $con->created_at->format('M d, Y • H:i') }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-stone-500 block">Category</span>
                                    <span class="text-stone-200 font-semibold">{{ ucfirst(str_replace('_', ' ', $con->category)) }}</span>
                                </div>
                                <div>
                                    <span class="text-stone-500 block">Status</span>
                                    <span class="text-amber-400 font-mono uppercase font-bold">{{ $con->status }}</span>
                                </div>
                            </div>

                            @if($hasReviewPermission)
                                <div class="pt-2 border-t border-white/5 flex items-center justify-between">
                                    <span class="text-xs text-stone-400">Reported by: <strong class="text-white">{{ $con->reporter?->full_name }}</strong></span>
                                    <button wire:click="selectConcernForReview('{{ $con->ulid }}')"
                                        class="px-3 py-1 rounded-lg bg-stone-800 hover:bg-stone-700 text-xs font-semibold text-white">
                                        Review Details &rarr;
                                    </button>
                                </div>
                            @else
                                <!-- Standard Teacher view: strictly receipt verification -->
                                <div class="p-2.5 rounded-xl bg-stone-950 border border-stone-800/80 text-[11px] text-stone-400">
                                    Receipt Confirmation: This report was encrypted and lodged with the Academic Welfare Board. Details are restricted to protect student privacy.
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Moderator Detailed Review Modal / Section -->
            @if($activeReview && $hasReviewPermission)
                <div class="p-6 rounded-2xl bg-stone-900 border border-rose-800/50 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-white/5 pb-2">
                        <h3 class="text-base font-bold text-white">Confidential Review: CONCERN-{{ substr($activeReview->ulid, -8) }}</h3>
                        <button wire:click="$set('reviewingConcernUlid', null)" class="text-stone-400 hover:text-white text-xs">Close</button>
                    </div>

                    <div class="p-4 rounded-xl bg-stone-950 text-xs text-stone-200 whitespace-pre-wrap leading-relaxed border border-stone-800">
                        <span class="text-[10px] text-rose-400 uppercase font-bold block mb-1">Encrypted Observation Narrative:</span>
                        {{ $activeReview->description }}
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="review_status" class="block text-xs font-semibold text-stone-400 mb-1">Workflow Status</label>
                            <select id="review_status" wire:model="reviewStatus" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                <option value="under_review">Under Review</option>
                                <option value="in_progress">Counselor Assigned / In Progress</option>
                                <option value="escalated">Escalated to Medical Director</option>
                                <option value="resolved">Resolved & Documented</option>
                            </select>
                        </div>
                        <div>
                            <label for="resolution_notes" class="block text-xs font-semibold text-stone-400 mb-1">Resolution & Counseling Notes</label>
                            <textarea id="resolution_notes" wire:model="resolutionNotes" rows="2" placeholder="Record actions taken, follow-up dates..."
                                class="w-full bg-stone-950 border border-stone-800 rounded-xl p-2 text-xs text-white"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button wire:click="updateConcernReview" class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs">
                            Update Resolution Record
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
