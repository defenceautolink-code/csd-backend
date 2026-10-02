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
            padding: 25px 30px;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            margin-bottom: 12px;
            border-bottom: 2px solid #334155;
            padding-bottom: 12px;
        }
        .header-left {
            width: 35%;
            vertical-align: middle;
        }
        .header-right {
            width: 65%;
            vertical-align: middle;
            text-align: center;
        }
        .company-logo {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .company-logo span {
            color: #2563eb;
        }
        .dealership-title {
            font-size: 24px;
            font-weight: 800;
            color: #b91c1c;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .address-box {
            background-color: #e2e8f0;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 9.5px;
            font-weight: bold;
            color: #1e293b;
            line-height: 1.3;
            display: inline-block;
            width: 90%;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .meta-left {
            width: 35%;
            font-weight: bold;
            font-size: 11px;
        }
        .meta-center {
            width: 30%;
            text-align: center;
        }
        .meta-right {
            width: 35%;
            text-align: right;
            font-size: 11px;
        }
        .quote-badge {
            border: 2px solid #0f172a;
            color: #b91c1c;
            font-weight: bold;
            font-size: 12px;
            padding: 3px 15px;
            display: inline-block;
            letter-spacing: 1px;
        }

        .sheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 2px solid #334155;
        }
        .sheet-table th {
            background-color: #94a3b8;
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #334155;
            text-align: left;
        }
        .sheet-table th.text-center {
            text-align: center;
        }
        .sheet-table td {
            padding: 7px 10px;
            font-size: 10.5px;
            border: 1px solid #334155;
            vertical-align: middle;
            font-weight: 600;
        }
        .row-variant td.cell-variant {
            background-color: #86efac;
            color: #052e16;
            font-weight: 800;
            text-transform: uppercase;
        }
        .row-total td {
            background-color: #fef3c7;
            font-weight: 800;
            font-size: 12px;
        }
        .row-total td.total-val {
            color: #b91c1c;
            font-size: 13px;
        }

        .footer-table {
            width: 100%;
            margin-top: 25px;
            border-top: 1px solid #cbd5e1;
            padding-top: 12px;
        }
        .footer-left {
            width: 60%;
            vertical-align: bottom;
            font-size: 9.5px;
            color: #475569;
            line-height: 1.4;
        }
        .footer-right {
            width: 40%;
            vertical-align: bottom;
            text-align: right;
        }
        .signature-box {
            display: inline-block;
            text-align: center;
            border-top: 1px dashed #94a3b8;
            padding-top: 4px;
            width: 160px;
            font-size: 9.5px;
            color: #1e293b;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Header Section with DEFENCE AUTOLINK Red Heading and Address -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="company-logo">
                    DEFENCE <span>AUTOLINK</span>
                </div>
            </td>
            <td class="header-right">
                <div class="dealership-title">DEFENCE AUTOLINK</div>
                <div class="address-box">
                    D-601, 6TH FLOOR, S.G BUSINESS HUB, NEAR UMIYA CAMPUS,
                    <br>
                    Ahmedabad - 380060
                </div>
            </td>
        </tr>
    </table>

    <!-- Meta Details Row: Date, QUOTATION Badge, Client Name -->
    <table class="meta-table">
        <tr>
            <td class="meta-left">
                DATE : {{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d.m.y') }}
            </td>
            <td class="meta-center">
                <div class="quote-badge">QUOTATION</div>
            </td>
            <td class="meta-right">
                CLIENT: <strong>{{ $quotation->customer_name }}</strong>
            </td>
        </tr>
    </table>

    <!-- Main Comparison / Item Breakdown Grid Table -->
    <table class="sheet-table">
        <thead>
            <tr>
                <th style="width: 45%;">
                    CAR: {{ $quotation->subject ? strtoupper(str_replace(['Official Vehicle Quotation – ', 'Price Quotation – '], '', $quotation->subject)) : 'VEHICLE PROPOSAL' }}
                </th>
                <th style="width: 35%; text-align: center;">
                    MODEL: {{ $quotation->items->first()?->item_name ? strtoupper($quotation->items->first()->item_name) : 'STANDARD VARIANT' }}
                </th>
                <th style="width: 20%; text-align: center;">
                    N.A
                </th>
            </tr>
        </thead>
        <tbody>
            <!-- Variant Row (Mint Green) -->
            <tr class="row-variant">
                <td class="cell-variant">
                    VARIENT: {{ $quotation->items->first()?->description ? strtoupper($quotation->items->first()->description) : 'PETROL' }}
                </td>
                <td style="text-align: center;">N.A</td>
                <td style="text-align: center;">N.A</td>
            </tr>

            <!-- Itemized Rows -->
            @foreach($quotation->items as $item)
                <tr>
                    <td>{{ strtoupper($item->item_name) }}</td>
                    <td style="text-align: center; font-weight: bold;">
                        {{ $item->unit_price > 0 ? number_format($item->unit_price, 0) : ($item->description ?: 'INCLUDED') }}
                    </td>
                    <td style="text-align: center; color: #64748b;">N.A</td>
                </tr>
            @endforeach

            @if($quotation->discount > 0)
                <tr>
                    <td style="color: #dc2626;">M.S REWORD (DISCOUNT)</td>
                    <td style="text-align: center; color: #dc2626; font-weight: bold;">- {{ number_format($quotation->discount, 0) }}</td>
                    <td style="text-align: center; color: #64748b;">N.A</td>
                </tr>
            @endif

            <!-- Estimated Grand Total Row -->
            <tr class="row-total">
                <td style="color: #92400e;">ESTIMATED ON-ROAD PRICE</td>
                <td class="total-val" style="text-align: center;">
                    ₹ {{ number_format($quotation->grand_total, 0) }}/-
                </td>
                <td style="text-align: center; color: #64748b;">N.A</td>
            </tr>
        </tbody>
    </table>

    <!-- Footer & Signatures -->
    <table class="footer-table">
        <tr>
            <td class="footer-left">
                <strong>Executive:</strong> {{ $quotation->creator->name ?? 'PRIYANKA PARMAR' }} • Contact: +91 {{ $quotation->creator->phone ?? '97233 37621' }}<br>
                <span>* Prices prevailing at the time of delivery will be applicable. Road tax & registration as per RTO norms.</span>
            </td>
            <td class="footer-right">
                <div class="signature-box">
                    For DEFENCE AUTOLINK<br>
                    <span style="font-size: 8px; font-weight: normal; color: #64748b;">(Authorized Dealership Signatory)</span>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
