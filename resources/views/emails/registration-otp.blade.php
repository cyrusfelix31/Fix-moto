<p>Hello,</p>
<p>Use this one-time code to {{ $purpose === 'mechanic_access' ? 'access your FixMoto mechanic verification account' : 'verify your email address for FixMoto registration' }}:</p>
<p style="font-size:28px;font-weight:700;letter-spacing:8px">{{ $code }}</p>
<p>This code expires in {{ $expirationMinutes }} minutes. If you did not request it, you can ignore this email.</p>
