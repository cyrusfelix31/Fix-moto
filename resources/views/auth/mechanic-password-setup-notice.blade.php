@extends('layouts.app')
@section('title', 'Finish account setup - FixMoto')
@section('content')
<section class="auth-card">
    <div class="auth-brand"><img class="fixmoto-logo" src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2" alt="FixMoto"></div>
    <h2>Your shop is verified</h2>
    <p>Check your email for a secure link to create the password for {{ auth()->user()->mechanicProfile?->shop_name ?? 'your shop' }}.</p>
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('mechanic.password.setup.resend') }}">@csrf<button class="btn primary" type="submit">Resend setup link</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn outline" type="submit">Sign out</button></form>
</section>
@endsection
