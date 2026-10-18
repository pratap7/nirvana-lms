<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">Class Schedule & Attendance</h1>
            <p style="color: var(--text-muted); margin: 4px 0 0 0;">View your cohort timetables, meeting links, and participation logs.</p>
        </div>

        <div class="card" style="padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Attendance Rate</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: {{ $attendancePercentage >= 80 ? '#10b981' : '#f59e0b' }};">
                    {{ (int) $attendancePercentage }}%
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); border-left: 1px solid var(--border); padding-left: 1rem;">
                {{ $attendedCount }} of {{ $totalHeld }} sessions attended
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title" style="margin-bottom: 1rem;">All Scheduled Sessions</h3>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @forelse($sessions as $session)
                @php
                    $isPast = $session->starts_at->isPast();
                    $att = $attendances->get($session->id);
                @endphp
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid var(--border); border-radius: 8px; background: {{ $isPast ? '#f8fafc' : '#ffffff' }};">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div style="background: {{ $isPast ? '#e2e8f0' : '#e0e7ff' }}; color: {{ $isPast ? '#475569' : '#3730a3' }}; padding: 0.5rem 0.75rem; border-radius: 6px; text-align: center; min-width: 50px;">
                            <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">{{ $session->starts_at->format('M') }}</div>
                            <div style="font-size: 1.15rem; font-weight: 800;">{{ $session->starts_at->format('d') }}</div>
                        </div>

                        <div>
                            <span class="badge badge-info" style="font-size: 0.7rem; margin-bottom: 2px;">{{ $session->batch?->course?->title }}</span>
                            <h4 style="margin: 0; font-size: 1rem; font-weight: 700;">{{ $session->title }}</h4>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                {{ $session->starts_at->format('D, h:i A') }} - {{ $session->ends_at->format('h:i A') }} ({{ $session->timezone }}) •
                                @if($session->is_online)
                                    Online ({{ strtoupper($session->meeting_provider->value) }})
                                @else
                                    Campus ({{ $session->room?->name ?? 'Main Hall' }})
                                @endif
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        @if($isPast)
                            @if($att)
                                <span class="badge {{ $att->status === 'present' ? 'badge-success' : ($att->status === 'late' ? 'badge-warning' : 'badge-danger') }}">
                                    {{ ucfirst($att->status) }}
                                </span>
                            @else
                                <span class="badge badge-info">Completed</span>
                            @endif
                        @else
                            @if($session->meeting_link)
                                <a href="{{ $session->meeting_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-success" style="padding: 4px 10px; font-size: 0.85rem;">
                                    Join Class 🎥
                                </a>
                            @else
                                <span class="badge badge-info">Upcoming</span>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No sessions scheduled.</p>
            @endforelse
        </div>
    </div>
</div>
