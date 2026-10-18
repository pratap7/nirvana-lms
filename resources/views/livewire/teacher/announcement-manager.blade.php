<div class="space-y-8" id="announcement-manager-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Cohort Announcements</h1>
            <p class="text-stone-400 text-sm mt-1">Broadcast official notices, curriculum updates, and instructions to assigned batches.</p>
        </div>
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- New Announcement Form -->
        <div class="lg:col-span-1">
            <form wire:submit="postAnnouncement" class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 space-y-4 shadow-xl">
                <h2 class="text-sm font-bold uppercase tracking-wider text-white border-b border-white/5 pb-2">Publish Announcement</h2>

                <div>
                    <label for="announcement_batch" class="block text-xs text-stone-400 font-semibold mb-1">Target Batch</label>
                    <select id="announcement_batch" wire:model="batch_id" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="announcement_title" class="block text-xs text-stone-400 font-semibold mb-1">Announcement Title</label>
                    <input id="announcement_title" type="text" wire:model="title" placeholder="e.g. Schedule Change for Sunday Session"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500" required>
                </div>

                <div>
                    <label for="announcement_content" class="block text-xs text-stone-400 font-semibold mb-1">Notice Content</label>
                    <textarea id="announcement_content" wire:model="content" rows="5" placeholder="Write instructions or notice details..."
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-amber-500" required></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition">
                        Broadcast Announcement
                    </button>
                </div>
            </form>
        </div>

        <!-- Announcements Timeline -->
        <div class="lg:col-span-2 space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400">Recent Notices ({{ $announcements->count() }})</h2>
            <div class="space-y-4">
                @forelse($announcements as $ann)
                    <div class="p-6 rounded-2xl bg-stone-900/60 border border-stone-800 space-y-3 shadow-lg">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                            <span class="text-xs text-amber-400 font-bold uppercase tracking-wider font-mono">{{ $ann->batch?->name }}</span>
                            <span class="text-[10px] text-stone-500 font-mono">{{ $ann->published_at?->format('M d, Y • h:i A') }}</span>
                        </div>
                        <h3 class="text-base font-bold text-white">{{ $ann->title }}</h3>
                        <p class="text-xs text-stone-300 whitespace-pre-wrap leading-relaxed">{{ $ann->content }}</p>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-2xl bg-stone-900/40 border border-white/5 text-stone-400 text-xs">
                        No announcements posted yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
