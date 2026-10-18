<div class="space-y-8" id="staff-checkin-console">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs uppercase font-bold tracking-wider text-emerald-400">Live Entrance Gate Terminal</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mt-1">Staff QR Ticket Check-In</h1>
            <p class="text-stone-400 text-xs mt-0.5">Scan attendee QR tickets or enter ticket codes. Prevents offline duplicate entries.</p>
        </div>

        <!-- Event Selector Dropdown -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <select wire:model.live="selectedEventId" class="bg-stone-900 border border-stone-800 rounded-xl px-4 py-2 text-xs text-white">
                @foreach($events as $ev)
                    <option value="{{ $ev->id }}">{{ $ev->title }}</option>
                @endforeach
            </select>

            @if($occurrences->isNotEmpty())
                <select wire:model.live="selectedOccurrenceId" class="bg-stone-900 border border-stone-800 rounded-xl px-4 py-2 text-xs text-white">
                    @foreach($occurrences as $occ)
                        <option value="{{ $occ->id }}">Session #{{ $occ->occurrence_number }} ({{ $occ->starts_at->format('M d, h:i A') }})</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    <!-- Live Gate Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Total Registered</span>
            <p class="text-3xl font-extrabold text-white mt-1 font-mono">{{ $totalRegistered }}</p>
            <span class="text-xs text-stone-500 mt-0.5 block">Total valid ticket passes</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Checked-In Attendees</span>
            <p class="text-3xl font-extrabold text-emerald-400 mt-1 font-mono">{{ $totalCheckedIn }}</p>
            <span class="text-xs text-stone-500 mt-0.5 block">Scanned & admitted at entrance</span>
        </div>
        <div class="p-5 rounded-2xl bg-stone-900/60 border border-stone-800">
            <span class="text-xs uppercase font-bold tracking-wider text-stone-400">Attendance Turnout</span>
            <p class="text-3xl font-extrabold text-amber-400 mt-1 font-mono">{{ $attendanceRate }}%</p>
            <div class="w-full bg-stone-800 h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="bg-amber-400 h-full rounded-full transition-all" style="width: {{ min(100, $attendanceRate) }}%"></div>
            </div>
        </div>
    </div>

    <!-- Scanner & Ticket Code Input Section -->
    <div class="p-6 sm:p-8 rounded-3xl bg-stone-900 border border-stone-800 shadow-2xl space-y-6">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Scan or Enter Ticket Code
            </h2>
            <p class="text-xs text-stone-400 mt-0.5">Use a USB/Bluetooth barcode scanner, camera stream, or type the code manually.</p>
        </div>

        <form wire:submit.prevent="processScan" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <input type="text"
                       wire:model="scanInput"
                       autofocus
                       placeholder="Scan QR or enter code (e.g. TKT-XXXXXXXXXXXX)..."
                       class="w-full bg-stone-950 border-2 border-stone-700 focus:border-amber-500 rounded-2xl px-5 py-3.5 text-base font-mono text-white placeholder-stone-500 outline-none transition">
            </div>
            <button type="submit"
                    class="px-8 py-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-sm shadow-xl shadow-amber-500/20 flex items-center justify-center gap-2 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Check In</span>
            </button>
        </form>

        <!-- DUPLICATE SCAN ALERT BANNER (High Visibility) -->
        @if($duplicateWarning)
            <div class="p-5 rounded-2xl bg-rose-950 border-2 border-rose-600 text-rose-200 space-y-2 animate-bounce">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-rose-600 text-white font-black flex items-center justify-center text-sm shrink-0">!</span>
                    <h3 class="text-base font-black tracking-wide uppercase text-white">DUPLICATE ENTRY DETECTED</h3>
                </div>
                <p class="text-sm font-semibold text-rose-100 pl-11">
                    {{ $duplicateWarning }}
                </p>
            </div>
        @endif

        <!-- SUCCESS CHECK-IN BANNER -->
        @if($successMessage)
            <div class="p-5 rounded-2xl bg-emerald-950/80 border border-emerald-600 text-emerald-200 space-y-2">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-sm shrink-0">✓</span>
                    <h3 class="text-base font-bold text-white">{{ $successMessage }}</h3>
                </div>
                @if($lastCheckedInAttendee)
                    <div class="pl-11 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs font-mono text-emerald-300">
                        <div><strong class="text-emerald-100">Attendee:</strong> {{ $lastCheckedInAttendee['name'] }}</div>
                        <div><strong class="text-emerald-100">Ticket:</strong> {{ $lastCheckedInAttendee['ticket_code'] }}</div>
                        <div><strong class="text-emerald-100">Time:</strong> {{ $lastCheckedInAttendee['time'] }}</div>
                    </div>
                @endif
            </div>
        @endif

        @if($errorMessage)
            <div class="p-4 rounded-xl bg-stone-950 border border-rose-800 text-rose-400 text-xs">
                {{ $errorMessage }}
            </div>
        @endif
    </div>

    <!-- Recent Scans Stream -->
    <div class="space-y-4">
        <h3 class="text-sm font-bold uppercase tracking-wider text-stone-400">Live Admission Feed (Recent Scans)</h3>
        @if($recentCheckIns->isEmpty())
            <div class="p-8 text-center rounded-2xl bg-stone-900/40 border border-white/5 text-stone-500 text-xs">
                No check-ins recorded yet for this event.
            </div>
        @else
            <div class="rounded-2xl bg-stone-900/60 border border-stone-800 divide-y divide-white/5 overflow-hidden font-mono text-xs">
                @foreach($recentCheckIns as $reg)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:bg-white/[0.02] transition">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-lg bg-emerald-950 text-emerald-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <div>
                                <span class="font-bold text-white font-sans text-sm">{{ $reg->user->full_name }}</span>
                                <span class="text-stone-400 block text-[11px]">{{ $reg->ticket_code }} {{ $reg->has_plus_one ? "(+1: {$reg->plus_one_name})" : '' }}</span>
                            </div>
                        </div>
                        <div class="text-right text-[11px] text-stone-400">
                            <span class="text-stone-300 block">{{ $reg->checked_in_at?->format('h:i:s A') }}</span>
                            <span>Scanned by {{ $reg->checkedInBy?->full_name ?? 'staff' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
