<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #334155;
            margin: 0;
            padding: 30px 15px;
        }
        .email-container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .email-banner {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            padding: 35px 25px;
            color: #ffffff;
            text-align: center;
            border-bottom: 3px solid #3b82f6;
        }
        .brand-badge {
            display: inline-block;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #60a5fa;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .email-banner h1 {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .email-banner p {
            margin: 0;
            font-size: 14px;
            color: #94a3b8;
        }
        .email-body {
            padding: 35px 30px;
            background-color: #ffffff;
            font-size: 15px;
            line-height: 1.6;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .alert-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        .btn-reset-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        .btn-reset {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 34px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        }
        .btn-reset:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
        }
        .fallback-link {
            word-break: break-all;
            font-size: 12px;
            color: #64748b;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px dashed #cbd5e1;
            margin-top: 15px;
        }
        .fallback-link a {
            color: #2563eb;
            text-decoration: underline;
        }
        .email-footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 25px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-banner">
            <div class="brand-badge">DEFENCE AUTOLINK CRM</div>
            <h1>Password Reset Request</h1>
            <p>Secure Account Recovery</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="greeting">Hello {{ $user->name }},</div>

            <p>
                We received a request to reset the password for your Defence Autolink CRM account associated with <strong>{{ $user->email }}</strong>.
            </p>

            <!-- Expiry Warning -->
            <div class="alert-box">
                ⏰ <strong>Security Notice:</strong> This password reset link is strictly valid for <strong>{{ $validityMinutes }} minutes</strong>. After that, it will expire and you will need to request a new link.
            </div>

            <!-- Action Button -->
            <div class="btn-reset-wrapper">
                <a href="{{ $resetUrl }}" target="_blank" class="btn-reset">
                    Reset My Password
                </a>
            </div>

            <p style="font-size: 13px; color: #64748b; margin-top: 25px;">
                If the button above does not work, copy and paste this link into your web browser:
            </p>
            <div class="fallback-link">
                <a href="{{ $resetUrl }}" target="_blank">{{ $resetUrl }}</a>
            </div>

            <p style="font-size: 13px; color: #94a3b8; margin-top: 25px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                If you did not request a password reset, no further action is required. Your account remains completely secure.
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            &copy; {{ date('Y') }} Defence Autolink. All rights reserved.<br>
            Automated notification system &bull; Please do not reply directly to this email.
        </div>
    </div>
</body>
</html>
