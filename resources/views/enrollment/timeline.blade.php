<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Status Timeline - {{ $enrollment->course?->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0f172a;
            color: #f8fafc;
            margin: 0;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 680px;
            margin: 0 auto;
            background: #1e293b;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
            border: 1px solid #334155;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-active { background: #166534; color: #bbf7d0; }
        .badge-approved { background: #0369a1; color: #bae6fd; }
        .badge-waitlisted { background: #475569; color: #e2e8f0; }
        .badge-requested { background: #1e40af; color: #dbeafe; }
        .badge-cancelled { background: #991b1b; color: #fecaca; }
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
            border-left: 2px solid #475569;
        }
        .timeline-item:last-child {
            border-left: 2px transparent;
        }
        .timeline-dot {
            position: absolute;
            left: -0.45rem;
            top: 0.25rem;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            background: #38bdf8;
        }
        .time {
            font-size: 0.8rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="font-size: 1.5rem; margin-top: 0;">Enrollment Status Timeline</h1>
        <p style="color: #94a3b8;">
            Course: <strong>{{ $enrollment->course?->name }}</strong><br>
            Batch: <strong>{{ $enrollment->batch?->name }}</strong>
        </p>
        <div style="margin-bottom: 2rem;">
            Current Status: 
            <span class="badge badge-{{ $enrollment->status->value }}">
                {{ $enrollment->status->label() }}
            </span>
            @if ($enrollment->seat_hold_expires_at)
                <p style="font-size: 0.9rem; color: #f59e0b; margin-top: 0.5rem;">
                    ⏰ Seat Held Until: {{ $enrollment->seat_hold_expires_at->toDayDateTimeString() }}
                </p>
            @endif
            @if ($enrollment->waitlist_position)
                <p style="font-size: 0.9rem; color: #94a3b8; margin-top: 0.5rem;">
                    📋 Position in Waitlist: #{{ $enrollment->waitlist_position }}
                </p>
            @endif
        </div>

        <h2 style="font-size: 1.15rem; border-bottom: 1px solid #334155; padding-bottom: 0.5rem;">Audit History</h2>
        <div style="margin-top: 1.5rem;">
            @foreach ($timeline as $item)
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="time">{{ $item->created_at?->format('M d, Y h:i A') }}</div>
                    <div style="font-weight: 600; margin-top: 0.25rem;">
                        {{ $item->from_status ? ucfirst(str_replace('_', ' ', $item->from_status)) . ' → ' : '' }}
                        {{ ucfirst(str_replace('_', ' ', $item->to_status)) }}
                    </div>
                    @if ($item->reason)
                        <div style="font-size: 0.9rem; color: #cbd5e1; margin-top: 0.25rem;">
                            {{ $item->reason }}
                        </div>
                    @endif
                    @if ($item->changer)
                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
                            Action by: {{ $item->changer->name }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
