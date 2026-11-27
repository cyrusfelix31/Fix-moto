@extends('layouts.app')

@section('title', 'Create Account - FIXMOTO')

@section('content')

<link rel="stylesheet"
      href="{{ asset('css/shop-registration.css') }}?v=4">

<section class="auth-card wide fixmoto-registration">

    {{-- FIXMOTO BRAND --}}

    <div class="auth-brand">

        <img class="fixmoto-logo" src="{{ asset('images/motofix-logo.png') }}?v=1" alt="FixMoto motorcycle repair">

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
          enctype="multipart/form-data">

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
                           value="{{ old('name') }}"
                           placeholder="Enter full name"
                           maxlength="255"
                           autocomplete="name"
                           required>

                </label>


                {{-- OWNER CONTACT --}}

                <label>

                    Contact number

                    <input type="tel"
                           name="phone"
                           value="{{ old('phone') }}"
                           placeholder="09XXXXXXXXX"
                           maxlength="32"
                           autocomplete="tel">

                </label>


                {{-- EMAIL ADDRESS --}}

                <label>

                    Email address

                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="Enter email address"
                           autocomplete="email"
                           required>

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
                                   min="{{ today()->toDateString() }}"
                                   value="{{ old('valid_id_expiration') }}"
                                   disabled>

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
                           value="{{ old('shop_phone') }}"
                           placeholder="Enter shop contact number"
                           maxlength="32"
                           required
                           disabled>

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
                           min="{{ today()->toDateString() }}"
                           value="{{ old('business_permit_expiration') }}"
                           required
                           disabled>

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
                           min="{{ today()->toDateString() }}"
                           value="{{ old('mayors_permit_expiration') }}"
                           required
                           disabled>

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

        <section id="mechanic-team-section"
                 class="registration-section mechanic-fields"
                 hidden>

            <div class="registration-section-header">

                <span class="registration-icon">

                    <i data-lucide="users"></i>

                </span>

                <div>

                    <h3>Mechanic Team</h3>

                    <p>Register the mechanics in your shop.</p>

                </div>

            </div>

            <div class="mechanic-roster-heading">

                <strong>Registered mechanics</strong>

                <span id="mechanic-count">

                    1 mechanic

                </span>

            </div>

            <div id="mechanic-roster-fields"></div>

            <button type="button"
                    id="add-mechanic"
                    class="btn outline add-mechanic-button"
                    disabled>

                Add Mechanic

            </button>

        </section>


        {{-- ==================================
             ACCOUNT SECURITY
             ================================== --}}

        <section class="registration-section">

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

                    <input type="password"
                           name="password"
                           placeholder="Enter password"
                           minlength="8"
                           autocomplete="new-password"
                           required>

                </label>

                <label class="registration-full">

                    Confirm password

                    <input type="password"
                           name="password_confirmation"
                           placeholder="Confirm your password"
                           minlength="8"
                           autocomplete="new-password"
                           required>

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

    existingMechanics: {{ Js::from(old('mechanics', [])) }}

};

</script>


{{-- REGISTRATION JAVASCRIPT --}}

<script src="{{ asset('js/shop-registration.js') }}?v=4"
        defer>
</script>

@endsection
