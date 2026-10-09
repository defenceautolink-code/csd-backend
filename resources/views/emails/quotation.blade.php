<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $quotation->subject }}</title>
    <style>
        /* Resets & Base Styles */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
            box-sizing: border-box;
        }
        table, td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
            border-collapse: collapse !important;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Container Layout */
        .email-wrapper {
            width: 100%;
            background-color: #f1f5f9;
            padding: 20px 10px;
        }
        .email-container {
            max-width: 650px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Header */
        .email-header {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            padding: 30px 20px;
            color: #ffffff;
            text-align: center;
        }
        .email-header h1 {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #ffffff;
        }
        .email-header p {
            margin: 0;
            font-size: 13px;
            color: #e0e7ff;
            opacity: 0.95;
        }

        /* Body */
        .email-body {
            padding: 28px 24px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 22px;
        }

        /* Quote Card */
        .quote-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px 16px;
            margin-bottom: 22px;
        }
        .quote-info-table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .quote-info-table td {
            font-size: 13px;
            padding: 5px 0;
            vertical-align: top;
        }
        .label {
            color: #64748b;
            width: 38%;
            font-weight: 500;
        }
        .val {
            color: #0f172a;
            font-weight: 600;
            width: 62%;
            word-break: break-word;
        }

        /* Responsive Table Wrapper */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 14px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            table-layout: fixed;
        }
        .items-table th {
            background: #e2e8f0;
            color: #1e293b;
            text-align: left;
            padding: 10px 8px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .col-item { width: 44%; }
        .col-qty { width: 12%; text-align: center; }
        .col-price { width: 22%; text-align: right; }
        .col-total { width: 22%; text-align: right; }

        .item-title {
            color: #0f172a;
            font-weight: 600;
            font-size: 13px;
            line-height: 1.4;
            display: block;
        }
        .item-desc {
            color: #64748b;
            font-size: 11px;
            line-height: 1.35;
            margin-top: 3px;
            display: block;
        }

        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        /* Totals Box */
        .total-box {
            background: #eff6ff;
            border-radius: 6px;
            padding: 14px 16px;
            margin-top: 18px;
            border: 1px solid #bfdbfe;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            padding: 4px 0;
            color: #334155;
        }
        .grand-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            font-weight: 700;
            color: #1e3a8a;
            border-top: 1px solid #93c5fd;
            padding-top: 8px;
            margin-top: 6px;
        }

        /* Terms & Notes */
        .terms-box {
            font-size: 12px;
            color: #475569;
            line-height: 1.5;
            margin-top: 20px;
            padding: 14px 16px;
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .terms-box p {
            margin: 4px 0;
        }

        /* Footer */
        .email-footer {
            background: #f8fafc;
            padding: 20px 15px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        .email-footer p {
            margin: 4px 0;
        }

        /* Mobile Responsive Media Queries */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 5px 0 !important;
            }
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .email-header {
                padding: 20px 15px !important;
            }
            .email-header h1 {
                font-size: 19px !important;
            }
            .email-header p {
                font-size: 12px !important;
            }
            .email-body {
                padding: 16px 12px !important;
            }
            .quote-card {
                padding: 12px 10px !important;
                margin-bottom: 16px !important;
            }
            .quote-info-table td {
                font-size: 12px !important;
                padding: 4px 0 !important;
            }
            .label {
                width: 42% !important;
            }
            .val {
                width: 58% !important;
            }
            .items-table {
                font-size: 11px !important;
            }
            .items-table th {
                font-size: 10px !important;
                padding: 8px 4px !important;
            }
            .items-table td {
                padding: 8px 4px !important;
                font-size: 11px !important;
            }
            .col-item { width: 40% !important; }
            .col-qty { width: 10% !important; }
            .col-price { width: 25% !important; }
            .col-total { width: 25% !important; }
            .item-title {
                font-size: 11px !important;
            }
            .item-desc {
                font-size: 10px !important;
            }
            .total-box {
                padding: 10px 12px !important;
            }
            .total-row {
                font-size: 12px !important;
            }
            .grand-total-row {
                font-size: 14px !important;
            }
            .terms-box {
                padding: 10px 12px !important;
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
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
                    <table class="quote-info-table" role="presentation">
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

                    <!-- Items Breakdown -->
                    <div class="table-wrapper">
                        <table class="items-table" role="presentation">
                            <thead>
                                <tr>
                                    <th class="col-item">Item / Particulars</th>
                                    <th class="col-qty text-center">Qty</th>
                                    <th class="col-price text-right">Unit Price</th>
                                    <th class="col-total text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($quotation->items as $item)
                                <tr>
                                    <td class="col-item">
                                        <span class="item-title">{{ $item->item_name }}</span>
                                        @if($item->description)
                                            <span class="item-desc">{{ $item->description }}</span>
                                        @endif
                                    </td>
                                    <td class="col-qty text-center">{{ number_format($item->quantity, 0) }}</td>
                                    <td class="col-price text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="col-total text-right">₹{{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

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

                <p style="font-size: 13px; color: #475569; margin-top: 22px; line-height: 1.5;">
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
    </div>
</body>
</html>
