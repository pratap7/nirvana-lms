<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6" id="reporting-dashboard-root">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-stone-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Section H • Executive Intelligence</span>
                <span class="px-2 py-0.5 text-[10px] font-mono bg-emerald-950 text-emerald-400 border border-emerald-800 rounded-full">Live Analytics</span>
            </div>
            <h1 class="text-2xl font-black text-white mt-1">Institute Operational & Financial Intelligence</h1>
            <p class="text-stone-400 text-xs mt-0.5">Read-optimized analytics across admissions funnel, revenue, dues, attendance, completion, workloads, and events.</p>
        </div>

        <!-- Action / Export Button -->
        <div class="flex items-center gap-3">
            <button wire:click="triggerExport('csv')"
                    class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Current Tab (CSV)
            </button>
        </div>
    </div>

    <!-- Alert / Export Link -->
    @if($flashMessage)
        <div class="p-4 rounded-xl bg-amber-950/60 border border-amber-800/80 text-amber-200 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ $flashMessage }}</span>
            </div>
            @if($lastExportDownloadUrl)
                <a href="{{ $lastExportDownloadUrl }}" target="_blank"
                   class="px-3 py-1 bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold rounded-lg text-xs shadow">
                    Download File Now
                </a>
            @endif
        </div>
    @endif

    <!-- Global Date & Filter Bar -->
    <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 flex flex-wrap items-center justify-between gap-4">
        <!-- Date Presets -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] uppercase font-bold text-stone-400 font-mono mr-1">Period:</span>
            @foreach(['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'last_30_days' => 'Last 30 Days', 'this_quarter' => 'Quarter', 'this_year' => 'Year'] as $key => $label)
                <button wire:click="$set('datePreset', '{{ $key }}')"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $datePreset === $key ? 'bg-amber-500 text-stone-950 font-bold' : 'bg-stone-950 text-stone-300 hover:bg-stone-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <!-- Custom Date Range & Course Filter -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 bg-stone-950 px-3 py-1 rounded-xl border border-stone-800">
                <span class="text-[10px] text-stone-400 font-mono">FROM</span>
                <input type="date" wire:model.live="dateFrom" class="bg-transparent text-white text-xs border-0 focus:ring-0 p-0">
                <span class="text-[10px] text-stone-400 font-mono">TO</span>
                <input type="date" wire:model.live="dateTo" class="bg-transparent text-white text-xs border-0 focus:ring-0 p-0">
            </div>

            <select wire:model.live="selectedCourseId" class="px-3 py-1.5 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs">
                <option value="">All Courses</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-stone-800 overflow-x-auto gap-1">
        @php
            $tabs = [
                'funnel' => '1. Admissions Funnel',
                'revenue' => '2. Revenue & Billing',
                'dues' => '3. Outstanding Dues',
                'attendance' => '4. Batch Attendance',
                'completion' => '5. Course Completion',
                'workload' => '6. Faculty Workload',
                'certification' => '7. Certificates',
                'events' => '8. Event Registrations',
            ];
        @endphp
        @foreach($tabs as $tabKey => $tabLabel)
            <button wire:click="setTab('{{ $tabKey }}')"
                    class="px-4 py-3 text-xs font-bold transition whitespace-nowrap border-b-2 {{ $activeTab === $tabKey ? 'border-amber-500 text-amber-400 bg-amber-500/10' : 'border-transparent text-stone-400 hover:text-stone-200 hover:bg-stone-900/50' }}">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    <!-- Tab 1: Enrollment Funnel -->
    @if($activeTab === 'funnel')
        <div class="space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-500 font-mono">Total Inflow / Leads</span>
                    <p class="text-2xl font-black text-white mt-1">{{ $data['total_applications'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-emerald-400 font-mono">Active & Enrolled</span>
                    <p class="text-2xl font-black text-emerald-400 mt-1">{{ $data['total_enrolled'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Overall Conversion Rate</span>
                    <p class="text-2xl font-black text-amber-400 mt-1">{{ $data['conversion_rate_pct'] ?? 0 }}%</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-sky-400 font-mono">Review Approval Rate</span>
                    <p class="text-2xl font-black text-sky-400 mt-1">{{ $data['review_approval_rate_pct'] ?? 0 }}%</p>
                </div>
            </div>

            <!-- Funnel Stages Bar View -->
            <div class="p-6 rounded-2xl bg-stone-900 border border-stone-800 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Lifecycle Funnel Breakdown</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    @foreach($data['stages'] ?? [] as $stageKey => $count)
                        <div class="p-3 rounded-xl bg-stone-950 border border-stone-800/80">
                            <span class="text-[10px] uppercase font-mono text-stone-400 block">{{ str_replace('_', ' ', $stageKey) }}</span>
                            <span class="text-lg font-black text-white mt-1 block">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Tab 2: Revenue Analytics -->
    @if($activeTab === 'revenue')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-emerald-400 font-mono">Total Gross Collections</span>
                    <p class="text-3xl font-black text-emerald-400 mt-1">₹{{ number_format($data['total_gross'] ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-400 font-mono">Successful Transactions</span>
                    <p class="text-3xl font-black text-white mt-1">{{ $data['transactions_count'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Avg Ticket Size</span>
                    @php
                        $avgTicket = ($data['transactions_count'] ?? 0) > 0 ? ($data['total_gross'] / $data['transactions_count']) : 0;
                    @endphp
                    <p class="text-3xl font-black text-amber-400 mt-1">₹{{ number_format($avgTicket, 2) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Gateway Breakdown -->
                <div class="p-5 rounded-2xl bg-stone-900 border border-stone-800 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Revenue By Payment Gateway</h3>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-stone-800 text-[10px] font-mono text-stone-400 uppercase">
                                <th class="pb-2">Gateway</th>
                                <th class="pb-2 text-right">Transactions</th>
                                <th class="pb-2 text-right">Amount (INR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-800/60">
                            @forelse($data['by_gateway'] ?? [] as $gw)
                                <tr>
                                    <td class="py-2.5 font-bold text-white uppercase">{{ $gw['gateway'] }}</td>
                                    <td class="py-2.5 text-right text-stone-300 font-mono">{{ $gw['transactions_count'] }}</td>
                                    <td class="py-2.5 text-right font-black text-emerald-400 font-mono">₹{{ number_format($gw['total_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-4 text-center text-stone-500">No revenue records found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Course Breakdown -->
                <div class="p-5 rounded-2xl bg-stone-900 border border-stone-800 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Revenue By Academic Program</h3>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-stone-800 text-[10px] font-mono text-stone-400 uppercase">
                                <th class="pb-2">Program / Course</th>
                                <th class="pb-2 text-right">Count</th>
                                <th class="pb-2 text-right">Amount (INR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-800/60">
                            @forelse($data['by_course'] ?? [] as $bc)
                                <tr>
                                    <td class="py-2.5 font-bold text-stone-200">{{ $bc['course_title'] }}</td>
                                    <td class="py-2.5 text-right text-stone-300 font-mono">{{ $bc['transactions_count'] }}</td>
                                    <td class="py-2.5 text-right font-black text-emerald-400 font-mono">₹{{ number_format($bc['total_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-4 text-center text-stone-500">No transactions found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Tab 3: Outstanding Dues -->
    @if($activeTab === 'dues')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-400 font-mono">Total Outstanding Ledger</span>
                    <p class="text-2xl font-black text-white mt-1">₹{{ number_format($data['total_outstanding_amount'] ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-rose-400 font-mono">Past Due (Overdue)</span>
                    <p class="text-2xl font-black text-rose-400 mt-1">₹{{ number_format($data['total_overdue_amount'] ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Unpaid Invoices Count</span>
                    <p class="text-2xl font-black text-amber-400 mt-1">{{ $data['invoices_count'] ?? 0 }}</p>
                </div>
            </div>

            <!-- Aging Brackets -->
            <div class="p-5 rounded-2xl bg-stone-900 border border-stone-800 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Aging Analysis</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($data['aging_breakdown'] ?? [] as $bKey => $bInfo)
                        <div class="p-3 rounded-xl bg-stone-950 border border-stone-800">
                            <span class="text-[10px] text-stone-400 font-mono uppercase">{{ $bInfo['label'] }}</span>
                            <span class="text-xl font-black text-white mt-1 block">{{ $bInfo['count'] }} invoices</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Invoices List Table -->
            <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden">
                <div class="p-4 border-b border-stone-800 font-mono text-xs font-bold text-stone-300">Unsettled Invoices Ledger</div>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-stone-950 text-stone-400 font-mono text-[10px] uppercase border-b border-stone-800">
                            <th class="py-2.5 px-4">Invoice #</th>
                            <th class="py-2.5 px-4">Student</th>
                            <th class="py-2.5 px-4">Program</th>
                            <th class="py-2.5 px-4">Due Date</th>
                            <th class="py-2.5 px-4 text-right">Amount (INR)</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60 text-stone-300">
                        @forelse($data['items'] ?? [] as $inv)
                            <tr>
                                <td class="py-2.5 px-4 font-mono font-bold text-amber-400">{{ $inv['invoice_number'] }}</td>
                                <td class="py-2.5 px-4">{{ $inv['student_name'] }}</td>
                                <td class="py-2.5 px-4 text-stone-400">{{ $inv['course_name'] }}</td>
                                <td class="py-2.5 px-4 font-mono">{{ $inv['due_date'] }}</td>
                                <td class="py-2.5 px-4 text-right font-black font-mono">₹{{ number_format($inv['total_amount'], 2) }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono {{ $inv['is_overdue'] ? 'bg-rose-950 text-rose-400 border border-rose-800' : 'bg-amber-950 text-amber-400 border border-amber-800' }}">
                                        {{ $inv['is_overdue'] ? 'OVERDUE' : 'PENDING' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-stone-500">All invoices are settled! Zero outstanding dues.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 4: Batch Attendance -->
    @if($activeTab === 'attendance')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Institute Attendance Rate</span>
                    <p class="text-3xl font-black text-amber-400 mt-1">{{ $data['overall_attendance_rate_pct'] ?? 0 }}%</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-400 font-mono">Total Attendance Records</span>
                    <p class="text-3xl font-black text-white mt-1">{{ $data['total_marked_records'] ?? 0 }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-stone-950 text-stone-400 font-mono text-[10px] uppercase border-b border-stone-800">
                            <th class="py-3 px-4">Batch</th>
                            <th class="py-3 px-4">Course</th>
                            <th class="py-3 px-4 text-center">Sessions</th>
                            <th class="py-3 px-4 text-center">Present</th>
                            <th class="py-3 px-4 text-center">Late</th>
                            <th class="py-3 px-4 text-center">Absent</th>
                            <th class="py-3 px-4 text-right">Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60 text-stone-300">
                        @forelse($data['batches'] ?? [] as $b)
                            <tr>
                                <td class="py-3 px-4 font-bold text-white">{{ $b['batch_name'] }}</td>
                                <td class="py-3 px-4 text-stone-400">{{ $b['course_title'] }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ $b['sessions_held'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-emerald-400">{{ $b['present_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-amber-400">{{ $b['late_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-rose-400">{{ $b['absent_count'] }}</td>
                                <td class="py-3 px-4 text-right font-black font-mono text-emerald-400">{{ $b['attendance_rate_pct'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-stone-500">No session attendance data recorded in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 5: Course Completion -->
    @if($activeTab === 'completion')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-emerald-400 font-mono">Overall Completion Rate</span>
                    <p class="text-3xl font-black text-emerald-400 mt-1">{{ $data['overall_completion_rate_pct'] ?? 0 }}%</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-400 font-mono">Total Graduated Students</span>
                    <p class="text-3xl font-black text-white mt-1">{{ $data['total_students_completed'] ?? 0 }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-stone-950 text-stone-400 font-mono text-[10px] uppercase border-b border-stone-800">
                            <th class="py-3 px-4">Program</th>
                            <th class="py-3 px-4 text-center">Enrolled</th>
                            <th class="py-3 px-4 text-center">Active</th>
                            <th class="py-3 px-4 text-center">Completed</th>
                            <th class="py-3 px-4 text-center">Dropped</th>
                            <th class="py-3 px-4 text-center">Certificates</th>
                            <th class="py-3 px-4 text-right">Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60 text-stone-300">
                        @forelse($data['courses'] ?? [] as $c)
                            <tr>
                                <td class="py-3 px-4 font-bold text-white">{{ $c['course_title'] }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ $c['total_enrolled'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-sky-400">{{ $c['active_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-emerald-400">{{ $c['completed_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-rose-400">{{ $c['dropped_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-amber-400">{{ $c['certificates_issued'] }}</td>
                                <td class="py-3 px-4 text-right font-black font-mono text-emerald-400">{{ $c['completion_rate_pct'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-stone-500">No course data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 6: Faculty Workload -->
    @if($activeTab === 'workload')
        <div class="space-y-6">
            <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-stone-950 text-stone-400 font-mono text-[10px] uppercase border-b border-stone-800">
                            <th class="py-3 px-4">Teacher / Faculty</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4 text-center">Assigned Batches</th>
                            <th class="py-3 px-4 text-center">Scheduled Sessions</th>
                            <th class="py-3 px-4 text-center">Teaching Hours</th>
                            <th class="py-3 px-4 text-right">Pending Submissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60 text-stone-300">
                        @forelse($data['teachers'] ?? [] as $t)
                            <tr>
                                <td class="py-3 px-4 font-bold text-white">{{ $t['teacher_name'] }}</td>
                                <td class="py-3 px-4 font-mono text-stone-400">{{ $t['teacher_email'] }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ $t['assigned_batches_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ $t['sessions_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-amber-400 font-bold">{{ $t['total_hours_taught'] }} hrs</td>
                                <td class="py-3 px-4 text-right font-mono text-rose-400">{{ $t['pending_grading_count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-stone-500">No active teachers registered in system.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 7: Certificate Issuance -->
    @if($activeTab === 'certification')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-400 font-mono">Total Certificates</span>
                    <p class="text-2xl font-black text-white mt-1">{{ $data['total_issued'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-emerald-400 font-mono">Valid Credentials</span>
                    <p class="text-2xl font-black text-emerald-400 mt-1">{{ $data['valid_count'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-rose-400 font-mono">Revoked</span>
                    <p class="text-2xl font-black text-rose-400 mt-1">{{ $data['revoked_count'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Reissued</span>
                    <p class="text-2xl font-black text-amber-400 mt-1">{{ $data['reissued_count'] ?? 0 }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden">
                <div class="p-4 border-b border-stone-800 font-mono text-xs font-bold text-stone-300">Recent Credentials Registry</div>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-stone-950 text-stone-400 font-mono text-[10px] uppercase border-b border-stone-800">
                            <th class="py-2.5 px-4">Certificate #</th>
                            <th class="py-2.5 px-4">Student</th>
                            <th class="py-2.5 px-4">Course</th>
                            <th class="py-2.5 px-4">Issue Date</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60 text-stone-300">
                        @forelse($data['recent'] ?? [] as $rc)
                            <tr>
                                <td class="py-2.5 px-4 font-mono font-bold text-amber-400">{{ $rc['certificate_number'] }}</td>
                                <td class="py-2.5 px-4 text-white font-medium">{{ $rc['recipient_name'] }}</td>
                                <td class="py-2.5 px-4 text-stone-400">{{ $rc['course_name'] }}</td>
                                <td class="py-2.5 px-4 font-mono">{{ $rc['issue_date'] }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase {{ $rc['status'] === 'valid' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-rose-950 text-rose-400 border border-rose-800' }}">
                                        {{ $rc['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-stone-500">No certificates issued in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 8: Event Registrations -->
    @if($activeTab === 'events')
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-stone-400 font-mono">Events Conducted</span>
                    <p class="text-2xl font-black text-white mt-1">{{ $data['total_events'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-sky-400 font-mono">Registrations</span>
                    <p class="text-2xl font-black text-sky-400 mt-1">{{ $data['total_registrations'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-emerald-400 font-mono">Checked In Attendees</span>
                    <p class="text-2xl font-black text-emerald-400 mt-1">{{ $data['total_checked_in'] ?? 0 }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 shadow-md">
                    <span class="text-[10px] uppercase font-bold text-amber-400 font-mono">Check-In Rate</span>
                    <p class="text-2xl font-black text-amber-400 mt-1">{{ $data['overall_check_in_rate_pct'] ?? 0 }}%</p>
                </div>
            </div>

            <div class="rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-stone-950 text-stone-400 font-mono text-[10px] uppercase border-b border-stone-800">
                            <th class="py-3 px-4">Event Title</th>
                            <th class="py-3 px-4">Format</th>
                            <th class="py-3 px-4 text-center">Capacity</th>
                            <th class="py-3 px-4 text-center">Registrations</th>
                            <th class="py-3 px-4 text-center">Plus Ones</th>
                            <th class="py-3 px-4 text-center">Checked In</th>
                            <th class="py-3 px-4 text-right">Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60 text-stone-300">
                        @forelse($data['events'] ?? [] as $ev)
                            <tr>
                                <td class="py-3 px-4 font-bold text-white">{{ $ev['event_title'] }}</td>
                                <td class="py-3 px-4 uppercase font-mono text-[10px] text-stone-400">{{ $ev['format'] }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ $ev['capacity'] ?? 'Unlimited' }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ $ev['registrations_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-sky-400">+{{ $ev['plus_ones_count'] }}</td>
                                <td class="py-3 px-4 text-center font-mono text-emerald-400">{{ $ev['checked_in_count'] }}</td>
                                <td class="py-3 px-4 text-right font-black font-mono text-emerald-400">{{ $ev['check_in_rate_pct'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-stone-500">No events found in this date window.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
