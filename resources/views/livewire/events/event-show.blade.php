<div class="space-y-10" id="event-detail-page">
    {{-- Schema.org Event JSON-LD Structured Data --}}
    <script type="application/ld+json">
    {!! json_encode($schemaJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <!-- Feedback / Error Flash Banners -->
    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-800 text-emerald-300 text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $feedbackMessage }}</span>
        </div>
    @endif

    @if($errorMessage)
        <div class="p-4 rounded-xl bg-rose-950/40 border border-rose-800 text-rose-300 text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <!-- Event Hero Banner -->
    <div class="relative rounded-3xl bg-gradient-to-br from-stone-900 via-stone-900 to-amber-950/30 border border-white/10 p-8 sm:p-12 overflow-hidden shadow-2xl">
        <div class="relative z-10 max-w-3xl space-y-4">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    {{ $event->format->label() }}
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-stone-800 text-stone-300 border border-stone-700">
                    {{ $event->pricing_type->label() }}
                </span>
                @if($event->is_recurring)
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        Recurring Series
                    </span>
                @endif
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $event->status->value === 'cancelled' ? 'bg-rose-950 text-rose-400 border border-rose-800' : 'bg-emerald-950 text-emerald-400 border border-emerald-800' }}">
                    {{ $event->status->label() }}
                </span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
                {{ $event->title }}
            </h1>

            <p class="text-stone-300 text-base leading-relaxed">
                {{ $event->description }}
            </p>

            <!-- Date, Time, Venue Meta Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-white/5 font-mono text-xs">
                <div>
                    <span class="text-stone-500 block uppercase tracking-wider font-sans text-[10px]">Date & Time</span>
                    <span class="text-white font-semibold block mt-0.5">{{ $event->starts_at->format('M d, Y • h:i A') }}</span>
                    <span class="text-stone-400 text-[11px] block">{{ $event->timezone }}</span>
                </div>
                <div>
                    <span class="text-stone-500 block uppercase tracking-wider font-sans text-[10px]">Location</span>
                    @if($event->venue)
                        <span class="text-white font-semibold block mt-0.5">{{ $event->venue->name }}</span>
                        <span class="text-stone-400 text-[11px] block">{{ $event->venue->city }}</span>
                    @elseif($event->format->value === 'online')
                        <span class="text-emerald-400 font-semibold block mt-0.5">Live Online Stream</span>
                    @else
                        <span class="text-white font-semibold block mt-0.5">Main Ashram Campus</span>
                    @endif
                </div>
                <div>
                    <span class="text-stone-500 block uppercase tracking-wider font-sans text-[10px]">Admission Fee</span>
                    <span class="text-amber-400 font-bold text-sm block mt-0.5">
                        {{ $event->isPaid() ? $event->getFee()->formatted() : 'Free Admission' }}
                    </span>
                </div>
            </div>

            <!-- Action Buttons: ICS Download & Register Anchor -->
            <div class="flex flex-wrap items-center gap-3 pt-4">
                <a href="{{ route('events.ics', ['event' => $event->slug]) }}"
                   class="px-5 py-2.5 rounded-xl bg-stone-800 hover:bg-stone-700 text-white font-semibold text-xs border border-stone-700 shadow-md flex items-center gap-2 transition">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Download .ICS Calendar File</span>
                </a>

                @if(!$userRegistration)
                    <a href="#registration-section" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition">
                        Register for Event &rarr;
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- MAIN TWO COLUMN GRID: Left Content & Right Ticket / Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- LEFT 2 COLS: Event Details, Recurring Occurrences & Speakers -->
        <div class="lg:col-span-2 space-y-8">
            <!-- 1. Speakers / Guest Teachers Section -->
            @if($event->speakers->isNotEmpty())
                <div class="space-y-4">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Featured Speakers & Faculty
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($event->speakers as $speaker)
                            <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800 flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center font-bold text-stone-950 text-sm shrink-0">
                                    {{ substr($speaker->name, 0, 2) }}
                                </div>
                                <div class="space-y-1">
                                    <h3 class="text-sm font-bold text-white">{{ $speaker->name }}</h3>
                                    <p class="text-xs text-amber-400 font-medium">{{ $speaker->title ?? 'Acharya' }}</p>
                                    @if($speaker->bio)
                                        <p class="text-xs text-stone-400 leading-relaxed">{{ $speaker->bio }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 2. Occurrences List (For Recurring Events) -->
            @if($event->is_recurring && $event->occurrences->isNotEmpty())
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Recurring Occurrences
                        </h2>
                        <span class="text-xs text-stone-400 font-mono">{{ $event->occurrences->count() }} sessions scheduled</span>
                    </div>

                    <div class="rounded-2xl bg-stone-900/60 border border-stone-800 divide-y divide-white/5 overflow-hidden">
                        @foreach($event->occurrences as $occ)
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 {{ $occ->status->value === 'cancelled' ? 'bg-rose-950/10' : '' }}">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-mono font-bold text-stone-400">#{{ $occ->occurrence_number }}</span>
                                        <h4 class="text-sm font-bold {{ $occ->status->value === 'cancelled' ? 'text-stone-400 line-through' : 'text-white' }}">
                                            {{ $occ->starts_at->format('l, F d, Y • h:i A') }}
                                        </h4>
                                    </div>
                                    @if($occ->status->value === 'cancelled' && $occ->cancellation_reason)
                                        <p class="text-xs text-rose-400 font-mono">Cancelled: {{ $occ->cancellation_reason }}</p>
                                    @endif
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $occ->status->value === 'cancelled' ? 'bg-rose-950 text-rose-400 border border-rose-800' : 'bg-emerald-950 text-emerald-400 border border-emerald-800' }}">
                                    {{ $occ->status->label() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 3. Post-Event Feedback & Certificate Section if Registered & Checked In -->
            @if($userRegistration && $userRegistration->is_checked_in)
                <div class="p-6 rounded-2xl bg-stone-900/80 border border-amber-500/30 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-white">Event Attendance & Completion</h3>
                            <p class="text-xs text-stone-400">You checked in to this event. You can claim your verified certificate and submit feedback.</p>
                        </div>
                        @if($userCertificate)
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                Certificate #{{ $userCertificate->certificate_number }}
                            </span>
                        @else
                            <button wire:click="claimCertificate"
                                    class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition">
                                Claim Certificate &rarr;
                            </button>
                        @endif
                    </div>

                    <!-- Feedback Form -->
                    @if(!$feedbackSubmitted && !$userRegistration->feedback)
                        <div class="pt-4 border-t border-white/5 space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-stone-400">Submit Attendee Feedback</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-stone-300 block mb-1">Overall Rating (1 to 5 Stars)</label>
                                    <select wire:model="feedbackRating" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                        <option value="5">★★★★★ Excellent (5/5)</option>
                                        <option value="4">★★★★☆ Good (4/5)</option>
                                        <option value="3">★★★☆☆ Average (3/5)</option>
                                        <option value="2">★★☆☆☆ Needs Improvement (2/5)</option>
                                        <option value="1">★☆☆☆☆ Poor (1/5)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-stone-300 block mb-1">Recommendation Score (0-10 NPS)</label>
                                    <select wire:model="feedbackNps" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                        @for($i = 10; $i >= 1; $i--)
                                            <option value="{{ $i }}">{{ $i }} / 10</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <div>
                                <textarea wire:model="feedbackComments" rows="2" placeholder="What key insights or feedback would you like to share with the ashram team?"
                                          class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white placeholder-stone-500"></textarea>
                            </div>
                            <button wire:click="submitFeedback" class="px-4 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-white font-semibold text-xs">
                                Submit Feedback
                            </button>
                        </div>
                    @else
                        <div class="p-3 rounded-xl bg-emerald-950/20 border border-emerald-800/40 text-emerald-300 text-xs">
                            ✓ Thank you! Your feedback has been recorded.
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- RIGHT 1 COL: QR Admission Ticket OR Registration Form -->
        <div class="space-y-6" id="registration-section">
            @if($userRegistration)
                <!-- ADMISSION TICKET CARD -->
                <div class="p-6 rounded-3xl bg-stone-900 border border-amber-500/30 shadow-2xl space-y-5">
                    <div class="flex items-center justify-between border-b border-white/5 pb-4">
                        <div>
                            <span class="text-[10px] uppercase font-bold tracking-wider text-amber-400 block">Pramana LMS Admission Pass</span>
                            <h3 class="text-lg font-black text-white mt-0.5">Verified Ticket</h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $userRegistration->is_checked_in ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                            {{ $userRegistration->is_checked_in ? 'Checked In' : 'Active Pass' }}
                        </span>
                    </div>

                    <!-- QR Code Canvas Display -->
                    <div class="p-4 bg-white rounded-2xl flex flex-col items-center justify-center space-y-2">
                        @if($userRegistration->qr_code_svg)
                            <div class="w-48 h-48 flex items-center justify-center">
                                {!! $userRegistration->qr_code_svg !!}
                            </div>
                        @else
                            <div class="w-48 h-48 bg-stone-100 flex items-center justify-center text-stone-900 font-mono text-xs">
                                {{ $userRegistration->ticket_code }}
                            </div>
                        @endif
                        <span class="text-stone-900 font-mono font-bold text-xs tracking-wider">
                            {{ $userRegistration->ticket_code }}
                        </span>
                    </div>

                    <!-- Ticket Details -->
                    <div class="space-y-2 text-xs font-mono">
                        <div class="flex justify-between py-1 border-b border-white/5">
                            <span class="text-stone-400 font-sans">Attendee:</span>
                            <span class="text-white font-bold">{{ $userRegistration->user->full_name }}</span>
                        </div>
                        @if($userRegistration->has_plus_one)
                            <div class="flex justify-between py-1 border-b border-white/5">
                                <span class="text-stone-400 font-sans">+1 Guest Pass:</span>
                                <span class="text-amber-400 font-bold">{{ $userRegistration->plus_one_name }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between py-1 border-b border-white/5">
                            <span class="text-stone-400 font-sans">Status:</span>
                            <span class="text-emerald-400 font-bold uppercase">{{ $userRegistration->enrollment?->status?->value ?? 'active' }}</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-stone-400 text-center">
                        Please present this QR code at the entrance check-in counter on your mobile device.
                    </p>
                </div>
            @else
                <!-- REGISTRATION FORM CARD -->
                <div class="p-6 rounded-3xl bg-stone-900/80 border border-stone-800 shadow-xl space-y-5">
                    <div>
                        <h3 class="text-lg font-bold text-white">Event Registration</h3>
                        <p class="text-xs text-stone-400 mt-1">
                            {{ $event->isPaid() ? 'Reserve your seat. You will receive an invoice and QR ticket.' : 'Admission is complimentary. Reserve your seat below.' }}
                        </p>
                    </div>

                    <!-- Guest Details if not logged in -->
                    @guest
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[11px] text-stone-400 block mb-1 font-medium">First Name</label>
                                    <input type="text" wire:model="guestFirstName" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                </div>
                                <div>
                                    <label class="text-[11px] text-stone-400 block mb-1 font-medium">Last Name</label>
                                    <input type="text" wire:model="guestLastName" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                </div>
                            </div>
                            <div>
                                <label class="text-[11px] text-stone-400 block mb-1 font-medium">Email Address</label>
                                <input type="email" wire:model="guestEmail" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                            </div>
                            <div>
                                <label class="text-[11px] text-stone-400 block mb-1 font-medium">Mobile Number</label>
                                <input type="text" wire:model="guestPhone" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                            </div>
                        </div>
                    @endguest

                    <!-- Optional Plus-One / Guest Pass Toggle -->
                    @if($event->allow_plus_one)
                        <div class="p-4 rounded-2xl bg-stone-950 border border-stone-800 space-y-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="hasPlusOne" class="rounded bg-stone-900 border-stone-700 text-amber-500 focus:ring-0">
                                <span class="text-xs font-semibold text-white">Bring a Guest / Plus-One (+1)</span>
                            </label>

                            @if($hasPlusOne)
                                <div class="space-y-2 pt-2 border-t border-white/5">
                                    <div>
                                        <label class="text-[10px] text-stone-400 block mb-1 uppercase tracking-wider">Guest Full Name</label>
                                        <input type="text" wire:model="plusOneName" placeholder="Guest name" class="w-full bg-stone-900 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-stone-400 block mb-1 uppercase tracking-wider">Guest Email (Optional)</label>
                                        <input type="email" wire:model="plusOneEmail" placeholder="guest@example.com" class="w-full bg-stone-900 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Invitation Token Input if Invitation-Only -->
                    @if($event->isInvitationOnly())
                        <div class="space-y-1">
                            <label class="text-xs text-amber-400 font-semibold block">Single-Use Invitation Token</label>
                            <input type="text" wire:model="inviteToken" placeholder="Paste your 48-char invite token" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white font-mono">
                            <span class="text-[10px] text-stone-500 block">This event requires a personal invitation token or signed link.</span>
                        </div>
                    @endif

                    <!-- Fee Summary if Paid -->
                    @if($event->isPaid())
                        <div class="p-4 rounded-2xl bg-amber-950/20 border border-amber-900/30 flex items-center justify-between font-mono">
                            <div>
                                <span class="text-xs text-stone-400 block">Total Due:</span>
                                <span class="text-xs text-stone-500">{{ $hasPlusOne ? '2 Tickets (You + Guest)' : '1 Ticket' }}</span>
                            </div>
                            <span class="text-lg font-bold text-amber-400">
                                {{ Money::fromMinor($event->fee_amount * ($hasPlusOne ? 2 : 1), $event->fee_currency)->formatted() }}
                            </span>
                        </div>
                    @endif

                    <!-- Submit Button -->
                    <button wire:click="register"
                            class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition flex items-center justify-center gap-2">
                        <span>{{ $event->isPaid() ? 'Proceed to Payment & Ticket' : 'Confirm Registration & Get Ticket' }}</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
