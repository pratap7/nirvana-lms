<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1a202c;
            line-height: 1.5;
            margin: 0;
            padding: 40px;
            background-color: #f7fafc;
        }
        .invoice-card {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 40px;
            border: 1px solid #e2e8f0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 24px;
            margin-bottom: 24px;
        }
        .institute-brand h1 {
            margin: 0 0 4px 0;
            font-size: 24px;
            color: #2d3748;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h2 {
            margin: 0 0 4px 0;
            font-size: 20px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .meta-box h3 {
            font-size: 13px;
            text-transform: uppercase;
            color: #718096;
            margin: 0 0 8px 0;
            letter-spacing: 0.05em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        th {
            background-color: #edf2f7;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 12px;
            text-align: left;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .text-right {
            text-align: right;
        }
        .totals-table {
            width: 320px;
            margin-left: auto;
            border: none;
        }
        .totals-table td {
            padding: 6px 12px;
            border: none;
        }
        .total-row {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #2d3748 !important;
            color: #1a202c;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-paid { background-color: #c6f6d5; color: #22543d; }
        .badge-pending { background-color: #feebc8; color: #7b341e; }
        .badge-partial { background-color: #e2e8f0; color: #2d3748; }
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #718096;
            text-align: center;
        }
        @media print {
            body { background: transparent; padding: 0; }
            .invoice-card { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-card">
        <div class="header">
            <div class="institute-brand">
                <h1>{{ $settings->get('institute.name', 'Pramana Institute') }}</h1>
                <div style="font-size: 13px; color: #718096;">
                    GSTIN: {{ $settings->get('tax.gstin', '07AAAAA0000A1Z5') }}<br>
                    State: {{ $settings->get('tax.institute_state', 'DL') }} | Contact: {{ $settings->get('institute.email', 'billing@pramana.edu') }}
                </div>
            </div>
            <div class="invoice-title">
                <h2>Tax Invoice</h2>
                <div style="font-weight: bold; color: #2d3748;">{{ $invoice->invoice_number }}</div>
                <div style="margin-top: 6px;">
                    <span class="badge badge-{{ $invoice->status->value === 'paid' ? 'paid' : ($invoice->status->value === 'pending' ? 'pending' : 'partial') }}">
                        {{ $invoice->status->getLabel() }}
                    </span>
                </div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <h3>Billed To</h3>
                <strong>{{ $invoice->billing_name }}</strong><br>
                {{ $invoice->billing_email }}<br>
                @if($invoice->billing_gstin)
                    Student GSTIN: {{ $invoice->billing_gstin }}<br>
                @endif
                @if($invoice->billing_address)
                    <div style="font-size: 13px; color: #4a5568;">{{ $invoice->billing_address }}</div>
                @endif
            </div>
            <div class="meta-box" style="text-align: right;">
                <h3>Invoice Details</h3>
                Date of Issue: {{ $invoice->issued_at->format('d M Y') }}<br>
                Due Date: {{ $invoice->due_date->format('d M Y') }}<br>
                Financial Year: {{ $invoice->financial_year }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>SAC</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Tax (%)</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->sac_code }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ $item->getUnitPrice()->getFormatted() }}</td>
                        <td class="text-right">{{ $item->tax_rate }}%</td>
                        <td class="text-right">{{ $item->getTotal()->getFormatted() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">{{ $invoice->getSubtotal()->getFormatted() }}</td>
            </tr>
            @if($invoice->discount_amount > 0)
                <tr style="color: #c53030;">
                    <td>Discount:</td>
                    <td class="text-right">-{{ $invoice->getDiscount()->getFormatted() }}</td>
                </tr>
            @endif
            @if(is_array($invoice->tax_breakup))
                @if(($invoice->tax_breakup['type'] ?? '') === 'intra_state')
                    <tr>
                        <td>CGST ({{ $invoice->tax_breakup['cgst_rate'] }}%):</td>
                        <td class="text-right">{{ \App\Support\Money::fromMinor($invoice->tax_breakup['cgst_amount'], $invoice->currency)->getFormatted() }}</td>
                    </tr>
                    <tr>
                        <td>SGST ({{ $invoice->tax_breakup['sgst_rate'] }}%):</td>
                        <td class="text-right">{{ \App\Support\Money::fromMinor($invoice->tax_breakup['sgst_amount'], $invoice->currency)->getFormatted() }}</td>
                    </tr>
                @else
                    <tr>
                        <td>IGST ({{ $invoice->tax_breakup['igst_rate'] ?? 18 }}%):</td>
                        <td class="text-right">{{ \App\Support\Money::fromMinor($invoice->tax_breakup['igst_amount'] ?? $invoice->tax_amount, $invoice->currency)->getFormatted() }}</td>
                    </tr>
                @endif
            @else
                <tr>
                    <td>GST (18%):</td>
                    <td class="text-right">{{ $invoice->getTax()->getFormatted() }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td>Total Invoice Value:</td>
                <td class="text-right">{{ $invoice->getTotal()->getFormatted() }}</td>
            </tr>
            <tr>
                <td>Total Settled / Paid:</td>
                <td class="text-right" style="color: #2f855a; font-weight: bold;">{{ $invoice->getPaidAmount()->getFormatted() }}</td>
            </tr>
            <tr>
                <td>Balance Due:</td>
                <td class="text-right" style="color: #c53030; font-weight: bold;">{{ $invoice->getRemainingBalance()->getFormatted() }}</td>
            </tr>
        </table>

        @if($invoice->payments->count() > 0)
            <div style="margin-top: 24px;">
                <h3 style="font-size: 13px; text-transform: uppercase; color: #718096; margin-bottom: 8px;">Settled Payments</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference / Transaction ID</th>
                            <th>Status</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $payment->method->getLabel() }}</td>
                                <td>{{ $payment->gateway_transaction_id ?? $payment->reference_number ?? 'N/A' }}</td>
                                <td><span class="badge badge-{{ $payment->status->value === 'verified' ? 'paid' : 'pending' }}">{{ $payment->status->getLabel() }}</span></td>
                                <td class="text-right">{{ $payment->getAmount()->getFormatted() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="footer">
            This is a computer-generated tax invoice under the GST Law of India. No physical signature is required.
        </div>
    </div>
</body>
</html>
