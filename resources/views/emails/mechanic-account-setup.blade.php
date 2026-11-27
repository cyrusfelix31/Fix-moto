<p>Hello {{ $user->name }},</p>
<p>Your FixMoto mechanic shop has been verified. Set a password to open the shop dashboard and manage your mechanic team.</p>
<p><a href="{{ $setupUrl }}">Create your FixMoto password</a></p>
<p>This secure link expires in {{ $expirationMinutes }} minutes and can only be used once. If you did not expect this message, you can ignore it.</p>
