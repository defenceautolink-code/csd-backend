<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Happy Birthday {{ $lead->name }}!</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #334155;
            margin: 0;
            padding: 30px 15px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .email-banner {
            background: linear-gradient(135deg, #e11d48, #be123c, #881337);
            padding: 40px 20px 30px 20px;
            color: #ffffff;
            text-align: center;
            position: relative;
        }
        .dealership-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 30px;
            padding: 5px 16px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .cake-emoji {
            font-size: 54px;
            display: block;
            margin-bottom: 10px;
            animation: bounce 2s infinite;
        }
        .email-banner h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .email-banner p {
            margin: 0;
            font-size: 15px;
            opacity: 0.95;
            font-weight: 400;
        }
        .email-body {
            padding: 36px 30px;
            background: #ffffff;
        }
        .celebrant-card {
            background: linear-gradient(135deg, #fff1f2, #ffe4e6);
            border: 1px solid #fecdd3;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        .celebrant-name {
            font-size: 22px;
            font-weight: 700;
            color: #9f1239;
            margin-bottom: 4px;
        }
        .celebrant-sub {
            font-size: 13px;
            color: #be123c;
            font-weight: 500;
        }
        .message-text {
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
            margin-bottom: 20px;
        }
        .benefit-box {
            background: #f8fafc;
            border-left: 4px solid #e11d48;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 25px 0;
        }
        .benefit-title {
            font-weight: 700;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .benefit-desc {
            font-size: 13px;
            color: #64748b;
            margin: 0;
            line-height: 1.5;
        }
        .closing-text {
            font-size: 14px;
            color: #334155;
            line-height: 1.6;
            margin-top: 25px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
        .email-footer {
            background: #f8fafc;
            padding: 24px;
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
        <!-- Banner -->
        <div class="email-banner">
            <div class="dealership-badge">DEFENCE AUTOLINK</div>
            <span class="cake-emoji">🎂</span>
            <h1>Happy Birthday!</h1>
            <p>Wishing you joy, good health, and many wonderful journeys ahead</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="celebrant-card">
                <div class="celebrant-name">{{ $lead->name }}</div>
                <div class="celebrant-sub">Valued Patron & Friend of Defence Autolink</div>
            </div>

            <p class="message-text">
                Dear <strong>{{ $lead->name }}</strong>,
            </p>

            <p class="message-text">
                On this wonderful occasion of your Birthday, the entire team at <strong>Defence Autolink</strong> sends you our warmest greetings and best wishes!
            </p>

            <p class="message-text">
                May your special day be filled with celebration, laughter, and cherished moments with your family and loved ones. May this coming year drive you forward toward greater success, memorable milestones, and smooth roads ahead.
            </p>

            @if($lead->model_variant || $lead->brand_name)
            <div class="benefit-box">
                <div class="benefit-title">🚗 Special Birthday Privilege from Us</div>
                <p class="benefit-desc">
                    As a token of our appreciation for your association regarding the <strong>{{ $lead->model_variant ?: $lead->brand_name }}</strong>, visit our dealership this month to enjoy complimentary priority consultation, exclusive celebration incentives, and dedicated assistance.
                </p>
            </div>
            @endif

            <div class="closing-text">
                <p style="margin: 0 0 5px 0;">Warm regards and best wishes,</p>
                <p style="margin: 0; font-weight: 700; color: #0f172a;">Team DEFENCE AUTOLINK</p>
                <p style="margin: 2px 0 0 0; color: #64748b; font-size: 13px;">Customer Relationship & Care Division</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>DEFENCE AUTOLINK</strong></p>
            <p>D-601, 6th Floor, S.G Business Hub, S.G Highway, Ahmedabad - 380060</p>
            <p>Helpline: +91 98000 11111 | Email: support@defenceautolink.com</p>
            <p style="margin-top: 10px; font-size: 11px; color: #cbd5e1;">© {{ date('Y') }} Defence Autolink. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
