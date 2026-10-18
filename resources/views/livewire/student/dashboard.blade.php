<div>
    <!-- Welcome Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.875rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0;">
                Namaste, {{ $student->first_name }} 🙏
            </h1>
            <p style="color: var(--text-muted); margin: 0; font-size: 1rem;">
                Continue your learning path, live sadhana sessions, and practice reviews.
            </p>
        </div>
        @if($totalOutstandingMinor > 0)
            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; padding: 0.75rem 1rem; border-radius: 8px;">
                <div style="font-size: 0.8rem; font-weight: 600; color: #b45309; text-transform: uppercase;">Outstanding Balance</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #92400e;">
                    {{ \App\Support\Money::fromMinor($totalOutstandingMinor, 'INR')->getFormatted() }}
                </div>
                <a href="/student/payments" class="btn btn-primary" style="margin-top: 6px; padding: 4px 10px; font-size: 0.8rem;">Settle Invoices</a>
            </div>
        @endif
    </div>

    <!-- Main Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        <!-- Left Column: Courses & Sessions -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Enrolled Courses -->
            <section aria-labelledby="enrolled-courses-heading">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 id="enrolled-courses-heading" style="font-size: 1.25rem; font-weight: 700; margin: 0;">Enrolled Programs</h2>
                    <span style="font-size: 0.875rem; color: var(--text-muted);">{{ count($enrolledCourses) }} Active</span>
                </div>

                @if(count($enrolledCourses) > 0)
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach($enrolledCourses as $item)
                            <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 1.25rem; align-items: center;">
                                    <!-- Progress Ring -->
                                    <div style="position: relative; width: 64px; height: 64px;">
                                        <svg width="64" height="64" class="progress-ring">
                                            <circle stroke="#e2e8f0" stroke-width="6" fill="transparent" r="26" cx="32" cy="32"/>
                                            <circle stroke="#4f46e5" stroke-width="6" stroke-dasharray="{{ 2 * 3.14159 * 26 }}"
                                                    stroke-dashoffset="{{ (2 * 3.14159 * 26) * (1 - ($item['overall_percentage'] / 100)) }}"
                                                    stroke-linecap="round" fill="transparent" r="26" cx="32" cy="32" class="progress-ring-circle"/>
                                        </svg>
                                        <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #4f46e5;">
                                            {{ (int) $item['overall_percentage'] }}%
                                        </div>
                                    </div>

                                    <div>
                                        <h3 style="margin: 0 0 4px 0; font-size: 1.1rem; font-weight: 700;">
                                            {{ $item['course']->title ?? $item['course']->name }}
                                        </h3>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            Batch: <strong>{{ $item['batch']?->name ?? 'Self-Paced Cohort' }}</strong> •
                                            {{ $item['lessons_completed'] }} of {{ $item['total_lessons'] }} lessons completed
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 0.75rem;">
                                    <a href="/student/courses/{{ $item['course']->slug }}?batch={{ $item['batch']?->ulid }}" class="btn btn-primary">
                                        Resume Course →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
                        <p style="color: var(--text-muted); margin-bottom: 1rem;">You are not currently enrolled in any active course cohorts.</p>
                        <a href="/courses" class="btn btn-primary">Explore Course Catalog</a>
                    </div>
                @endif
            </section>

            <!-- Next Upcoming Sessions -->
            <section aria-labelledby="upcoming-sessions-heading">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 id="upcoming-sessions-heading" style="font-size: 1.25rem; font-weight: 700; margin: 0;">Next Live Sessions</h2>
                    <a href="/student/schedule" style="font-size: 0.875rem; color: var(--primary); text-decoration: none; font-weight: 600;">Full Calendar →</a>
                </div>

                @if($nextSessions->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach($nextSessions as $session)
                            <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 1rem; align-items: center;">
                                    <div style="background: var(--primary-light); color: var(--primary); padding: 0.75rem; border-radius: 8px; text-align: center; min-width: 50px;">
                                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">{{ $session->starts_at->format('M') }}</div>
                                        <div style="font-size: 1.25rem; font-weight: 800;">{{ $session->starts_at->format('d') }}</div>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0 0 2px 0; font-size: 1rem; font-weight: 700;">{{ $session->title }}</h4>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            {{ $session->starts_at->format('h:i A') }} - {{ $session->ends_at->format('h:i A') }} •
                                            {{ $session->is_online ? 'Live Online Session' : 'Campus Offline' }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    @if($session->meeting_link)
                                        <a href="{{ $session->meeting_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-success">
                                            Join Meeting 🎥
                                        </a>
                                    @else
                                        <span class="badge badge-info">Scheduled</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="card" style="padding: 1.5rem; text-align: center; color: var(--text-muted);">
                        No upcoming sessions scheduled for this week.
                    </div>
                @endif
            </section>
        </div>

        <!-- Right Column: Due Assignments, Announcements & Quick Links -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Due Assignments -->
            <section class="card" aria-labelledby="due-assignments-heading">
                <div class="card-header">
                    <h3 id="due-assignments-heading" class="card-title">Due Assignments</h3>
                    <a href="/student/assignments" style="font-size: 0.8rem; color: var(--primary); font-weight: 600; text-decoration: none;">View All</a>
                </div>

                @if($dueAssignments->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($dueAssignments as $assignment)
                            @php
                                $submission = $assignment->submissions->first();
                            @endphp
                            <div style="border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <strong style="font-size: 0.9rem;">{{ $assignment->title }}</strong>
                                    @if($submission)
                                        <span class="badge badge-success">{{ ucfirst($submission->status) }}</span>
                                    @else
                                        <span class="badge badge-warning">Due {{ $assignment->due_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                                    {{ $assignment->course?->title }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">No pending assignments due.</p>
                @endif
            </section>

            <!-- Announcements -->
            <section class="card" aria-labelledby="announcements-heading">
                <div class="card-header">
                    <h3 id="announcements-heading" class="card-title">Announcements</h3>
                </div>

                @if($announcements->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach($announcements as $notice)
                            <div style="border-left: 3px solid #6366f1; padding-left: 0.75rem;">
                                <div style="font-weight: 700; font-size: 0.9rem;">{{ $notice->title }}</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                    {{ Str::limit($notice->content, 120) }}
                                </div>
                                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px;">
                                    {{ $notice->published_at ? $notice->published_at->format('d M Y') : 'Recent' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">No announcements at this time.</p>
                @endif
            </section>

            <!-- Quick Links -->
            <section class="card" aria-labelledby="quick-links-heading">
                <h3 id="quick-links-heading" class="card-title" style="margin-bottom: 0.75rem;">Quick Links</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="/student/schedule" class="btn btn-secondary" style="justify-content: flex-start;">📅 Attendance & Calendar</a>
                    <a href="/student/assignments" class="btn btn-secondary" style="justify-content: flex-start;">📝 Assignment Submissions</a>
                    <a href="/student/quizzes" class="btn btn-secondary" style="justify-content: flex-start;">⏱️ Practice Quizzes</a>
                    <a href="/student/payments" class="btn btn-secondary" style="justify-content: flex-start;">💳 Payment Receipts & Installments</a>
                    <a href="/student/profile" class="btn btn-secondary" style="justify-content: flex-start;">⚙️ Emergency Contacts & Privacy</a>
                </div>
            </section>
        </div>
    </div>
</div>
