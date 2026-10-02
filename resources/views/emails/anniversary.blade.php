<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Happy Anniversary {{ $lead->name }}!</title>
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
            background: linear-gradient(135deg, #4f46e5, #7c3aed, #9333ea);
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
        .flower-emoji {
            font-size: 54px;
            display: block;
            margin-bottom: 10px;
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
            background: linear-gradient(135deg, #eef2ff, #ede9fe);
            border: 1px solid #ddd6fe;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        .celebrant-name {
            font-size: 22px;
            font-weight: 700;
            color: #4338ca;
            margin-bottom: 4px;
        }
        .celebrant-sub {
            font-size: 13px;
            color: #6366f1;
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
            border-left: 4px solid #7c3aed;
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
            <span class="flower-emoji">💐</span>
            <h1>Happy Anniversary!</h1>
            <p>Celebrating love, togetherness, and wonderful milestones</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="celebrant-card">
                <div class="celebrant-name">{{ $lead->name }} & Family</div>
                <div class="celebrant-sub">Esteemed Patron of Defence Autolink</div>
            </div>

            <p class="message-text">
                Dear <strong>{{ $lead->name }}</strong>,
            </p>

            <p class="message-text">
                On this joyful milestone of your <strong>Anniversary</strong>, the leadership and staff at <strong>Defence Autolink</strong> extend our heartiest congratulations and warmest wishes to you and your partner!
            </p>

            <p class="message-text">
                May your journey together continue to be blessed with boundless love, happiness, peace, and mutual prosperity. Here's to celebrating all the wonderful memories you've built and the exciting adventures yet to come!
            </p>

            @if($lead->model_variant || $lead->brand_name)
            <div class="benefit-box">
                <div class="benefit-title">✨ Exclusive Anniversary Celebration Privilege</div>
                <p class="benefit-desc">
                    To make this occasion even more special as you explore your requirements for the <strong>{{ $lead->model_variant ?: $lead->brand_name }}</strong>, our team would be delighted to host you with dedicated hospitality and anniversary privileges.
                </p>
            </div>
            @endif

            <div class="closing-text">
                <p style="margin: 0 0 5px 0;">Wishing you a lifetime of joy and togetherness,</p>
                <p style="margin: 0; font-weight: 700; color: #0f172a;">Team DEFENCE AUTOLINK</p>
                <p style="margin: 2px 0 0 0; color: #64748b; font-size: 13px;">Customer Relationship & Milestone Care</p>
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
