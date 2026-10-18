<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fafaf9;
            color: #1c1917;
            -webkit-print-color-adjust: exact;
        }
        .cert-container {
            width: 100%;
            height: 100%;
            padding: 40px;
            box-sizing: border-box;
            position: relative;
        }
        .cert-border {
            border: 8px double #78350f;
            padding: 30px;
            height: calc(100% - 80px);
            box-sizing: border-box;
            background-color: #ffffff;
            position: relative;
            text-align: center;
        }
        .institute-header {
            font-size: 24px;
            font-weight: bold;
            color: #78350f;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 20px;
        }
        .institute-sub {
            font-size: 11px;
            letter-spacing: 3px;
            color: #78716c;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .cert-title {
            font-size: 16px;
            font-style: italic;
            color: #57534e;
            margin-top: 35px;
        }
        .recipient-name {
            font-size: 38px;
            font-weight: bold;
            color: #0c0a09;
            margin-top: 15px;
            font-family: 'Georgia', serif;
            border-bottom: 2px solid #e7e5e4;
            display: inline-block;
            padding-bottom: 8px;
            min-width: 450px;
        }
        .conferment-text {
            font-size: 13px;
            color: #44403c;
            margin-top: 20px;
            line-height: 1.6;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
        }
        .course-title {
            font-size: 26px;
            font-weight: bold;
            color: #451a03;
            margin-top: 12px;
        }
        .footer-table {
            width: 100%;
            position: absolute;
            bottom: 40px;
            left: 30px;
            right: 30px;
        }
        .meta-text {
            font-size: 10px;
            color: #78716c;
            font-family: 'Courier', monospace;
            text-align: left;
        }
        .meta-text strong {
            color: #1c1917;
        }
        .signature-box {
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #78716c;
            width: 180px;
            margin: 0 auto;
            padding-top: 5px;
            font-size: 11px;
            font-weight: bold;
            color: #1c1917;
        }
        .qr-box {
            text-align: right;
            padding-right: 40px;
        }
        .watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 90px;
            color: rgba(120, 53, 15, 0.04);
            font-family: 'Georgia', serif;
            font-weight: bold;
            pointer-events: none;
            letter-spacing: 10px;
        }
    </style>
</head>
<body>
    <div class="cert-container">
        <div class="cert-border">
            <div class="watermark">PRAMANA</div>

            <div class="institute-header">
                {{ $template->branding['institute_name'] ?? 'Pramana Institute of Yoga &amp; Vedanta' }}
            </div>
            <div class="institute-sub">
                Classical Gurukula of Vedic Sciences &bull; Rishikesh, Himalayas
            </div>

            <div class="cert-title">
                This certificate of formal conferment is awarded to
            </div>

            <div class="recipient-name">
                {{ $certificate->user?->full_name }}
            </div>

            <div class="conferment-text">
                having satisfactorily completed all prescribed sadhana requirements, master-level modules,
                attendance criteria, and practical examinations for
            </div>

            <div class="course-title">
                {{ $certificate->getTargetTitle() }}
            </div>

            <table class="footer-table">
                <tr>
                    <td class="meta-text" style="width: 35%;">
                        <div><strong>CERTIFICATE NO:</strong> {{ $certificate->certificate_number }}</div>
                        <div><strong>DATE OF CONFERMENT:</strong> {{ $certificate->issue_date->format('F d, Y') }}</div>
                        <div><strong>VERIFICATION ID:</strong> {{ $certificate->public_token }}</div>
                    </td>
                    <td class="signature-box" style="width: 40%;">
                        <div style="font-family: 'Georgia', serif; font-size: 16px; color: #78350f; margin-bottom: 5px;">
                            Acharya Satyendra
                        </div>
                        <div class="signature-line">
                            Director &amp; Head of Faculty
                        </div>
                    </td>
                    <td class="qr-box" style="width: 25%;">
                        @if($certificate->qr_code_svg)
                            <div style="width: 75px; height: 75px; display: inline-block;">
                                {!! $certificate->qr_code_svg !!}
                            </div>
                        @else
                            <div style="font-size: 9px; font-family: monospace;">[VERIFIED]</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
