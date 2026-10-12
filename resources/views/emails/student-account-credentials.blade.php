<h1>Your student attendance account</h1>
<p>Hello {{ $student->first_name }},</p>
<p>Your student account has been created for the attendance system.</p>
<p><strong>Login email:</strong> {{ $student->user?->email }}</p>
<p><strong>Temporary password:</strong> {{ $temporaryPassword }}</p>
<p>Please sign in and change your password from <strong>Edit my account</strong>.</p>
