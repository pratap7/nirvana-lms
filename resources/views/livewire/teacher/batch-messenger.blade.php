<div class="space-y-8" id="batch-messenger-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Cohort Direct Messaging</h1>
            <p class="text-stone-400 text-sm mt-1">Direct communication with students strictly within shared batches.</p>
        </div>
        @if($currentBatch)
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-950/60 text-emerald-400 border border-emerald-800/40">
                Shared Cohort: {{ $currentBatch->name }}
            </span>
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

    <!-- Batch Selector Pills -->
    <div class="flex flex-wrap gap-2">
        @foreach($batches as $b)
            <button wire:click="$set('selectedBatchUlid', '{{ $b->ulid }}')"
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentBatch && $currentBatch->id === $b->id ? 'bg-amber-500 text-stone-950 shadow-lg shadow-amber-500/20' : 'bg-stone-900 text-stone-300 hover:bg-stone-800 border border-stone-800' }}">
                <span>{{ $b->name }}</span>
            </button>
        @endforeach
    </div>

    @if(! $currentBatch)
        <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5 text-stone-400 text-sm">
            No assigned batches found.
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-xl min-h-[550px]">
            <!-- Students in Cohort Column -->
            <div class="lg:col-span-1 border-r border-white/5 p-4 space-y-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-stone-400 block pb-2 border-b border-white/5">
                    Students in this Batch
                </span>
                <div class="space-y-1.5 max-h-[500px] overflow-y-auto">
                    @foreach($students as $st)
                        <button type="button" wire:click="selectStudent('{{ $st->ulid }}')"
                            class="w-full text-left p-3 rounded-xl border transition flex items-center gap-3 {{ $selectedStudentUlid === $st->ulid ? 'bg-amber-500/10 border-amber-500 text-white' : 'bg-stone-950/50 border-stone-800 text-stone-300 hover:bg-stone-900' }}">
                            <div class="w-7 h-7 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center text-xs shrink-0">
                                {{ substr($st->first_name, 0, 1) }}
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs font-bold text-white truncate">{{ $st->full_name }}</p>
                                <p class="text-[10px] text-stone-500 font-mono truncate">{{ $st->email }}</p>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Thread & Input Area -->
            <div class="lg:col-span-2 p-6 flex flex-col justify-between">
                <!-- Message Thread -->
                <div class="space-y-4 max-h-[400px] overflow-y-auto mb-4 pr-2">
                    @forelse($messages as $msg)
                        <div class="flex flex-col {{ $msg->sender_id === auth()->id() ? 'items-end' : 'items-start' }}">
                            <span class="text-[10px] text-stone-500 font-mono mb-1">
                                {{ $msg->sender?->full_name }} • {{ $msg->created_at->format('M d, H:i') }}
                            </span>
                            <div class="p-3.5 rounded-2xl max-w-md text-xs leading-relaxed {{ $msg->sender_id === auth()->id() ? 'bg-amber-500 text-stone-950 font-medium rounded-tr-none' : 'bg-stone-800 text-stone-200 rounded-tl-none' }}">
                                {{ $msg->message }}
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-stone-500 text-xs">
                            No messages exchanged yet in this batch thread. Select a student and start a conversation.
                        </div>
                    @endforelse
                </div>

                <!-- Input Box -->
                <form wire:submit="sendMessage" class="flex gap-3 pt-4 border-t border-white/5">
                    <input type="text" wire:model="messageText" placeholder="Write message to student in this shared batch..."
                        class="flex-1 bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-amber-500" required>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/20">
                        Send
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
