@extends('layouts.app')
@section('title','Sign in - FIXMOTO')
@section('content')
<section class="auth-card login-card">
    <div class="auth-brand login-brand"><img class="fixmoto-logo" src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2" alt="FixMoto motorcycle repair"></div>
    <h2>Welcome to FixMoto</h2><p>Sign in to your FixMoto account</p>
    @if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('login') }}">@csrf
        <label>Username<input type="text" name="email" value="{{ old('email') }}" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <label class="check"><input type="checkbox" name="remember"> Remember me</label>
        <button class="btn primary" type="submit">Sign in</button>
    </form>
    <p class="auth-switch">New to FIXMOTO? <a href="{{ route('register') }}">Create account</a></p>
    <p class="auth-switch"><a href="{{ route('mechanic.access') }}">Check a mechanic application</a></p>
</section>
@endsection
