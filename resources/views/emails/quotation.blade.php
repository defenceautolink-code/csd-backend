@php
    $firstItem = $quotation->items->first();
    
    // 1. CAR and MODEL
    $carName = '';
    $modelSpec = '';
    if ($firstItem && str_contains($firstItem->item_name, '–')) {
        $parts = explode('–', $firstItem->item_name);
        $carName = trim($parts[0] ?? '');
        $modelSpec = trim($parts[1] ?? '');
    } elseif ($firstItem && str_contains($firstItem->item_name, '-')) {
        $parts = explode('-', $firstItem->item_name);
        $carName = trim($parts[0] ?? '');
        $modelSpec = trim($parts[1] ?? '');
    } elseif ($quotation->subject) {
        $cleanSubj = trim(preg_replace('/^Official Vehicle Quotation\s*[–-]?\s*/i', '', $quotation->subject));
        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $cleanSubj, $matches)) {
            $carName = trim($matches[1]);
            $modelSpec = trim($matches[2]);
        } else {
            $carName = $cleanSubj;
            $modelSpec = 'STANDARD';
        }
    }
    if (empty($carName)) {
        $carName = 'MARUTI SUZUKI BREZZA';
    }
    if (empty($modelSpec)) {
        $modelSpec = 'ZXI DUAL TONE';
    }

    // 2. FUEL / VARIANT
    $variantFuel = 'PETROL';
    if ($firstItem && $firstItem->description && stripos($firstItem->description, 'Fuel / Variant Type:') !== false) {
        $variantFuel = trim(preg_replace('/.*Fuel \/ Variant Type:\s*/i', '', $firstItem->description)) ?: 'PETROL';
    }

    // 3. PARAMETER VALUES
    $csdPrice = ($firstItem && $firstItem->unit_price > 0) ? number_format($firstItem->unit_price, 0) : '';
    $msReward = ($firstItem && $firstItem->discount > 0) ? number_format($firstItem->discount, 0) : ($quotation->discount > 0 ? number_format($quotation->discount, 0) : 'N.A');

    $findVal = function($pattern) use ($quotation) {
        $it = $quotation->items->first(function($item) use ($pattern) {
            return stripos($item->item_name ?? '', $pattern) !== false;
        });
        if (!$it || !$it->unit_price || $it->unit_price <= 0) {
            return 'N.A';
        }
        return number_format($it->unit_price, 0);
    };

    $gjRto = $findVal('Gujarat State RTO');
    if ($gjRto === 'N.A') $gjRto = $findVal('GJ RTO');

    $bhRto = $findVal('BH Series');
    if ($bhRto === 'N.A') $bhRto = $findVal('BH RTO');

    $crtm = $findVal('CRTM');
    $insurance = $findVal('Insurance');
    $accessories = $findVal('Accessories');
    $warranty = $findVal('Warranty');
    $diffAmtCash = $findVal('Difference');
    if ($diffAmtCash === 'N.A') $diffAmtCash = $findVal('Cash Adjustment');

    $displayDate = \Carbon\Carbon::parse($quotation->quotation_date ?? now())->format('d.m.y');
    $clientName = strtoupper($quotation->customer_name ?? '');
    $grandTotal = number_format($quotation->grand_total, 0);

    $executiveName = $quotation->creator->name ?? 'Admin User';
    $executivePhone = $quotation->creator->phone ?? '98000 11111';
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Official Quotation {{ $quotation->quotation_number }} - Defence Autolink</title>
    <style>
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
            box-sizing: border-box;
        }
        table, td {
            border-collapse: collapse !important;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f1f5f9;
            padding: 24px 12px;
        }
        .email-container {
            max-width: 660px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }
        .email-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);
            padding: 26px 24px;
            color: #ffffff;
            text-align: center;
        }
        .logo-badge {
            display: inline-block;
            background: #ffffff;
            color: #1e3a8a;
            font-weight: 900;
            font-size: 18px;
            padding: 6px 14px;
            border-radius: 8px;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .header-title {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
        }
        .header-subtitle {
            margin: 0;
            font-size: 12.5px;
            color: #cbd5e1;
            letter-spacing: 0.3px;
        }
        .email-body {
            padding: 26px 24px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
        }

        /* Vehicle Highlight Banner */
        .vehicle-highlight-card {
            background: #f8fafc;
            border: 1.5px solid #2563eb;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 22px;
        }
        .vh-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #2563eb;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .vh-car-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .vh-model-spec {
            font-size: 13.5px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 12px;
        }
        .vh-meta-table {
            width: 100%;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 10px;
        }
        .vh-meta-td {
            font-size: 12.5px;
            color: #64748b;
            padding: 3px 0;
        }
        .vh-meta-val {
            font-weight: 700;
            color: #0f172a;
        }

        /* Dealership Card Table (1:1 replica of Defence Autolink Quotation Card) */
        .quote-card-container {
            border: 2px solid #334155;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 22px;
            background: #ffffff;
        }
        .card-top-bar {
            background: #f1f5f9;
            padding: 12px 16px;
            border-bottom: 2px solid #334155;
        }
        .top-table {
            width: 100%;
        }
        .top-left {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .top-badge {
            text-align: center;
        }
        .badge-box {
            display: inline-block;
            border: 1.5px solid #0f172a;
            color: #b91c1c;
            font-weight: 800;
            font-size: 11.5px;
            padding: 2px 14px;
            letter-spacing: 1px;
        }
        .top-right {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        .breakdown-table th {
            background-color: #94a3b8;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            padding: 9px 12px;
            border: 1px solid #334155;
            text-transform: uppercase;
        }
        .breakdown-table td {
            padding: 8px 12px;
            font-size: 12px;
            border: 1px solid #334155;
            font-weight: 600;
            color: #0f172a;
        }
        .col-left {
            width: 55%;
            text-align: left;
        }
        .col-right {
            width: 45%;
            text-align: center;
        }
        .row-total-bar td {
            background-color: #fef3c7;
            padding: 11px 12px;
        }
        .total-title {
            color: #92400e;
            font-weight: 800;
            font-size: 13px;
        }
        .total-amount {
            color: #b91c1c;
            font-weight: 900;
            font-size: 15px;
            text-align: center;
        }

        /* Attached PDF Callout Box */
        .pdf-alert-box {
            background-color: #ecfdf5;
            border: 1.5px dashed #059669;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 22px;
        }
        .pdf-alert-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 4px;
        }
        .pdf-alert-desc {
            font-size: 12px;
            color: #047857;
            line-height: 1.45;
            margin: 0;
        }

        /* Executive Contact Box */
        .exec-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .exec-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .exec-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .exec-phone {
            font-size: 13px;
            color: #2563eb;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Email Footer */
        .email-footer {
            background-color: #0f172a;
            padding: 22px 20px;
            color: #94a3b8;
            font-size: 12px;
            text-align: center;
            line-height: 1.5;
        }
        .footer-company {
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .footer-address {
            color: #cbd5e1;
            font-size: 11.5px;
            margin-bottom: 6px;
        }
        .footer-disclaimer {
            font-size: 10px;
            color: #64748b;
            margin-top: 10px;
            border-top: 1px solid #1e293b;
            padding-top: 10px;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 8px 4px !important;
            }
            .email-header {
                padding: 20px 14px !important;
            }
            .email-body {
                padding: 18px 14px !important;
            }
            .vh-car-name {
                font-size: 16px !important;
            }
            .breakdown-table th, .breakdown-table td {
                padding: 7px 8px !important;
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header with Defence Autolink Branding -->
            <div class="email-header">
                <div class="logo-badge">DA &bull; DEFENCE AUTOLINK</div>
                <h1 class="header-title">DEFENCE AUTOLINK</h1>
                <p class="header-subtitle">Official Vehicle Quotation & Dealership Price Estimate</p>
            </div>

            <!-- Body Content -->
            <div class="email-body">
                <div class="greeting">Dear {{ $quotation->customer_name }},</div>
                <p class="intro-text">
                    Greetings from <strong>Defence Autolink</strong>! Thank you for inquiring with us. We are pleased to provide you with the official vehicle quotation for your requested car: <strong>{{ $carName }} ({{ $modelSpec }})</strong>.
                    Please find the complete quotation card breakdown below and download the attached official PDF for your records, bank finance, and booking procedures.
                </p>

                <!-- Vehicle Highlight Snapshot Card -->
                <div class="vehicle-highlight-card">
                    <div class="vh-title">SELECTED VEHICLE DETAILS</div>
                    <div class="vh-car-name">{{ strtoupper($carName) }}</div>
                    <div class="vh-model-spec">Model & Variant: <strong>{{ strtoupper($modelSpec) }}</strong> &bull; Fuel: <strong>{{ strtoupper($variantFuel) }}</strong></div>
                    
                    <table class="vh-meta-table">
                        <tr>
                            <td class="vh-meta-td" style="width: 50%;">
                                Quotation Ref: <span class="vh-meta-val">{{ $quotation->quotation_number }}</span>
                            </td>
                            <td class="vh-meta-td" style="width: 50%; text-align: right;">
                                Date: <span class="vh-meta-val">{{ $displayDate }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="vh-meta-td" style="width: 50%;">
                                Dealership: <span class="vh-meta-val">Defence Autolink</span>
                            </td>
                            <td class="vh-meta-td" style="width: 50%; text-align: right;">
                                Net Estimate: <span class="vh-meta-val" style="color: #b91c1c; font-size: 13.5px;">₹ {{ $grandTotal }}/-</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Official Defence Autolink Card Layout Table -->
                <div class="quote-card-container">
                    <div class="card-top-bar">
                        <table class="top-table">
                            <tr>
                                <td class="top-left">DATE : {{ $displayDate }}</td>
                                <td class="top-badge">
                                    <span class="badge-box">QUOTATION</span>
                                </td>
                                <td class="top-right">
                                    CLIENT: <span style="color: #2563eb;">{{ $clientName }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th class="col-left">CAR: {{ strtoupper($carName) }}</th>
                                <th class="col-right">MODEL: {{ strtoupper($modelSpec) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>CSD PRICE</td>
                                <td class="col-right" style="font-weight: 700;">{{ $csdPrice ?: 'N.A' }}</td>
                            </tr>
                            <tr>
                                <td>GJ RTO</td>
                                <td class="col-right">{{ $gjRto }}</td>
                            </tr>
                            <tr>
                                <td>BH RTO * APPOROX</td>
                                <td class="col-right">{{ $bhRto }}</td>
                            </tr>
                            <tr>
                                <td>CRTM</td>
                                <td class="col-right">{{ $crtm }}</td>
                            </tr>
                            <tr>
                                <td>INSURANCE</td>
                                <td class="col-right">{{ $insurance }}</td>
                            </tr>
                            <tr>
                                <td>ACCESSORIES</td>
                                <td class="col-right" style="font-weight: 700;">{{ $accessories }}</td>
                            </tr>
                            <tr>
                                <td>WARRANTY</td>
                                <td class="col-right">{{ $warranty }}</td>
                            </tr>
                            <tr>
                                <td>M.S REWORD</td>
                                <td class="col-right">{{ $msReward }}</td>
                            </tr>
                            <tr>
                                <td>DIFFERENCE AMT CASH</td>
                                <td class="col-right">{{ $diffAmtCash }}</td>
                            </tr>
                            <tr class="row-total-bar">
                                <td class="total-title">ESTIMATED ON-ROAD PRICE</td>
                                <td class="total-amount">₹ {{ $grandTotal }}/-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Attached PDF Notice Callout -->
                <div class="pdf-alert-box">
                    <div class="pdf-alert-title">&#128206; Official Dealership PDF Card Attached</div>
                    <p class="pdf-alert-desc">
                        A signed and authorized copy of this quotation (<strong>Quotation-{{ $quotation->quotation_number }}.pdf</strong>) has been generated and attached to this email. You can directly view, download, or print it.
                    </p>
                </div>

                @if(!empty($customMessage))
                <div style="background: #f8fafc; border-left: 4px solid #2563eb; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #334155;">
                    <strong>Message from Sales Executive:</strong><br>
                    {{ $customMessage }}
                </div>
                @endif

                <!-- Executive / Contact Person -->
                <div class="exec-box">
                    <div class="exec-title">Sales Executive & Assistance</div>
                    <div class="exec-name">{{ $executiveName }}</div>
                    <div class="exec-phone">Phone / WhatsApp: +91 {{ $executivePhone }}</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                        Authorized Multi-Brand Dealership Signatory &bull; Defence Autolink
                    </div>
                </div>

                <p style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 0;">
                    Should you require any customization, loan assistance, or test-drive booking, please feel free to reach out to our executive or reply directly to this email.
                </p>
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <div class="footer-company">DEFENCE AUTOLINK</div>
                <div class="footer-address">
                    D-601, S G Business Hub, S G Highway, Ahmedabad, Gujarat - 382470
                </div>
                <div>Contact: +91 {{ $executivePhone }} &bull; Email: sales@defenceautolink.com</div>
                <div class="footer-disclaimer">
                    * Prices prevailing at the time of vehicle delivery will be applicable. Road tax and registration charges as per state RTO norms.<br>
                    This is an electronically generated official quotation from Defence Autolink.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
