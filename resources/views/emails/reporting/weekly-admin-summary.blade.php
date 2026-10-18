<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pramana LMS - Weekly Executive Summary</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0c0a09; color: #f5f5f4; margin: 0; padding: 24px; }
        .card { background-color: #1c1917; border: 1px solid #292524; border-radius: 12px; padding: 24px; max-width: 650px; margin: 0 auto; }
        .header { border-bottom: 1px solid #292524; padding-bottom: 16px; margin-bottom: 20px; }
        .title { color: #f59e0b; font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0; }
        .subtitle { color: #a8a29e; font-size: 13px; margin-top: 4px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .metric-box { background-color: #292524; border-radius: 8px; padding: 12px 16px; }
        .metric-label { font-size: 11px; text-transform: uppercase; color: #a8a29e; font-weight: 600; }
        .metric-value { font-size: 20px; font-weight: 800; color: #ffffff; margin-top: 4px; }
        .footer { font-size: 11px; color: #78716c; text-align: center; border-top: 1px solid #292524; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 class="title">Pramana Institute • Executive Weekly Summary</h1>
            <p class="subtitle">Reporting Window: {{ $summaryData['week_range'] }}</p>
        </div>

        <table width="100%" cellpadding="6" cellspacing="0" style="margin-bottom: 20px;">
            <tr>
                <td width="50%" style="background-color: #292524; border-radius: 8px; padding: 12px;">
                    <div class="metric-label">Gross Revenue</div>
                    <div class="metric-value" style="color: #10b981;">₹{{ number_format($summaryData['total_revenue'], 2) }}</div>
                    <div style="font-size: 11px; color: #a8a29e; margin-top: 2px;">{{ $summaryData['transactions_count'] }} transactions</div>
                </td>
                <td width="50%" style="background-color: #292524; border-radius: 8px; padding: 12px;">
                    <div class="metric-label">Outstanding Dues</div>
                    <div class="metric-value" style="color: #f43f5e;">₹{{ number_format($summaryData['outstanding_dues'], 2) }}</div>
                    <div style="font-size: 11px; color: #a8a29e; margin-top: 2px;">Critical Overdue: ₹{{ number_format($summaryData['overdue_dues'], 2) }}</div>
                </td>
            </tr>
            <tr>
                <td width="50%" style="background-color: #292524; border-radius: 8px; padding: 12px;">
                    <div class="metric-label">New Enrolled Students</div>
                    <div class="metric-value" style="color: #38bdf8;">{{ $summaryData['new_enrollments'] }}</div>
                </td>
                <td width="50%" style="background-color: #292524; border-radius: 8px; padding: 12px;">
                    <div class="metric-label">Overall Attendance Rate</div>
                    <div class="metric-value" style="color: #fbbf24;">{{ $summaryData['average_attendance_pct'] }}%</div>
                </td>
            </tr>
            <tr>
                <td width="50%" style="background-color: #292524; border-radius: 8px; padding: 12px;">
                    <div class="metric-label">Certificates Issued</div>
                    <div class="metric-value">{{ $summaryData['certificates_issued'] }}</div>
                </td>
                <td width="50%" style="background-color: #292524; border-radius: 8px; padding: 12px;">
                    <div class="metric-label">Event Registrations</div>
                    <div class="metric-value">{{ $summaryData['event_registrations'] }}</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            Generated automatically by Pramana LMS Section H Analytics Engine. Secure & confidential.
        </div>
    </div>
</body>
</html>
