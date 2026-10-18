<div class="space-y-8" id="session-manager-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Class Scheduling & Links</h1>
            <p class="text-stone-400 text-sm mt-1">Schedule live online or in-studio classes, configure meeting links, and broadcast to cohorts.</p>
        </div>
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- New Session Form -->
        <div class="lg:col-span-1">
            <form wire:submit="createSession" class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 space-y-4 shadow-xl">
                <h2 class="text-sm font-bold uppercase tracking-wider text-white border-b border-white/5 pb-2">Schedule New Class</h2>

                <div>
                    <label for="batch_id" class="block text-xs text-stone-400 font-semibold mb-1">Batch Cohort</label>
                    <select id="batch_id" wire:model="batch_id" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->course?->title ?? 'Course' }})</option>
                        @endforeach
                    </select>
                    @error('batch_id') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="session_title" class="block text-xs text-stone-400 font-semibold mb-1">Session Title</label>
                    <input id="session_title" type="text" wire:model="title" placeholder="e.g. Pranayama Kriya & Alignment"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                    @error('title') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="starts_at" class="block text-xs text-stone-400 font-semibold mb-1">Starts At</label>
                        <input id="starts_at" type="datetime-local" wire:model="starts_at"
                            class="w-full bg-stone-950 border border-stone-800 rounded-xl px-2 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label for="ends_at" class="block text-xs text-stone-400 font-semibold mb-1">Ends At</label>
                        <input id="ends_at" type="datetime-local" wire:model="ends_at"
                            class="w-full bg-stone-950 border border-stone-800 rounded-xl px-2 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <div>
                    <label for="meeting_provider" class="block text-xs text-stone-400 font-semibold mb-1">Meeting Type</label>
                    <select id="meeting_provider" wire:model="meeting_provider" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                        <option value="manual">Manual Meeting URL / Direct Link</option>
                        <option value="zoom">Zoom Video</option>
                        <option value="google_meet">Google Meet</option>
                    </select>
                </div>

                <div>
                    <label for="meeting_link" class="block text-xs text-stone-400 font-semibold mb-1">Meeting Link</label>
                    <input id="meeting_link" type="url" wire:model="meeting_link" placeholder="https://zoom.us/j/... or https://meet.google.com/..."
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                    @error('meeting_link') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition">
                        Schedule Session
                    </button>
                </div>
            </form>
        </div>

        <!-- Scheduled Sessions Timeline -->
        <div class="lg:col-span-2 space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400">Scheduled Sessions ({{ $sessions->count() }})</h2>
            <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-xl">
                <div class="divide-y divide-white/5">
                    @forelse($sessions as $sess)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:bg-white/[0.02] transition">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 block font-mono">{{ $sess->batch?->name }}</span>
                                <h4 class="text-sm font-bold text-white mt-0.5">{{ $sess->title }}</h4>
                                <p class="text-xs text-stone-400 mt-0.5 font-mono">{{ $sess->starts_at->format('M d, Y • h:i A') }} – {{ $sess->ends_at->format('h:i A') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($sess->meeting_link)
                                    <a href="{{ $sess->meeting_link }}" target="_blank"
                                        class="px-3 py-1.5 rounded-lg bg-stone-800 hover:bg-stone-700 text-xs font-semibold text-emerald-300">
                                        Open Link
                                    </a>
                                @endif
                                <a href="{{ route('teacher.attendance', ['session' => $sess->ulid]) }}"
                                    class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold">
                                    Attendance
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-stone-400 text-xs">
                            No sessions scheduled for your assigned cohorts.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
