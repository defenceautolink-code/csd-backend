<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $quotation->subject }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 650px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            padding: 30px;
            color: #ffffff;
            text-align: center;
        }
        .email-header h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .email-header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .email-body {
            padding: 30px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 15px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 20px;
        }
        .quote-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .quote-info-grid {
            width: 100%;
            margin-bottom: 15px;
        }
        .quote-info-grid td {
            font-size: 13px;
            padding: 4px 0;
        }
        .label {
            color: #64748b;
            width: 40%;
        }
        .val {
            color: #0f172a;
            font-weight: 600;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
        }
        .items-table th {
            background: #e2e8f0;
            color: #1e293b;
            text-align: left;
            padding: 8px 10px;
            font-weight: 600;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-box {
            background: #eff6ff;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #bfdbfe;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 4px 0;
        }
        .grand-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            border-top: 1px solid #93c5fd;
            padding-top: 8px;
            margin-top: 6px;
        }
        .terms-box {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
            margin-top: 20px;
            padding: 15px;
            background: #fafafa;
            border-radius: 6px;
        }
        .email-footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .email-footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>DEFENCE AUTOLINK</h1>
            <p>Official Vehicle Quotation & Price Estimate</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="greeting">Dear {{ $quotation->customer_name }},</div>
            <p class="intro-text">
                Thank you for your interest in Defence Autolink. Please find below the detailed quotation as per your vehicle requirements. A PDF copy of the official quotation is also attached with this email for your records.
            </p>

            <!-- Quotation Card Details -->
            <div class="quote-card">
                <table class="quote-info-grid">
                    <tr>
                        <td class="label">Quotation Number:</td>
                        <td class="val">{{ $quotation->quotation_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Quotation Date:</td>
                        <td class="val">{{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d M Y') }}</td>
                    </tr>
                    @if($quotation->valid_until)
                    <tr>
                        <td class="label">Valid Until:</td>
                        <td class="val">{{ \Carbon\Carbon::parse($quotation->valid_until)->format('d M Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="label">Subject:</td>
                        <td class="val">{{ $quotation->subject }}</td>
                    </tr>
                </table>

                <!-- Items breakdown -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item / Particulars</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotation->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->item_name }}</strong>
                                @if($item->description)
                                    <br><small style="color:#64748b;">{{ $item->description }}</small>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                            <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-right">₹{{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="total-box">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>₹{{ number_format($quotation->subtotal, 2) }}</span>
                    </div>
                    @if($quotation->discount > 0)
                    <div class="total-row" style="color: #dc2626;">
                        <span>Discount:</span>
                        <span>- ₹{{ number_format($quotation->discount, 2) }}</span>
                    </div>
                    @endif
                    @if($quotation->tax > 0)
                    <div class="total-row">
                        <span>Tax / GST:</span>
                        <span>₹{{ number_format($quotation->tax, 2) }}</span>
                    </div>
                    @endif
                    <div class="grand-total-row">
                        <span>Grand Total:</span>
                        <span>₹{{ number_format($quotation->grand_total, 2) }}</span>
                    </div>
                </div>
            </div>

            @if($quotation->payment_terms || $quotation->delivery_terms || $quotation->notes)
            <div class="terms-box">
                @if($quotation->payment_terms)
                    <p><strong>Payment Terms:</strong> {{ $quotation->payment_terms }}</p>
                @endif
                @if($quotation->delivery_terms)
                    <p><strong>Delivery Terms:</strong> {{ $quotation->delivery_terms }}</p>
                @endif
                @if($quotation->notes)
                    <p><strong>Notes:</strong> {{ $quotation->notes }}</p>
                @endif
            </div>
            @endif

            <p style="font-size: 13px; color: #475569; margin-top: 25px;">
                If you have any questions or wish to proceed with the booking, please do not hesitate to contact our sales team at <strong>+91 98000 11111</strong> or reply directly to this email.
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>DEFENCE AUTOLINK</strong></p>
            <p>Main Ring Road Showroom, South Extension Part-II, New Delhi - 110049</p>
            <p>Phone: +91 98000 11111 | Email: sales@defenceautolink.com</p>
        </div>
    </div>
</body>
</html>
