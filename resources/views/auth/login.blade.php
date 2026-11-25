@extends('layouts.app')
@section('title','Sign in - FIXMOTO')
@section('content')
<section class="auth-card">
    <div class="auth-brand"><span class="brand-mark">F</span><strong>FIXMOTO</strong></div>
    <h2>Welcome to Fix-Moto</h2><p>Sign in to your FIXMOTO account</p>
    @if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <form method="POST" action="{{ route('login') }}">@csrf
        <label>Username<input type="text" name="email" value="{{ old('email') }}" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <label class="check"><input type="checkbox" name="remember"> Remember me</label>
        <button class="btn primary" type="submit">Sign in</button>
    </form>
    <p class="auth-switch">New to FIXMOTO? <a href="{{ route('register') }}">Create account</a></p>
</section>
@endsection
