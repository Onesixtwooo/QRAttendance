<div style="margin:0; padding:32px 16px; background-color:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 4px 16px rgba(15, 23, 42, 0.08);">
        <tr>
            <td style="padding:30px 36px; background-color:#0f766e; color:#ffffff;">
                <p style="margin:0 0 8px; font-size:13px; letter-spacing:1.5px; text-transform:uppercase; opacity:0.85;">Attendance System</p>
                <h1 style="margin:0; font-size:28px; line-height:1.25; font-weight:700;">Your student account is ready</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:36px;">
                <p style="margin:0 0 18px; font-size:16px; line-height:1.6;">Hello {{ $student->first_name }},</p>
                <p style="margin:0 0 26px; font-size:16px; line-height:1.6; color:#4b5563;">Your student account has been created for the attendance system. Use the credentials below to sign in.</p>

                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 28px; border:1px solid #dbe5e3; border-radius:10px; background-color:#f0fdfa;">
                    <tr>
                        <td style="padding:16px 18px; border-bottom:1px solid #dbe5e3;">
                            <p style="margin:0 0 5px; font-size:12px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#0f766e;">Login email</p>
                            <p style="margin:0; font-size:16px; color:#111827; word-break:break-word;">{{ $student->user?->email }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 18px;">
                            <p style="margin:0 0 5px; font-size:12px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#0f766e;">Temporary password</p>
                            <p style="margin:0; font-size:16px; font-family:Consolas, Monaco, monospace; color:#111827;">{{ $temporaryPassword }}</p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 28px;">
                    <tr>
                        <td style="border-radius:8px; background-color:#0f766e;">
                            <a href="https://attendance.jpcsolshco.org" style="display:inline-block; padding:14px 26px; border:1px solid #0f766e; border-radius:8px; color:#ffffff; font-size:16px; font-weight:700; text-decoration:none;">Sign in to Attendance System</a>
                        </td>
                    </tr>
                </table>

                <p style="margin:0; font-size:14px; line-height:1.6; color:#4b5563;">For your security, please sign in and change your temporary password from <strong style="color:#1f2937;">Edit my account</strong>.</p>
                <p style="margin:22px 0 0; font-size:13px; line-height:1.6; color:#6b7280;">If the button does not work, copy and paste this link into your browser:<br><a href="https://attendance.jpcsolshco.org" style="color:#0f766e; word-break:break-all;">https://attendance.jpcsolshco.org</a></p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 36px; background-color:#f8fafc; text-align:center;">
                <p style="margin:0; font-size:12px; color:#64748b;">JPCS- OLSHCO Attendance System</p>
            </td>
        </tr>
    </table>
</div>
