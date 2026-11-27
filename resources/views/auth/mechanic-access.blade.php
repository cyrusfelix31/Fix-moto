@extends('layouts.app')
@section('title', 'Mechanic Application Access - FIXMOTO')
@section('content')
<section class="auth-card login-card">
    <div class="auth-brand login-brand"><img class="fixmoto-logo" src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2" alt="FixMoto motorcycle repair"></div>
    <h2>Mechanic application access</h2>
    <p>Use the email address you verified during registration. We’ll send a one-time code if it belongs to a mechanic application.</p>
    @if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('mechanic.access-code.request') }}">@csrf
        <label>Email address<input type="email" name="email" value="{{ session('mechanic_access_email', old('email')) }}" autocomplete="email" required></label>
        <button class="btn outline" type="submit">Email me an access code</button>
    </form>
    @if(session('mechanic_access_challenge'))
        <form method="POST" action="{{ route('mechanic.access-code.verify') }}" class="mechanic-otp-verify">@csrf
            <input type="hidden" name="challenge_id" value="{{ session('mechanic_access_challenge') }}">
            <p>Enter the code sent to {{ session('mechanic_access_email') }}.</p>
            <label>One-time code<input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="{{ config('fixmoto.otp_length', 6) }}" required></label>
            <button class="btn primary" type="submit">Verify code</button>
        </form>
        <form method="POST" action="{{ route('mechanic.access-code.resend') }}" class="mechanic-otp-resend">@csrf
            <input type="hidden" name="challenge_id" value="{{ session('mechanic_access_challenge') }}">
            <button class="btn outline" type="submit">Resend code</button>
        </form>
    @endif
    <p class="auth-switch"><a href="{{ route('login') }}">Return to sign in</a></p>
</section>
@endsection
