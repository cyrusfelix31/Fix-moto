@extends('layouts.app')
@section('title', 'Create mechanic password - FixMoto')
@section('content')
<section class="auth-card">
    <div class="auth-brand"><img class="fixmoto-logo" src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2" alt="FixMoto"></div>
    <h2>Create your shop password</h2>
    <p>{{ $user->mechanicProfile?->shop_name }}</p>
    @if(!$valid)
        <div class="alert error">This setup link has expired or was already used. Sign in with an email code to request a new link.</div>
        <a class="btn primary" href="{{ route('login') }}">Return to sign in</a>
    @else
        @if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <form method="POST" action="{{ route('mechanic.password.setup.store') }}">@csrf
            <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="email" value="{{ $user->email }}">
            <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
            <label>Confirm password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
            <button class="btn primary" type="submit">Save password</button>
        </form>
    @endif
</section>
@endsection
