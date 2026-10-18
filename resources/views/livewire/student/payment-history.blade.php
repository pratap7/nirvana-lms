<div class="space-y-10" id="student-payment-history-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Billing & Certificates</h1>
            <p class="text-stone-400 text-sm mt-1">Review your invoices, installments, payment receipts, and earned certificates.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/40">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                256-Bit SSL Secured
            </span>
        </div>
    </div>

    @if($successMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ $successMessage }}</span>
            </div>
            <button wire:click="$set('successMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    @if($errorMessage)
        <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-700/50 text-rose-200 text-sm flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Payment Modal -->
    @if($payingInstallment)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="pay-modal-title">
            <div class="bg-stone-900 border border-stone-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-white/5">
                    <h3 id="pay-modal-title" class="text-lg font-bold text-white">Pay Installment #{{ $payingInstallment->installment_number }}</h3>
                    <button wire:click="cancelPayment" class="text-stone-400 hover:text-white p-1 rounded-lg">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-4 rounded-xl bg-stone-950/60 border border-stone-800 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-stone-400">Amount Due:</span>
                        <span class="text-white font-bold text-lg">{{ $payingInstallment->getAmount()->format() }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-stone-400">
                        <span>Due Date:</span>
                        <span>{{ $payingInstallment->due_date->format('M d, Y') }}</span>
                    </div>
                    @if($payingInstallment->grace_date)
                        <div class="flex justify-between text-xs text-stone-400">
                            <span>Grace Period Until:</span>
                            <span class="text-amber-400">{{ $payingInstallment->grace_date->format('M d, Y') }}</span>
                        </div>
                    @endif
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-stone-400">Select Payment Method</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" wire:click="$set('paymentMethod', 'stripe')"
                            class="p-3 rounded-xl border text-left flex flex-col gap-1 transition-all {{ $paymentMethod === 'stripe' ? 'border-amber-500 bg-amber-950/30 text-white' : 'border-stone-800 bg-stone-950 text-stone-400 hover:border-stone-700' }}">
                            <span class="text-sm font-semibold text-white">Credit / Debit Card</span>
                            <span class="text-xs text-stone-400">Via Stripe Checkout</span>
                        </button>
                        <button type="button" wire:click="$set('paymentMethod', 'upi')"
                            class="p-3 rounded-xl border text-left flex flex-col gap-1 transition-all {{ $paymentMethod === 'upi' ? 'border-amber-500 bg-amber-950/30 text-white' : 'border-stone-800 bg-stone-950 text-stone-400 hover:border-stone-700' }}">
                            <span class="text-sm font-semibold text-white">UPI / Net Banking</span>
                            <span class="text-xs text-stone-400">Instant QR / VPA</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/5">
                    <button wire:click="cancelPayment" type="button" class="px-4 py-2 text-sm text-stone-400 hover:text-white rounded-lg">Cancel</button>
                    <button wire:click="processInstallmentPayment" type="button" class="px-5 py-2.5 rounded-xl bg-amber-500 text-stone-950 font-bold hover:bg-amber-400 shadow-lg shadow-amber-500/20 text-sm">
                        Confirm & Pay {{ $payingInstallment->getAmount()->format() }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Certificates Section -->
    @if($certificates->isNotEmpty())
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
                Issued Certificates
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($certificates as $cert)
                    <div class="p-6 rounded-2xl bg-gradient-to-br from-amber-950/20 via-stone-900 to-stone-900 border border-amber-600/30 flex flex-col justify-between shadow-lg relative overflow-hidden">
                        <div class="absolute -top-12 -right-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">Verified</span>
                                <span class="text-xs text-stone-400 font-mono">{{ $cert->issue_date->format('M d, Y') }}</span>
                            </div>
                            <h3 class="text-lg font-bold text-white">{{ $cert->course?->title ?? 'Course' }}</h3>
                            <p class="text-xs text-stone-400 font-mono">ID: {{ $cert->certificate_number }}</p>
                        </div>
                        <div class="pt-6 mt-4 border-t border-white/5 flex items-center justify-between">
                            <span class="text-xs text-stone-400">Pramana LMS Official</span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-400 hover:text-amber-300">
                                Download PDF
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Invoices & Installments -->
    <div class="space-y-6">
        <h2 class="text-xl font-bold text-white">Invoices & Installments</h2>
        @if($invoices->isEmpty())
            <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5">
                <p class="text-stone-400">No invoices have been billed to your account yet.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach($invoices as $inv)
                    <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-lg">
                        <!-- Invoice Header -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-white/5 bg-stone-950/40">
                            <div class="space-y-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-base font-bold text-white font-mono">{{ $inv->invoice_number }}</h3>
                                    @php
                                        $badgeColor = match($inv->status->value) {
                                            'paid' => 'bg-emerald-950/60 text-emerald-300 border-emerald-800/40',
                                            'partially_paid' => 'bg-amber-950/60 text-amber-300 border-amber-800/40',
                                            'overdue' => 'bg-rose-950/60 text-rose-300 border-rose-800/40',
                                            default => 'bg-stone-800 text-stone-300 border-stone-700',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $inv->status->value)) }}
                                    </span>
                                </div>
                                <p class="text-xs text-stone-400">
                                    Course: <span class="text-white">{{ $inv->course?->title ?? 'Enrollment Fee' }}</span>
                                    @if($inv->batch)
                                        • Batch: <span class="text-stone-300">{{ $inv->batch->name }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-6">
                                <div class="text-right">
                                    <p class="text-xs text-stone-400">Total Billed</p>
                                    <p class="text-lg font-bold text-white font-mono">{{ \App\Support\Money::fromMinor($inv->total_amount, $inv->currency)->format() }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-stone-400">Due Date</p>
                                    <p class="text-xs font-semibold text-stone-200">{{ $inv->due_date->format('M d, Y') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Installments List if any -->
                        @if($inv->installments->isNotEmpty())
                            <div class="p-6 space-y-4">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-400">Installment Schedule</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach($inv->installments as $inst)
                                        <div class="p-4 rounded-xl border {{ $inst->isPaid() ? 'bg-stone-950/30 border-stone-800/60' : ($inst->is_overdue ? 'bg-rose-950/20 border-rose-800/50' : 'bg-stone-950/70 border-stone-800') }} flex flex-col justify-between gap-3">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-semibold text-stone-300">Installment #{{ $inst->installment_number }}</span>
                                                @if($inst->isPaid())
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950/60 text-emerald-400 border border-emerald-800/40">Paid</span>
                                                @elseif($inst->is_overdue)
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950/60 text-rose-400 border border-rose-800/40">Overdue</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-400 border border-amber-800/40">Pending</span>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="text-base font-bold text-white font-mono">{{ $inst->getAmount()->format() }}</p>
                                                <p class="text-xs text-stone-400 mt-1">Due: {{ $inst->due_date->format('M d, Y') }}</p>
                                            </div>
                                            @if(! $inst->isPaid())
                                                <button wire:click="selectInstallmentToPay('{{ $inst->ulid }}')"
                                                    class="w-full mt-2 py-2 px-3 rounded-lg bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold transition">
                                                    Pay Now
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Payments / Receipts Ledger -->
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-white">Payment Transactions & Receipts</h2>
        @if($payments->isEmpty())
            <div class="p-8 text-center rounded-2xl bg-stone-900/40 border border-white/5">
                <p class="text-stone-400 text-sm">No transaction records found.</p>
            </div>
        @else
            <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-stone-300">
                        <thead class="bg-stone-950/70 text-xs uppercase font-semibold text-stone-400 border-b border-stone-800">
                            <tr>
                                <th class="p-4">Reference</th>
                                <th class="p-4">Date</th>
                                <th class="p-4">Invoice</th>
                                <th class="p-4">Method</th>
                                <th class="p-4">Amount</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-mono">
                            @foreach($payments as $pmt)
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="p-4 font-bold text-white text-xs">{{ $pmt->reference_number ?? $pmt->ulid }}</td>
                                    <td class="p-4 text-xs font-sans text-stone-400">{{ $pmt->created_at->format('M d, Y H:i') }}</td>
                                    <td class="p-4 text-xs">{{ $pmt->invoice?->invoice_number ?? '-' }}</td>
                                    <td class="p-4 text-xs font-sans uppercase">{{ $pmt->method->value ?? 'Online' }}</td>
                                    <td class="p-4 text-white font-bold">{{ \App\Support\Money::fromMinor($pmt->amount, $pmt->currency)->format() }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-sans font-semibold {{ $pmt->status->value === 'successful' ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40' : 'bg-stone-800 text-stone-300' }}">
                                            {{ ucfirst($pmt->status->value) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
