<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sign in to {{ $tenant->name }}</title>
</head>
<body style="margin:0;padding:0;background:#F6F5F2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F6F5F2;padding:40px 20px">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" border="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04)">
                    <tr>
                        <td style="background:{{ $tenant->primary_color ?? '#FFC61A' }};padding:24px 28px">
                            <div style="font-size:20px;font-weight:700;color:#1B1A24">{{ $tenant->name }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px">
                            <h1 style="font-size:22px;font-weight:700;color:#1B1A24;margin:0 0 14px">Hello {{ $user->name }},</h1>
                            <p style="font-size:15px;line-height:1.6;color:#3D3B4A;margin:0 0 14px">
                                Tap the button below to sign in to your <strong>{{ $tenant->name }}</strong> workspace on Aiywah FSM.
                            </p>

                            <div style="margin:28px 0;text-align:center">
                                <a href="{{ $signInUrl }}"
                                   style="display:inline-block;background:#1B1A24;color:#fff;text-decoration:none;padding:14px 36px;border-radius:999px;font-weight:600;font-size:15px">
                                    Sign in
                                </a>
                            </div>

                            <p style="font-size:13px;color:#5F5D6E;margin:0 0 8px">Or copy and paste this URL into your browser:</p>
                            <p style="font-size:12px;color:#5F5D6E;word-break:break-all;background:#F6F5F2;padding:12px 14px;border-radius:10px;font-family:'JetBrains Mono',Consolas,monospace;margin:0">
                                {{ $signInUrl }}
                            </p>

                            <p style="font-size:12px;color:#8B89A0;margin:24px 0 0;padding-top:20px;border-top:1px solid #E6E4EE">
                                This link works once and expires in <strong>24 hours</strong>. If you didn't ask for this, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#F6F5F2;padding:14px 28px;text-align:center;color:#8B89A0;font-size:11.5px;border-top:1px solid #E6E4EE">
                            Aiywah FSM · Field Service Management
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>