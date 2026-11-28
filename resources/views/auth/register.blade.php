@extends('layouts.app')

@section('title', 'Create Account - FIXMOTO')

@section('content')

@php
    $otpChallengeId = session('otp_challenge') ?? old('challenge_id');
    $otpEmail = session('otp_email') ?? old('otp_email');
    $otpResendSeconds = session('otp_resend_seconds') ?? old('otp_resend_remaining') ?? config('fixmoto.otp_resend_seconds', 60);
@endphp

<link rel="stylesheet"
      href="{{ asset('css/shop-registration.css') }}?v=6">

<section class="auth-card wide fixmoto-registration">

    {{-- FIXMOTO BRAND --}}

    <div class="auth-brand">

        <img class="fixmoto-logo" src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2" alt="FixMoto motorcycle repair">

    </div>

    <h2>Create your account</h2>

    <p>Join the FIXMOTO roadside service network.</p>


    {{-- VALIDATION ERRORS --}}

    @if($errors->any())

        <div class="alert error" role="alert">

            @foreach($errors->all() as $error)

                <div>{{ $error }}</div>

            @endforeach

        </div>

    @endif


    {{-- REGISTRATION FORM --}}

    <form method="POST"
          id="registration-form"
          action="{{ route('register') }}"
          enctype="multipart/form-data"
          novalidate>

        @csrf


        {{-- ACCOUNT TYPE SELECTION --}}

        <div class="role-picker">

            <label>

                <input type="radio"
                       name="role"
                       value="rider"
                       @checked(old('role', 'rider') === 'rider')>

                <span>

                    <i data-lucide="bike"></i>

                    <strong>Rider</strong>

                    <small>I need roadside help</small>

                </span>

            </label>

            <label>

                <input type="radio"
                       name="role"
                       value="mechanic"
                       @checked(old('role') === 'mechanic')>

                <span>

                    <i data-lucide="store"></i>

                    <strong>Mechanic Shop</strong>

                    <small>We provide repairs</small>

                </span>

            </label>

        </div>


        {{-- ==================================
             OWNER INFORMATION
             ================================== --}}

        <section class="registration-section">

            <div class="registration-section-header">

                <span class="registration-icon">

                    <i data-lucide="user-round"></i>

                </span>

                <div>

                    <h3 id="information-title">

                        Personal Information

                    </h3>

                    <p>Provide your account details.</p>

                </div>

            </div>

            <div class="registration-grid">


                {{-- OWNER NAME --}}

                <label class="registration-full">

                    <span id="account-name-label">

                        Full name

                    </span>

                    <input type="text"
                           name="name"
                           data-registration-name
                           value="{{ old('name') }}"
                           placeholder="Enter full name"
                           maxlength="255"
                           autocomplete="name"
                           required>

                    <small class="registration-field-feedback" data-feedback-for="name" aria-live="polite"></small>

                </label>


                {{-- OWNER CONTACT --}}

                <label>

                    Contact number

                    <input type="tel"
                           name="phone"
                           data-registration-phone
                           value="{{ old('phone') }}"
                           placeholder="09XXXXXXXXX"
                           maxlength="11"
                           pattern="09[0-9]{9}"
                           inputmode="numeric"
                           autocomplete="tel"
                           required>

                    <small class="registration-field-feedback" data-feedback-for="phone" aria-live="polite"></small>

                </label>


                {{-- EMAIL ADDRESS --}}

                <label>

                    Email address

                    <input type="email"
                           name="email"
                           data-registration-email
                           data-email-check-url="{{ route('register.email-check') }}"
                           value="{{ old('email') }}"
                           placeholder="Enter email address"
                           autocomplete="email"
                           required>

                    <small class="registration-field-feedback" data-feedback-for="email" aria-live="polite"></small>

                </label>


                {{-- OWNER VALID ID --}}

                <div class="registration-full mechanic-fields"
                     hidden>

                    <div class="document-upload-card">

                        <h4>Owner's Valid ID</h4>

                        <label class="document-dropzone">

                            <i data-lucide="upload-cloud"></i>

                            <strong>Upload Valid ID</strong>

                            <small>
                                PDF, JPG or PNG — Max 5 MB
                            </small>

                            <input type="file"
                                   name="valid_id"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   required
                                   disabled>

                        </label>

                        <label class="document-expiration">

                            Expiration date (if applicable)

                            <input type="date"
                                   name="valid_id_expiration"
                                   data-registration-expiration
                                   min="{{ today('Asia/Manila')->toDateString() }}"
                                   value="{{ old('valid_id_expiration') }}"
                                   disabled>
                            <small class="registration-field-feedback" data-feedback-for="valid_id_expiration" aria-live="polite"></small>

                        </label>

                    </div>

                </div>

            </div>

        </section>


        {{-- ==================================
             SHOP INFORMATION
             ================================== --}}

        <section id="shop-information-section"
                 class="registration-section mechanic-fields"
                 hidden>

            <div class="registration-section-header">

                <span class="registration-icon">

                    <i data-lucide="store"></i>

                </span>

                <div>

                    <h3>Shop Information</h3>

                    <p>Provide your registered business details.</p>

                </div>

            </div>

            <div class="registration-grid">


                {{-- SHOP NAME --}}

                <label class="registration-full">

                    Shop name

                    <input type="text"
                           id="shop-name"
                           name="shop_name"
                           value="{{ old('shop_name') }}"
                           placeholder="Enter registered shop name"
                           maxlength="255"
                           disabled>

                </label>


                {{-- SHOP ADDRESS WITH GPS --}}

                <div class="registration-full shop-address-section">

                    <label for="service-address">

                        Shop address

                    </label>

                    <div class="shop-address-wrapper">

                        <div class="shop-address-input-group">

                            <input type="text"
                                   id="service-address"
                                   name="service_address"
                                   value="{{ old('service_address') }}"
                                   placeholder="Search address in Calapan City"
                                   maxlength="1000"
                                   autocomplete="off"
                                   aria-describedby="shop-location-status"
                                   disabled>

                            <button type="button"
                                    id="open-shop-map"
                                    class="shop-gps-button"
                                    title="Pin shop location"
                                    aria-label="Open GPS map"
                                    disabled>

                                <i data-lucide="map-pin"></i>

                            </button>

                        </div>

                        {{-- ADDRESS RECOMMENDATIONS --}}

                        <div id="address-suggestions"
                             class="address-suggestions"
                             hidden>

                        </div>

                    </div>

                    <small class="address-hint">

                        Search for a barangay, street,
                        subdivision or landmark within Calapan City.

                        Click the GPS icon to manually pin
                        your exact shop location.

                    </small>

                    <p id="shop-location-status"
                       class="shop-location-status"
                       role="status">

                        @if(old('latitude') && old('longitude'))

                            Shop location selected.

                        @else

                            No shop location selected.

                        @endif

                    </p>

                    {{-- GPS COORDINATES --}}

                    <input type="hidden"
                           name="latitude"
                           id="shop-latitude"
                           value="{{ old('latitude') }}"
                           disabled>

                    <input type="hidden"
                           name="longitude"
                           id="shop-longitude"
                           value="{{ old('longitude') }}"
                           disabled>

                </div>


                {{-- SHOP CONTACT NUMBER --}}

                <label class="registration-full">

                    Shop contact number

                    <input type="tel"
                           name="shop_phone"
                           data-registration-phone
                           value="{{ old('shop_phone') }}"
                           placeholder="Enter shop contact number"
                           maxlength="11"
                           pattern="09[0-9]{9}"
                           inputmode="numeric"
                           required
                           disabled>

                    <small class="registration-field-feedback" data-feedback-for="shop_phone" aria-live="polite"></small>

                </label>

            </div>

        </section>


        {{-- ==================================
             BUSINESS DOCUMENTS
             ================================== --}}

        <section class="registration-section mechanic-fields"
                 hidden>

            <div class="registration-section-header">

                <span class="registration-icon">

                    <i data-lucide="file-check"></i>

                </span>

                <div>

                    <h3>Business Documents</h3>

                    <p>Upload clear and valid business documents.</p>

                </div>

            </div>


            {{-- BUSINESS PERMIT --}}

            <div class="document-upload-card">

                <h4>Business Permit</h4>

                <label class="document-dropzone">

                    <i data-lucide="upload-cloud"></i>

                    <strong>Upload Business Permit</strong>

                    <small>PDF, JPG or PNG — Max 5 MB</small>

                    <input type="file"
                           name="business_permit"
                           accept=".pdf,.jpg,.jpeg,.png"
                           required
                           disabled>

                </label>

                <label class="document-expiration">

                    Expiration date

                    <input type="date"
                           name="business_permit_expiration"
                           data-registration-expiration
                           min="{{ today('Asia/Manila')->toDateString() }}"
                           value="{{ old('business_permit_expiration') }}"
                           required
                           disabled>
                    <small class="registration-field-feedback" data-feedback-for="business_permit_expiration" aria-live="polite"></small>

                </label>

            </div>


            {{-- MAYOR'S PERMIT --}}

            <div class="document-upload-card">

                <h4>Mayor's Permit</h4>

                <label class="document-dropzone">

                    <i data-lucide="upload-cloud"></i>

                    <strong>Upload Mayor's Permit</strong>

                    <small>PDF, JPG or PNG — Max 5 MB</small>

                    <input type="file"
                           name="mayors_permit"
                           accept=".pdf,.jpg,.jpeg,.png"
                           required
                           disabled>

                </label>

                <label class="document-expiration">

                    Expiration date

                    <input type="date"
                           name="mayors_permit_expiration"
                           data-registration-expiration
                           min="{{ today('Asia/Manila')->toDateString() }}"
                           value="{{ old('mayors_permit_expiration') }}"
                           required
                           disabled>
                    <small class="registration-field-feedback" data-feedback-for="mayors_permit_expiration" aria-live="polite"></small>

                </label>

            </div>


            {{-- BIR CERTIFICATE --}}

            <div class="document-upload-card">

                <h4>BIR Certificate of Registration</h4>

                <label class="document-dropzone">

                    <i data-lucide="upload-cloud"></i>

                    <strong>Upload BIR Certificate</strong>

                    <small>PDF, JPG or PNG — Max 5 MB</small>

                    <input type="file"
                           name="bir_certificate"
                           accept=".pdf,.jpg,.jpeg,.png"
                           required
                           disabled>

                </label>

            </div>


            <small class="address-hint">

                Documents are submitted for administrator
                verification.

            </small>

        </section>


        {{-- ==================================
             MECHANIC TEAM
             ================================== --}}

        {{-- ==================================
             ACCOUNT SECURITY
             ================================== --}}

        <section id="account-security-section" class="registration-section rider-account-security">

            <div class="registration-section-header">

                <span class="registration-icon">

                    <i data-lucide="lock"></i>

                </span>

                <div>

                    <h3>Account Security</h3>

                    <p>Create a secure password.</p>

                </div>

            </div>

            <div class="registration-grid">

                <label class="registration-full">

                    Password

                    <div class="registration-password-control">

                        <input id="registration-password"
                               type="password"
                               name="password"
                               data-registration-password
                               placeholder="Enter password"
                               minlength="8"
                               autocomplete="new-password"
                               required>

                        <button class="registration-password-toggle"
                                type="button"
                                data-password-toggle
                                aria-controls="registration-password"
                                aria-label="Show password"
                                aria-pressed="false">

                            <i data-lucide="eye"></i>

                        </button>

                    </div>

                    <small class="registration-field-feedback" data-feedback-for="password" aria-live="polite"></small>

                </label>

                <label class="registration-full">

                    Confirm password

                    <div class="registration-password-control">

                        <input id="registration-password-confirmation"
                               type="password"
                               name="password_confirmation"
                               data-registration-password-confirmation
                               placeholder="Confirm your password"
                               minlength="8"
                               autocomplete="new-password"
                               required>

                        <button class="registration-password-toggle"
                                type="button"
                                data-password-toggle
                                aria-controls="registration-password-confirmation"
                                aria-label="Show password"
                                aria-pressed="false">

                            <i data-lucide="eye"></i>

                        </button>

                    </div>

                    <small class="registration-field-feedback" data-feedback-for="password_confirmation" aria-live="polite"></small>

                </label>

            </div>

        </section>


        {{-- REGISTER BUTTON --}}

        <button type="submit"
                id="registration-submit"
                class="btn primary registration-submit">

            Create Account

        </button>

    </form>


    {{-- LOGIN LINK --}}

    <p class="auth-switch">

        Already have an account?

        <a href="{{ route('login') }}">

            Sign in

        </a>

    </p>

</section>

<dialog id="registration-otp-dialog"
        class="registration-otp-dialog"
        data-challenge="{{ $otpChallengeId }}"
        data-resend-seconds="{{ (int) $otpResendSeconds }}"
        aria-labelledby="registration-otp-title">
    <div class="registration-otp-content">
        <span class="registration-otp-icon"><i data-lucide="mail-check"></i></span>
        <h2 id="registration-otp-title">Verify your email</h2>
        <p>Enter the {{ (int) config('fixmoto.otp_length', 6) }}-digit code sent to <strong>{{ $otpEmail }}</strong>.</p>
        <p class="muted">The code expires in {{ (int) config('fixmoto.otp_expiration_minutes', 5) }} minutes.</p>

        @error('otp')
            <div class="alert error" role="alert">{{ $message }}</div>
        @enderror
        @if(old('otp_attempts_remaining') !== null)
            <p class="otp-attempts-remaining" role="status">{{ (int) old('otp_attempts_remaining') }} of {{ (int) config('fixmoto.otp_max_attempts', 5) }} verification attempts remaining.</p>
        @endif

        <form method="POST" action="{{ route('register.verify-otp') }}" class="registration-otp-form">
            @csrf
            <input type="hidden" name="challenge_id" value="{{ $otpChallengeId }}">
            <label for="registration-otp-code">Verification code</label>
            <input id="registration-otp-code"
                   type="text"
                   name="otp"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   pattern="[0-9]+"
                   minlength="{{ (int) config('fixmoto.otp_length', 6) }}"
                   maxlength="{{ (int) config('fixmoto.otp_length', 6) }}"
                   required
                   autofocus>
            <button type="submit" class="btn primary">Verify email and create account</button>
        </form>

        <form method="POST" action="{{ route('register.resend-otp') }}" class="registration-otp-resend">
            @csrf
            <input type="hidden" name="challenge_id" value="{{ $otpChallengeId }}">
            <button type="submit" id="registration-resend-otp" class="btn outline">Resend code</button>
            <span id="registration-resend-countdown" role="status"></span>
        </form>
        <button type="button" id="close-registration-otp" class="registration-otp-close">Back to registration</button>
    </div>
</dialog>


{{-- ======================================
     GPS LOCATION MAP MODAL
     ====================================== --}}

<dialog id="shop-map-modal"
        class="shop-map-modal"
        aria-labelledby="shop-map-title">

    <div class="shop-map-modal-content">

        <div class="shop-map-modal-header">

            <div>

                <h2 id="shop-map-title">

                    Pin Shop Location

                </h2>

                <p>

                    Select your exact business location
                    within Calapan City.

                </p>

            </div>

            <button type="button"
                    id="close-shop-map"
                    class="shop-map-close"
                    aria-label="Close map">

                &times;

            </button>

        </div>


        {{-- REAL LEAFLET MAP --}}

        <div id="shop-location-map"
             class="shop-picker-map"
             aria-label="Calapan City map">

        </div>


        {{-- SELECTED LOCATION DETAILS --}}

        <div class="selected-location-details">

            <small>Selected address</small>

            <strong id="selected-shop-address">

                No location selected.

            </strong>

            <div class="shop-coordinate-grid">

                <div>

                    <small>Latitude</small>

                    <strong id="selected-latitude">

                        —

                    </strong>

                </div>

                <div>

                    <small>Longitude</small>

                    <strong id="selected-longitude">

                        —

                    </strong>

                </div>

            </div>

            <div>

                <small>Detected barangay</small>

                <strong id="selected-barangay">

                    —

                </strong>

            </div>

        </div>

        <p id="shop-map-message"
           class="shop-map-message"
           role="status">

            Click the map to place your shop location pin.

        </p>


        {{-- MAP ACTION BUTTONS --}}

        <div class="shop-map-actions">

            <button type="button" id="use-current-shop-location" class="btn outline">
                <i data-lucide="locate-fixed"></i> Use My Current Location
            </button>

            <button type="button"
                    id="cancel-shop-location"
                    class="btn outline">

                Cancel

            </button>

            <button type="button"
                    id="confirm-shop-location"
                    class="btn primary"
                    disabled>

                Confirm Location

            </button>

        </div>

    </div>

</dialog>


{{-- LOAD LEAFLET IF THE LAYOUT DOES NOT PROVIDE IT --}}

<script>

if (typeof window.L === 'undefined') {

    const leafletCSS = document.createElement('link');

    leafletCSS.rel = 'stylesheet';

    leafletCSS.href =
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';

    document.head.appendChild(leafletCSS);

    const leafletScript = document.createElement('script');

    leafletScript.src =
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

    document.head.appendChild(leafletScript);

}

</script>


{{-- LARAVEL GPS CONFIGURATION --}}

<script>

window.FixMotoRegistrationConfig = {

    cityBoundary: {{ Js::from($cityBoundary ?? null) }},

    searchUrl: @json(route('locations.search')),

    reverseUrl: @json(route('locations.reverse')),

    maxDocumentYears: @json(config('fixmoto.document_expiration_max_years', 20))

};

</script>


{{-- REGISTRATION JAVASCRIPT --}}

<script src="{{ asset('js/shop-registration.js') }}?v=5"
        defer>
</script>

<script src="{{ asset('js/registration-validation.js') }}?v=2" defer></script>

@endsection
