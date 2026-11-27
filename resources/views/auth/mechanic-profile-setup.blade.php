@extends('layouts.app')
@section('title', 'Profile photo setup - FixMoto')
@section('content')
<section class="auth-card mechanic-photo-setup-card">
    <div class="auth-brand"><img class="fixmoto-logo" src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2" alt="FixMoto"></div>
    <p class="profile-photo-kicker">ACCOUNT SETUP</p>
    <h2>Your shop is ready</h2>
    <p>Add a profile photo for {{ $profile->shop_name }}, or skip and use the default avatar.</p>

    @if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form method="POST" action="{{ route('mechanic.profile.setup.save') }}" enctype="multipart/form-data" data-photo-cropper data-crop-output="profile_photo">
        @csrf
        <label class="photo-picker-label" for="setup-profile-photo">Choose profile photo</label>
        <input id="setup-profile-photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-crop-input>
        <small class="profile-photo-file-name">JPG, PNG or WEBP, up to 5 MB.</small>
        @error('profile_photo')<small class="profile-photo-error">{{ $message }}</small>@enderror
        <div class="photo-crop-layout">
            <div class="photo-crop-viewport" data-crop-viewport aria-label="Crop preview">
                <img src="{{ $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : asset('images/default-profile-avatar.svg') }}" alt="Profile photo crop preview" data-crop-image>
            </div>
            <div class="photo-crop-controls">
                <strong>Photo preview</strong>
                <p>Drag the image to position it. Adjust the zoom, then save.</p>
                <label for="setup-profile-zoom">Zoom</label>
                <input id="setup-profile-zoom" type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom disabled>
                <small data-crop-file-name>No photo selected</small>
            </div>
        </div>
        <div class="profile-photo-setup-actions">
            <button class="btn outline" type="submit" name="choice" value="skip" data-photo-skip>Skip for Now</button>
            <button class="btn primary" type="submit" name="choice" value="save" data-photo-save>Crop and Save Photo</button>
        </div>
    </form>
</section>
<script src="{{ asset('js/mechanic-profile-photo.js') }}?v=1" defer></script>
@endsection
