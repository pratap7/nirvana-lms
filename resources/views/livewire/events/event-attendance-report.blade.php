<div class="space-y-8" id="event-report-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('events.show', ['event' => $event->slug]) }}" class="text-xs text-amber-400 hover:text-amber-300 font-semibold flex items-center gap-1">
                    &larr; View Public Event Page
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mt-1">Event Analytics & Attendance Report</h1>
            <p class="text-stone-400 text-xs mt-0.5">{{ $event->title }} • {{ $event->starts_at->format('M d, Y') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('events.checkin', ['event' => $event->id]) }}"
               class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 flex items-center gap-1.5 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Launch QR Scanner &rarr;
            </a>
        </div>
    </div>

    <!-- Feedback Banners -->
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

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Total Registered</span>
            <p class="text-3xl font-extrabold text-white mt-1 font-mono">{{ $totalRegistered }}</p>
            <span class="text-xs text-stone-500 mt-0.5 block">Seats reserved in cohort</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Plus-One Guests</span>
            <p class="text-3xl font-extrabold text-indigo-400 mt-1 font-mono">{{ $totalPlusOnes }}</p>
            <span class="text-xs text-stone-500 mt-0.5 block">Additional guest passes (+1)</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Total Checked In</span>
            <p class="text-3xl font-extrabold text-emerald-400 mt-1 font-mono">{{ $totalCheckedIn }}</p>
            <span class="text-xs text-stone-500 mt-0.5 block">Scanned & admitted attendees</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Turnout Percentage</span>
            <p class="text-3xl font-extrabold text-amber-400 mt-1 font-mono">{{ $turnoutRate }}%</p>
            <span class="text-xs text-stone-500 mt-0.5 block">Physical entrance turnout</span>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="flex items-center gap-2 border-b border-white/5 pb-2">
        <button wire:click="$set('activeTab', 'attendance')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'attendance' ? 'bg-amber-500 text-stone-950 shadow-lg shadow-amber-500/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Roster & Attendance ({{ $registrations->count() }})
        </button>
        <button wire:click="$set('activeTab', 'invitations')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'invitations' ? 'bg-amber-500 text-stone-950 shadow-lg shadow-amber-500/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Invitation Links & CSV Import ({{ $invitations->count() }})
        </button>
    </div>

    <!-- TAB 1: ATTENDANCE & CERTIFICATES -->
    @if($activeTab === 'attendance')
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button wire:click="$set('filterStatus', 'all')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold {{ $filterStatus === 'all' ? 'bg-stone-800 text-white' : 'text-stone-400 hover:text-white' }}">
                        All ({{ $totalRegistered }})
                    </button>
                    <button wire:click="$set('filterStatus', 'checked_in')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold {{ $filterStatus === 'checked_in' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'text-stone-400 hover:text-white' }}">
                        Checked In ({{ $totalCheckedIn }})
                    </button>
                    <button wire:click="$set('filterStatus', 'pending')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold {{ $filterStatus === 'pending' ? 'bg-stone-800 text-white' : 'text-stone-400 hover:text-white' }}">
                        Pending ({{ $totalRegistered - $totalCheckedIn }})
                    </button>
                </div>

                <button wire:click="bulkIssueCertificates"
                        class="px-4 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-amber-400 font-bold text-xs border border-stone-700 shadow flex items-center gap-1.5 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    <span>Bulk Issue Certificates (Attendance Rule)</span>
                </button>
            </div>

            <!-- Attendees Table -->
            <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-stone-300">
                        <thead class="bg-stone-950/80 text-stone-400 uppercase text-[10px] tracking-wider border-b border-white/5 font-mono">
                            <tr>
                                <th class="p-4">Attendee</th>
                                <th class="p-4">Ticket Code</th>
                                <th class="p-4">Guest Pass</th>
                                <th class="p-4">Check-In Status</th>
                                <th class="p-4">Scanned At & Staff</th>
                                <th class="p-4 text-right">Certificate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-mono">
                            @forelse($registrations as $reg)
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="p-4">
                                        <div class="font-sans font-bold text-white text-sm">{{ $reg->user->full_name }}</div>
                                        <div class="text-[11px] text-stone-400">{{ $reg->user->email }}</div>
                                    </td>
                                    <td class="p-4 text-amber-400 font-bold">
                                        {{ $reg->ticket_code }}
                                    </td>
                                    <td class="p-4">
                                        @if($reg->has_plus_one)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-800">
                                                +1: {{ $reg->plus_one_name }}
                                            </span>
                                        @else
                                            <span class="text-stone-500">—</span>
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $reg->is_checked_in ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-stone-800 text-stone-400' }}">
                                            {{ $reg->is_checked_in ? 'Checked In' : 'Not Attended' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-[11px] text-stone-400">
                                        @if($reg->is_checked_in)
                                            <span class="text-stone-200 block">{{ $reg->checked_in_at?->format('h:i A, M d') }}</span>
                                            <span>Staff: {{ $reg->checkedInBy?->full_name ?? 'terminal' }}</span>
                                        @else
                                            <span class="text-stone-600">—</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-right">
                                        <button wire:click="issueCertificateForRegistration({{ $reg->id }})"
                                                class="px-3 py-1.5 rounded-lg text-xs font-sans font-semibold {{ $reg->is_checked_in ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 border border-amber-500/40' : 'bg-stone-800/40 text-stone-600 cursor-not-allowed' }}">
                                            Issue Certificate
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-stone-500 font-sans text-xs">
                                        No registered attendees found matching the filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: INVITATIONS & CSV IMPORT -->
    @if($activeTab === 'invitations')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Invitations List -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white">Generated Single-Use Invitation Links</h3>
                    <span class="text-xs text-stone-400 font-mono">{{ $invitations->count() }} total invites</span>
                </div>

                <div class="rounded-2xl bg-stone-900/60 border border-stone-800 divide-y divide-white/5 overflow-hidden">
                    @forelse($invitations as $inv)
                        <div class="p-4 space-y-2 hover:bg-white/[0.02] transition">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <h4 class="text-sm font-bold text-white">{{ $inv->name }}</h4>
                                    <p class="text-xs text-stone-400 font-mono">{{ $inv->email }}</p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $inv->status === 'used' ? 'bg-stone-800 text-stone-500' : 'bg-emerald-950 text-emerald-400 border border-emerald-800' }}">
                                    {{ $inv->status }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 pt-1 font-mono text-[11px]">
                                <input type="text" readonly value="{{ $inv->getSignedInviteUrl() }}"
                                       class="flex-1 bg-stone-950 border border-stone-800 rounded-lg px-2.5 py-1 text-stone-400 text-[10px] select-all">
                                <span class="text-stone-500 text-[10px]">Expires: {{ $inv->expires_at?->format('M d, Y') ?? 'Never' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-stone-500 text-xs">
                            No invitations generated yet. Import a CSV on the right to dispatch invite links.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right 1 Col: CSV Import Card -->
            <div class="space-y-4">
                <div class="p-6 rounded-3xl bg-stone-900 border border-stone-800 shadow-xl space-y-4">
                    <div>
                        <h3 class="text-base font-bold text-white">CSV Invitee Import</h3>
                        <p class="text-xs text-stone-400 mt-1">Paste CSV with columns: <code class="text-amber-400 font-mono">email,name</code> (one invitee per line).</p>
                    </div>

                    <div>
                        <label class="text-xs text-stone-300 block mb-1">CSV Content</label>
                        <textarea wire:model="csvInviteData" rows="8" placeholder="swami.vivek@example.com,Swami Vivek&#10;ananya.sharma@example.com,Ananya Sharma"
                                  class="w-full bg-stone-950 border border-stone-800 rounded-xl p-3 text-xs font-mono text-white placeholder-stone-600 focus:outline-none focus:border-amber-500"></textarea>
                    </div>

                    <div>
                        <label class="text-xs text-stone-300 block mb-1">Expiration Window (Days)</label>
                        <input type="number" wire:model="inviteExpiryDays" min="1" max="90" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white font-mono">
                    </div>

                    <button wire:click="importCsvInvitations"
                            class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Import & Generate Links</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
