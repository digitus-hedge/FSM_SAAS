<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invitation to {{ $tenant->name }}</title>
</head>
<body style="margin:0;padding:0;background:#F6F5F2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F6F5F2;padding:40px 20px">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" border="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04)">
                    <tr>
                        <td style="background:{{ $tenant->primary_color ?? '#FFC61A' }};padding:28px 32px">
                            <div style="font-size:20px;font-weight:700;color:#1B1A24">{{ $tenant->name }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px">
                            <h1 style="font-size:22px;font-weight:700;color:#1B1A24;margin:0 0 16px">Hello {{ $user->name }},</h1>
                            <p style="font-size:15px;line-height:1.6;color:#3D3B4A;margin:0 0 16px">
                                You've been invited to join <strong>{{ $tenant->name }}</strong> on Aiywah FSM.
                                Click the button below to accept the invitation and set your password.
                            </p>

                            <div style="margin:28px 0;text-align:center">
                                <a href="{{ $invitationUrl }}"
                                   style="display:inline-block;background:#1B1A24;color:#fff;text-decoration:none;padding:14px 32px;border-radius:999px;font-weight:600;font-size:15px">
                                    Accept invitation
                                </a>
                            </div>

                            <p style="font-size:13px;color:#5F5D6E;margin:0 0 8px">Or paste this URL into your browser:</p>
                            <p style="font-size:12px;color:#5F5D6E;word-break:break-all;background:#F6F5F2;padding:10px 12px;border-radius:8px;margin:0">
                                {{ $invitationUrl }}
                            </p>

                            <p style="font-size:12px;color:#8B89A0;margin:24px 0 0;padding-top:20px;border-top:1px solid #E6E4EE">
                                This link works once and expires in 7 days. If you weren't expecting this invitation, you can ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="font-size:11px;color:#8B89A0;margin:16px 0 0">Aiywah FSM · Field Service Management</p>
            </td>
        </tr>
    </table>
</body>
</html>