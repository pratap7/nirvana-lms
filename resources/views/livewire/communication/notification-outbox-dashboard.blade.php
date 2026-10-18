<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6" id="communication-outbox-dashboard">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-stone-800 pb-5">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Section H • Outbox Monitor</span>
            <h1 class="text-2xl font-black text-white mt-1">Multi-Channel Outbox & Delivery Reliability</h1>
            <p class="text-stone-400 text-xs mt-0.5">Track queued, delivered, failed, and held notifications across Email, Database, WhatsApp, and SMS.</p>
        </div>

        @if($metrics['failed'] > 0)
            <button wire:click="retryAllFailed"
                    class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Retry All Failed ({{ $metrics['failed'] }})</span>
            </button>
        @endif
    </div>

    <!-- Flash message -->
    @if($feedbackMessage)
        <div class="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-800/60 text-emerald-300 text-xs flex items-center gap-2 shadow-lg">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $feedbackMessage }}</span>
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
            <span class="text-[10px] uppercase font-bold text-stone-500 font-mono">Total Dispatches</span>
            <p class="text-2xl font-black text-white mt-1">{{ number_format($metrics['total']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
            <span class="text-[10px] uppercase font-bold text-blue-400 font-mono">Queued</span>
            <p class="text-2xl font-black text-blue-400 mt-1">{{ number_format($metrics['queued']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
            <span class="text-[10px] uppercase font-bold text-emerald-400 font-mono">Sent / Delivered</span>
            <p class="text-2xl font-black text-emerald-400 mt-1">{{ number_format($metrics['sent']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-stone-900 border {{ $metrics['failed'] > 0 ? 'border-rose-800/60 bg-rose-950/20' : 'border-stone-800' }} shadow-md">
            <span class="text-[10px] uppercase font-bold text-rose-400 font-mono">Failed</span>
            <p class="text-2xl font-black text-rose-400 mt-1">{{ number_format($metrics['failed']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
            <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Held (Quiet Hours)</span>
            <p class="text-2xl font-black text-amber-400 mt-1">{{ number_format($metrics['held']) }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-[200px]">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by recipient, template key, subject..."
                   class="w-full px-4 py-2 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs placeholder-stone-500 focus:outline-none focus:border-amber-500">
        </div>

        <div class="flex items-center gap-3">
            <select wire:model.live="selectedStatus" class="px-3 py-2 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs">
                <option value="all">All Statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="selectedChannel" class="px-3 py-2 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs">
                <option value="all">All Channels</option>
                @foreach($channels as $ch)
                    <option value="{{ $ch->value }}">{{ $ch->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Outbox Table -->
    <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden shadow-xl">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-stone-800 bg-stone-950/60 text-stone-400 font-mono text-[10px] uppercase tracking-wider">
                    <th class="py-3 px-4">Channel</th>
                    <th class="py-3 px-4">Recipient</th>
                    <th class="py-3 px-4">Template / Subject</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Attempts</th>
                    <th class="py-3 px-4">Scheduled / Sent</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60 text-stone-300">
                @forelse($outboxes as $outbox)
                    <tr class="hover:bg-white/[0.02] transition">
                        <td class="py-3 px-4">
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider font-mono
                                {{ $outbox->channel->value === 'mail' ? 'bg-sky-950 text-sky-400 border border-sky-800/40' :
                                   ($outbox->channel->value === 'whatsapp' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800/40' :
                                   ($outbox->channel->value === 'database' ? 'bg-purple-950 text-purple-400 border border-purple-800/40' :
                                   'bg-amber-950 text-amber-400 border border-amber-800/40')) }}">
                                {{ $outbox->channel->value }}
                            </span>
                        </td>
                        <td class="py-3 px-4 font-mono font-medium text-white">
                            {{ $outbox->recipient }}
                            @if($outbox->user)
                                <span class="block text-[10px] text-stone-500 font-sans">{{ $outbox->user->full_name }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 max-w-xs">
                            <span class="font-bold text-amber-400 block font-mono text-[11px]">{{ $outbox->template_key }}</span>
                            <span class="text-stone-400 truncate block text-[11px]">{{ $outbox->subject ?? Str::limit($outbox->content, 40) }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                {{ $outbox->status->value === 'delivered' || $outbox->status->value === 'sent' ? 'bg-emerald-950 text-emerald-400' :
                                   ($outbox->status->value === 'failed' ? 'bg-rose-950 text-rose-400 border border-rose-800/50' :
                                   ($outbox->status->value === 'held_for_quiet_hours' ? 'bg-amber-950 text-amber-400' :
                                   'bg-blue-950 text-blue-400')) }}">
                                {{ $outbox->status->value }}
                            </span>
                            @if($outbox->last_error)
                                <span class="block text-[10px] text-rose-400 mt-1 max-w-[180px] truncate" title="{{ $outbox->last_error }}">
                                    {{ $outbox->last_error }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 font-mono">
                            {{ $outbox->attempts }} / {{ $outbox->max_attempts }}
                        </td>
                        <td class="py-3 px-4 text-stone-400 font-mono text-[11px]">
                            {{ $outbox->sent_at?->format('M d H:i') ?? $outbox->scheduled_at?->format('M d H:i') ?? $outbox->created_at->format('M d H:i') }}
                        </td>
                        <td class="py-3 px-4 text-right">
                            @if($outbox->status->value === 'failed' || $outbox->status->value === 'held_for_quiet_hours')
                                <button wire:click="retry({{ $outbox->id }})"
                                        class="px-2.5 py-1 rounded bg-stone-800 hover:bg-stone-700 text-stone-200 text-[10px] font-bold">
                                    Retry
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-stone-500">
                            No notifications match the filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-4 border-t border-stone-800">
            {{ $outboxes->links() }}
        </div>
    </div>
</div>
