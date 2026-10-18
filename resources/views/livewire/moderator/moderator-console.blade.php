<div class="space-y-8" id="moderator-console-view">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Academic Moderator Console</h1>
            <p class="text-stone-400 text-sm mt-1">Enrollment queues, offline admissions, attendance audits, manual payments, and student records.</p>
        </div>
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

    <!-- Workflow Tabs Bar -->
    <div class="flex flex-wrap gap-2 border-b border-white/5 pb-3">
        <button wire:click="$set('activeTab', 'enrollments')"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ $activeTab === 'enrollments' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            <span>Enrollment Queue</span>
            @if($pendingEnrollments->count() > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-500 text-white font-mono">{{ $pendingEnrollments->count() }}</span>
            @endif
        </button>

        <button wire:click="$set('activeTab', 'offline_reg')"
            class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'offline_reg' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Offline Registration
        </button>

        <button wire:click="$set('activeTab', 'attendance')"
            class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'attendance' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Attendance Corrections (Audited)
        </button>

        <button wire:click="$set('activeTab', 'payment')"
            class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'payment' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Manual Payment Entry
        </button>

        <button wire:click="$set('activeTab', 'tickets')"
            class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'tickets' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Support Desk Inbox
        </button>

        <button wire:click="$set('activeTab', 'lookup')"
            class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === 'lookup' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-stone-900 text-stone-400 hover:text-white border border-stone-800' }}">
            Student Lookup (Permission-Guarded)
        </button>
    </div>

    <!-- 1. ENROLLMENT REVIEW QUEUE -->
    @if($activeTab === 'enrollments')
        <div class="space-y-4">
            <h2 class="text-base font-bold text-white uppercase tracking-wider">Pending Enrollment Applications</h2>
            @if($pendingEnrollments->isEmpty())
                <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5 text-stone-400 text-xs">
                    All student enrollment applications have been reviewed. Queue is clear.
                </div>
            @else
                <div class="rounded-2xl bg-stone-900/60 border border-stone-800 overflow-hidden shadow-xl">
                    <table class="w-full text-left text-sm text-stone-300">
                        <thead class="bg-stone-950/80 text-xs uppercase font-semibold text-stone-400 border-b border-stone-800">
                            <tr>
                                <th class="p-4">Student</th>
                                <th class="p-4">Requested Cohort</th>
                                <th class="p-4">Applied At</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Moderator Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($pendingEnrollments as $enr)
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="p-4">
                                        <p class="font-bold text-white">{{ $enr->student?->full_name }}</p>
                                        <p class="text-xs text-stone-500 font-mono">{{ $enr->student?->email }}</p>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <p class="text-stone-200 font-semibold">{{ $enr->batch?->name }}</p>
                                        <p class="text-stone-500">{{ $enr->course?->title }}</p>
                                    </td>
                                    <td class="p-4 text-xs font-mono text-stone-400">
                                        {{ $enr->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-950 text-amber-400 border border-amber-800">
                                            {{ $enr->status->value }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="approveEnrollment('{{ $enr->ulid }}')"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow">
                                                Approve
                                            </button>
                                            <button wire:click="$set('rejectingEnrollmentUlid', '{{ $enr->ulid }}')"
                                                class="px-3 py-1.5 rounded-lg bg-rose-950/60 hover:bg-rose-900 border border-rose-800/60 text-rose-300 text-xs font-bold">
                                                Reject...
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Rejection Reason Modal -->
            @if($rejectingEnrollmentUlid)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" role="dialog" aria-modal="true">
                    <div class="bg-stone-900 border border-stone-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <h3 class="text-base font-bold text-white">Specify Rejection Reason</h3>
                        <p class="text-xs text-stone-400">The applicant will be informed with this formal justification.</p>
                        <textarea wire:model="rejectionReason" rows="3" placeholder="e.g. Prerequisites not met for Advanced Hatha..."
                            class="w-full bg-stone-950 border border-stone-800 rounded-xl p-3 text-xs text-white placeholder-stone-600 focus:outline-none focus:border-rose-500"></textarea>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button wire:click="$set('rejectingEnrollmentUlid', null)" type="button" class="px-3 py-1.5 text-xs text-stone-400">Cancel</button>
                            <button wire:click="rejectEnrollment" type="button" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold">
                                Confirm Rejection
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- 2. OFFLINE REGISTRATION -->
    @if($activeTab === 'offline_reg')
        <div class="max-w-xl mx-auto rounded-2xl bg-stone-900/60 border border-stone-800 p-8 space-y-6 shadow-xl">
            <div class="border-b border-white/5 pb-3">
                <h2 class="text-lg font-bold text-white">Offline Student Registration</h2>
                <p class="text-xs text-stone-400 mt-1">Register walk-in students or telephone applicants directly into a batch.</p>
            </div>
            <form wire:submit="registerStudentOffline" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-xs text-stone-400 font-semibold mb-1">First Name</label>
                        <input id="first_name" type="text" wire:model="regFirstName" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs text-stone-400 font-semibold mb-1">Last Name</label>
                        <input id="last_name" type="text" wire:model="regLastName" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-xs text-stone-400 font-semibold mb-1">Email Address</label>
                    <input id="email" type="email" wire:model="regEmail" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                </div>
                <div>
                    <label for="phone" class="block text-xs text-stone-400 font-semibold mb-1">Mobile / WhatsApp Number</label>
                    <input id="phone" type="text" wire:model="regPhone" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white">
                </div>
                <div>
                    <label for="batch_id" class="block text-xs text-stone-400 font-semibold mb-1">Target Batch Cohort</label>
                    <select id="batch_id" wire:model="regBatchId" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->course?->title }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="pt-4">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg">
                        Complete Offline Registration
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- 3. ATTENDANCE CORRECTIONS (AUDITED) -->
    @if($activeTab === 'attendance')
        <div class="max-w-xl mx-auto rounded-2xl bg-stone-900/60 border border-stone-800 p-8 space-y-6 shadow-xl">
            <div class="border-b border-white/5 pb-3">
                <h2 class="text-lg font-bold text-white">Attendance Audit Correction</h2>
                <p class="text-xs text-stone-400 mt-1">Retroactively correct session records with mandatory justification.</p>
            </div>
            <form wire:submit="submitAttendanceCorrection" class="space-y-4">
                <div>
                    <label for="att_session_id" class="block text-xs text-stone-400 font-semibold mb-1">Select Session</label>
                    <select id="att_session_id" wire:model="attSessionUlid" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                        <option value="">-- Choose Session --</option>
                        @foreach($sessions as $s)
                            <option value="{{ $s->ulid }}">{{ $s->title }} ({{ $s->batch?->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="att_student_id" class="block text-xs text-stone-400 font-semibold mb-1">Student User ID (Database ID)</label>
                    <input id="att_student_id" type="number" wire:model="attStudentId" placeholder="Enter student user ID"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                </div>
                <div>
                    <label for="att_status" class="block text-xs text-stone-400 font-semibold mb-1">Corrected Status</label>
                    <select id="att_status" wire:model="attNewStatus" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                    </select>
                </div>
                <div>
                    <label for="att_reason" class="block text-xs text-stone-400 font-semibold mb-1">Audit Correction Reason</label>
                    <input id="att_reason" type="text" wire:model="attReason" placeholder="e.g. Student network failure verified by instructor..."
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                    @error('attReason') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg">
                        Submit Audited Attendance Correction
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- 4. MANUAL PAYMENT ENTRY -->
    @if($activeTab === 'payment')
        <div class="max-w-xl mx-auto rounded-2xl bg-stone-900/60 border border-stone-800 p-8 space-y-6 shadow-xl">
            <div class="border-b border-white/5 pb-3">
                <h2 class="text-lg font-bold text-white">Record Manual Offline Payment</h2>
                <p class="text-xs text-stone-400 mt-1">Record Cash, UPI QR, or Bank Transfer payments directly into the ledger.</p>
            </div>
            <form wire:submit="recordManualPayment" class="space-y-4">
                <div>
                    <label for="pay_invoice" class="block text-xs text-stone-400 font-semibold mb-1">Select Pending Invoice</label>
                    <select id="pay_invoice" wire:model="payInvoiceUlid" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                        @foreach($unpaidInvoices as $inv)
                            <option value="{{ $inv->ulid }}">
                                {{ $inv->invoice_number }} — {{ $inv->student?->full_name }} (Due: {{ \App\Support\Money::fromMinor($inv->total_amount, $inv->currency)->format() }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="pay_amount" class="block text-xs text-stone-400 font-semibold mb-1">Amount in Minor Units (e.g. Paise/Cents)</label>
                        <input id="pay_amount" type="number" wire:model="payAmount" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white font-mono" required>
                    </div>
                    <div>
                        <label for="pay_method" class="block text-xs text-stone-400 font-semibold mb-1">Payment Method</label>
                        <select id="pay_method" wire:model="payMethod" class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white" required>
                            <option value="upi">UPI / QR Code</option>
                            <option value="bank_transfer">Bank Wire / NEFT</option>
                            <option value="cash">Cash Receipt</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="pay_reference" class="block text-xs text-stone-400 font-semibold mb-1">Bank Reference / UPI UTR / Cash Slip #</label>
                    <input id="pay_reference" type="text" wire:model="payReference" placeholder="e.g. UTR-9876543210 or CASH-SLIP-042"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-white font-mono" required>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg">
                        Record & Verify Payment in Ledger
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- 5. SUPPORT TICKET INBOX -->
    @if($activeTab === 'tickets')
        <div class="space-y-4">
            <h2 class="text-base font-bold text-white uppercase tracking-wider">Student Support Desk</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Ticket List -->
                <div class="lg:col-span-1 space-y-2">
                    @forelse($tickets as $t)
                        <button type="button" wire:click="$set('selectedTicketUlid', '{{ $t->ulid }}')"
                            class="w-full text-left p-4 rounded-xl border transition flex flex-col gap-1 {{ $activeTicket && $activeTicket->id === $t->id ? 'bg-indigo-950/40 border-indigo-500 text-white' : 'bg-stone-900/60 border-stone-800 text-stone-300' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-mono font-bold text-white">{{ $t->ticket_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold {{ $t->status === 'open' ? 'bg-amber-950 text-amber-400' : 'bg-stone-800 text-stone-400' }}">{{ $t->status }}</span>
                            </div>
                            <h4 class="text-xs font-bold text-stone-200 line-clamp-1">{{ $t->subject }}</h4>
                            <span class="text-[10px] text-stone-500">{{ $t->user?->full_name }} • {{ $t->created_at->format('M d') }}</span>
                        </button>
                    @empty
                        <div class="p-8 text-center rounded-xl bg-stone-900/40 text-stone-500 text-xs">
                            No open support inquiries.
                        </div>
                    @endforelse
                </div>

                <!-- Active Ticket Details -->
                <div class="lg:col-span-2">
                    @if(! $activeTicket)
                        <div class="p-12 text-center rounded-2xl bg-stone-900/40 border border-white/5 text-stone-400 text-xs">
                            Select a support ticket to review conversation and reply.
                        </div>
                    @else
                        <div class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 space-y-4 shadow-xl">
                            <div class="flex items-center justify-between border-b border-white/5 pb-3">
                                <div>
                                    <span class="text-xs font-mono text-indigo-400 font-bold">{{ $activeTicket->ticket_number }}</span>
                                    <h3 class="text-base font-bold text-white">{{ $activeTicket->subject }}</h3>
                                    <p class="text-xs text-stone-400">From: {{ $activeTicket->user?->full_name }} ({{ $activeTicket->user?->email }})</p>
                                </div>
                            </div>

                            <!-- Thread Messages -->
                            <div class="space-y-3 max-h-80 overflow-y-auto pr-2">
                                @foreach($activeTicket->messages as $m)
                                    <div class="p-3 rounded-xl text-xs {{ $m->is_staff_reply ? 'bg-indigo-950/40 border border-indigo-800/40 ml-6 text-stone-200' : 'bg-stone-950 border border-stone-800 mr-6 text-stone-300' }}">
                                        <div class="flex items-center justify-between text-[10px] text-stone-500 mb-1">
                                            <span>{{ $m->user?->full_name }} {{ $m->is_staff_reply ? '(Academic Staff)' : '' }}</span>
                                            <span>{{ $m->created_at->format('M d, H:i') }}</span>
                                        </div>
                                        <p class="whitespace-pre-wrap">{{ $m->message }}</p>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Reply Form -->
                            <form wire:submit="replyToTicket" class="space-y-3 pt-3 border-t border-white/5">
                                <textarea wire:model="ticketReplyText" rows="3" placeholder="Type official response to student..."
                                    class="w-full bg-stone-950 border border-stone-800 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-indigo-500" required></textarea>
                                <div class="flex justify-end">
                                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs">
                                        Send Staff Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- 6. STUDENT LOOKUP (PERMISSION-GUARDED) -->
    @if($activeTab === 'lookup')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-white uppercase tracking-wider">Student Profile Lookup</h2>
                    <p class="text-xs text-stone-400 mt-0.5">Search records. Sensitive health & emergency fields are restricted by permission.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $canViewHealthData ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-stone-800 text-stone-400 border border-stone-700' }}">
                        {{ $canViewHealthData ? 'Health Data View Permitted' : 'Health Data Redacted (Requires student.health.view)' }}
                    </span>
                </div>
            </div>

            <!-- Search input -->
            <div class="relative max-w-md">
                <input type="text" wire:model.live.debounce.300ms="searchStudent" placeholder="Type student name, email, or mobile number..."
                    class="w-full bg-stone-900 border border-stone-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- Student Results Cards -->
            <div class="space-y-4">
                @forelse($searchedStudents as $st)
                    <div class="p-6 rounded-2xl bg-stone-900/60 border border-stone-800 space-y-4 shadow-xl">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-3">
                            <div>
                                <h3 class="text-lg font-bold text-white">{{ $st->full_name }}</h3>
                                <p class="text-xs text-stone-400 font-mono">{{ $st->email }} • {{ $st->phone ?? 'No phone' }} • ID: {{ $st->ulid }}</p>
                            </div>
                        </div>

                        <!-- Enrolled Batches -->
                        <div>
                            <span class="text-xs font-semibold text-stone-400 uppercase tracking-wider block mb-1">Enrolled Cohorts</span>
                            <div class="flex flex-wrap gap-2">
                                @forelse($st->enrollments as $en)
                                    <span class="px-2.5 py-1 rounded-lg text-xs bg-stone-950 border border-stone-800 text-stone-200">
                                        {{ $en->batch?->name }} ({{ $en->course?->title }}) • <strong class="text-amber-400 font-mono uppercase">{{ $en->status->value }}</strong>
                                    </span>
                                @empty
                                    <span class="text-xs text-stone-500 font-mono">No cohort enrollments recorded.</span>
                                @endforelse
                            </div>
                        </div>

                        <!-- Permission-Guarded Health & Emergency Contact Section -->
                        <div class="p-4 rounded-xl {{ $canViewHealthData ? 'bg-amber-950/20 border border-amber-800/40' : 'bg-stone-950 border border-stone-800' }}">
                            <span class="text-xs font-bold uppercase tracking-wider {{ $canViewHealthData ? 'text-amber-400' : 'text-stone-500' }} block mb-2">
                                Confidential Medical & Emergency Contact Record
                            </span>

                            @if($canViewHealthData)
                                @if($st->studentProfile)
                                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                                        <div>
                                            <span class="text-stone-500 block">Emergency Contact</span>
                                            <span class="text-white font-semibold">{{ $st->studentProfile->emergency_contact_name ?? 'None' }}</span>
                                            <span class="text-stone-400 block font-mono">{{ $st->studentProfile->emergency_contact_phone ?? '' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-stone-500 block">Medical Conditions</span>
                                            <span class="text-white">{{ $st->studentProfile->medical_conditions ?? 'None declared' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-stone-500 block">Allergies</span>
                                            <span class="text-white">{{ $st->studentProfile->allergies ?? 'None declared' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-stone-500 block">Dietary Requirements</span>
                                            <span class="text-white">{{ $st->studentProfile->dietary_requirements ?? 'None declared' }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-stone-500">No student profile filled yet.</span>
                                @endif
                            @else
                                <div class="flex items-center gap-2 text-xs text-stone-500 font-mono">
                                    <svg class="w-4 h-4 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    [REDACTED] — Sensitive medical declarations and emergency contact details are restricted to users with 'student.health.view' authorization.
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    @if(trim($searchStudent) !== '')
                        <div class="p-8 text-center rounded-2xl bg-stone-900/40 text-stone-400 text-xs">
                            No students found matching "{{ $searchStudent }}".
                        </div>
                    @else
                        <div class="p-8 text-center rounded-2xl bg-stone-900/40 text-stone-500 text-xs">
                            Type in the search bar above to look up students.
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    @endif
</div>
