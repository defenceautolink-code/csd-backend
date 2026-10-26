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
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        body {
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            padding: 25px 32px;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            margin-bottom: 14px;
            border-bottom: 2px solid #334155;
            padding-bottom: 12px;
        }
        .header-left {
            width: 35%;
            vertical-align: middle;
        }
        .logo-box {
            display: inline-block;
            vertical-align: middle;
            width: 48px;
            height: 48px;
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 800;
            font-size: 20px;
            text-align: center;
            line-height: 48px;
            border-radius: 6px;
        }
        .logo-text {
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }
        .logo-text-navy {
            font-size: 16px;
            font-weight: 800;
            color: #1e3a8a;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }
        .logo-text-blue {
            font-size: 16px;
            font-weight: 800;
            color: #2563eb;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }
        .header-right {
            width: 65%;
            vertical-align: middle;
            text-align: center;
        }
        .dealership-title {
            font-size: 24px;
            font-weight: 800;
            color: #b91c1c;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .address-box {
            background-color: #e2e8f0;
            border-radius: 4px;
            padding: 5px 12px;
            font-size: 9.5px;
            font-weight: bold;
            color: #1e293b;
            line-height: 1.35;
            display: inline-block;
            width: 90%;
        }

        .meta-table {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 14px;
        }
        .meta-left {
            width: 33%;
            font-weight: bold;
            font-size: 11px;
            color: #0f172a;
            vertical-align: middle;
        }
        .meta-center {
            width: 34%;
            text-align: center;
            vertical-align: middle;
        }
        .quote-badge {
            border: 2px solid #0f172a;
            color: #b91c1c;
            font-weight: bold;
            font-size: 12px;
            padding: 3px 18px;
            display: inline-block;
            letter-spacing: 1px;
        }
        .meta-right {
            width: 33%;
            text-align: right;
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            vertical-align: middle;
        }
        .client-name {
            color: #2563eb;
            text-transform: uppercase;
        }

        .sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            border: 2px solid #334155;
        }
        .sheet-table th {
            background-color: #94a3b8;
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 9px 12px;
            border: 1px solid #334155;
        }
        .sheet-table th.th-left {
            width: 55%;
            text-align: left;
        }
        .sheet-table th.th-right {
            width: 45%;
            text-align: center;
        }
        .sheet-table td {
            padding: 7px 12px;
            font-size: 10.5px;
            border: 1px solid #334155;
            vertical-align: middle;
            font-weight: 600;
            color: #0f172a;
        }
        .sheet-table td.cell-val {
            text-align: center;
        }
        .sheet-table td.cell-bold {
            font-weight: 700;
        }
        .row-total td {
            background-color: #fef3c7;
            font-weight: 800;
            font-size: 11.5px;
            padding: 9px 12px;
        }
        .row-total td.total-label {
            color: #92400e;
        }
        .row-total td.total-val {
            color: #b91c1c;
            font-size: 13.5px;
            text-align: center;
        }

        .footer-table {
            width: 100%;
            margin-top: 18px;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
        }
        .footer-left {
            width: 60%;
            vertical-align: bottom;
            font-size: 9.5px;
            color: #334155;
            line-height: 1.4;
        }
        .exec-highlight {
            color: #2563eb;
            font-weight: bold;
        }
        .footer-note {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 3px;
        }
        .footer-right {
            width: 40%;
            vertical-align: bottom;
            text-align: right;
        }
        .sig-title {
            font-weight: bold;
            color: #0f172a;
            font-size: 10.5px;
        }
        .sig-subtitle {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <!-- Header Section with DA Logo, Red Heading and Dealership Address -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle;">
                            <div class="logo-box">DA</div>
                        </td>
                        <td style="vertical-align: middle; padding-left: 8px;">
                            <div class="logo-text-navy">DEFENCE</div>
                            <div class="logo-text-blue">AUTOLINK</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="header-right">
                <div class="dealership-title">DEFENCE AUTOLINK</div>
                <div class="address-box">
                    D-601, S G BUSINESS HUB, S G HIGHWAY,
                    <br>
                    Ahmedabad, Gujarat - 382470
                </div>
            </td>
        </tr>
    </table>

    <!-- Meta Details Row: Date, QUOTATION Badge, Client Name -->
    <table class="meta-table">
        <tr>
            <td class="meta-left">
                DATE : {{ $displayDate }}
            </td>
            <td class="meta-center">
                <div class="quote-badge">QUOTATION</div>
            </td>
            <td class="meta-right">
                @if($clientName)
                    CLIENT: <span class="client-name">{{ $clientName }}</span>
                @endif
            </td>
        </tr>
    </table>

    <!-- Main Dealership Quotation Grid Table (Exact Card Match) -->
    <table class="sheet-table">
        <thead>
            <tr>
                <th class="th-left">
                    CAR: {{ strtoupper($carName) }}
                </th>
                <th class="th-right">
                    MODEL: {{ strtoupper($modelSpec) }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>CSD PRICE</td>
                <td class="cell-val cell-bold">{{ $csdPrice ?: 'N.A' }}</td>
            </tr>
            <tr>
                <td>GJ RTO</td>
                <td class="cell-val">{{ $gjRto }}</td>
            </tr>
            <tr>
                <td>BH RTO * APPOROX</td>
                <td class="cell-val">{{ $bhRto }}</td>
            </tr>
            <tr>
                <td>CRTM</td>
                <td class="cell-val">{{ $crtm }}</td>
            </tr>
            <tr>
                <td>INSURANCE</td>
                <td class="cell-val">{{ $insurance }}</td>
            </tr>
            <tr>
                <td>ACCESSORIES</td>
                <td class="cell-val cell-bold">{{ $accessories }}</td>
            </tr>
            <tr>
                <td>WARRANTY</td>
                <td class="cell-val">{{ $warranty }}</td>
            </tr>
            <tr>
                <td>M.S REWORD</td>
                <td class="cell-val">{{ $msReward }}</td>
            </tr>
            <tr>
                <td>DIFFERENCE AMT CASH</td>
                <td class="cell-val">{{ $diffAmtCash }}</td>
            </tr>
            <tr class="row-total">
                <td class="total-label">ESTIMATED ON-ROAD PRICE</td>
                <td class="total-val">₹ {{ $grandTotal }}/-</td>
            </tr>
        </tbody>
    </table>

    <!-- Footer & Signatures -->
    <table class="footer-table">
        <tr>
            <td class="footer-left">
                <strong>Executive:</strong> <span class="exec-highlight">{{ $executiveName }}</span><br>
                <span>Contact: +91 {{ $executivePhone }}</span>
                <div class="footer-note">* Prices prevailing at the time of delivery will be applicable. Road tax as per RTO norms.</div>
            </td>
            <td class="footer-right">
                <div class="sig-title">For DEFENCE AUTOLINK</div>
                <div class="sig-subtitle">(Authorized Multi-Brand Dealership Signatory)</div>
            </td>
        </tr>
    </table>

</body>
</html>
