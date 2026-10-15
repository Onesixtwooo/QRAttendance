<h1>Your administrator account</h1>
<p>Hello {{ $user->name }},</p>
<p>An administrator account has been created for you in the attendance system.</p>
<p><strong>Login email:</strong> {{ $user->email }}</p>
<p><strong>Temporary password:</strong> {{ $temporaryPassword }}</p>
<p>Please sign in and change your password after your first login.</p>
