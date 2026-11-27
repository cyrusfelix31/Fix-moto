@extends('layouts.app')
@section('title', 'Shop Profile')
@section('page_title', 'Shop Profile')

@section('content')

@php
    $selectedServices = \App\Services\ShopServiceCatalog::normalize(
        old('services', $profile->services ?? [])
    );
@endphp

<div class="page mechanic-page sp-page">

    {{-- VALIDATION ERRORS --}}

    @if($errors->any())
        <div class="sp-error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- SHOP PROFILE HEADER --}}

    <section class="sp-card sp-shop-header">

        <div class="sp-shop-icon">
            <i data-lucide="store"></i>
        </div>

        <div class="sp-shop-title">

            <h2>{{ $profile->shop_name }}</h2>

            <p>{{ $profile->service_address }}</p>

            @if(
                $profile->is_verified &&
                $profile->verification_status === 'approved'
            )

                <span class="sp-verified">
                    <i data-lucide="badge-check"></i>
                    Verified
                </span>

            @else

                <span class="sp-inactive">
                    {{ ucfirst($profile->verification_status) }}
                </span>

            @endif

        </div>

    </section>


    {{-- =====================================
         OWNER'S INFORMATION
         ===================================== --}}

    <section class="sp-card">

        <div class="sp-heading">

            <h2>Owner's Information</h2>

            <button type="button"
                    class="sp-icon-button"
                    data-open="sp-owner-dialog"
                    aria-label="Edit owner information"
                    title="Edit owner information">

                <i data-lucide="pencil"></i>

            </button>

        </div>

        <div class="sp-owner-profile">

            <div class="sp-avatar sp-avatar-large">

                @if($owner->profile_picture)

                    <img
                        src="{{ route('mechanic.shop-profile.owner.photo') }}"
                        alt="Owner profile picture">

                @else

                    <i data-lucide="user-round"></i>

                @endif

            </div>

            <div>

                <h3>{{ $owner->name }}</h3>

                <p>Shop Owner</p>

                <button type="button"
                        class="sp-text-button"
                        data-open="sp-owner-dialog">

                    Change photo

                </button>

            </div>

        </div>

        <div class="sp-information-grid">

            <div>
                <span>Full name</span>
                <strong>{{ $owner->name }}</strong>
            </div>

            <div>
                <span>Contact number</span>
                <strong>
                    {{ $owner->phone ?? 'Not provided' }}
                </strong>
            </div>

            <div>
                <span>Email</span>
                <strong>{{ $owner->email }}</strong>
            </div>

            <div>
                <span>Role</span>
                <strong>Shop owner</strong>
            </div>

            <div>
                <span>Member since</span>
                <strong>
                    {{ $owner->created_at?->format('M d, Y') }}
                </strong>
            </div>

        </div>

    </section>


    {{-- =====================================
         MECHANIC INFORMATION
         ===================================== --}}

    <section class="sp-card" id="mechanic-information">

        <div class="sp-heading">

            <h2>Mechanic Information</h2>

            <span class="sp-muted">

                {{ $teamMembers->count() }}
                {{ $teamMembers->count() === 1
                    ? 'mechanic'
                    : 'mechanics' }}

            </span>

        </div>

        <div class="sp-member-list">

            @forelse($teamMembers as $member)

                <article class="sp-member-card"
                         id="member-{{ $member->id }}">

                    <div class="sp-avatar">

                        @if($member->profile_picture)

                            <img
                                src="{{ route(
                                    'mechanic.shop-profile.members.photo',
                                    $member
                                ) }}"
                                alt="Mechanic profile picture">

                        @else

                            <i data-lucide="user-round"></i>

                        @endif

                    </div>

                    <div class="sp-member-info">

                        <h3>{{ $member->name }}</h3>

                        <p>
                            {{ $member->specialization
                                ?: 'General Mechanic' }}
                        </p>

                        <div class="sp-member-contact">

                            <i data-lucide="phone"></i>

                            {{ $member->phone
                                ?: 'Contact number not provided' }}

                        </div>

                        @if($member->years_of_experience !== null)

                            <small class="sp-muted">

                                {{ $member->years_of_experience }}
                                {{ $member->years_of_experience === 1
                                    ? 'year'
                                    : 'years' }}
                                of experience

                            </small>

                        @endif

                        <div class="sp-member-status">

                            @if(!$member->is_active)

                                <span class="sp-status inactive">
                                    Inactive
                                </span>

                            @elseif($member->active_request_count > 0)

                                <span class="sp-status busy">
                                    On service
                                </span>

                            @else

                                <span class="sp-status active">
                                    Available
                                </span>

                            @endif

                        </div>

                    </div>

                    <button type="button"
                            class="sp-icon-button"
                            data-open="sp-member-dialog-{{ $member->id }}"
                            aria-label="Edit {{ $member->name }}"
                            title="Edit mechanic">

                        <i data-lucide="pencil"></i>

                    </button>

                </article>

            @empty

                <div class="sp-empty">

                    <i data-lucide="users"></i>

                    <p>No mechanics registered yet.</p>

                </div>

            @endforelse

        </div>

        <button type="button"
                class="sp-button sp-primary sp-full"
                data-open="sp-member-dialog-new"
                @disabled($teamMembers->count() >= 50)>

            <i data-lucide="plus"></i>

            Add Mechanic

        </button>

        <a href="{{ route('mechanic.team') }}"
           class="sp-manage-link">

            Manage mechanic availability

            <i data-lucide="arrow-right"></i>

        </a>

    </section>


    {{-- =====================================
         SHOP SERVICES AND LOCATION FORM
         ===================================== --}}

    <form method="POST"
          action="{{ route('mechanic.shop-profile.update') }}"
          id="sp-main-form"
          class="sp-main-form">

        @csrf


        {{-- SHOP SERVICES --}}

        <section class="sp-card">

            <div class="sp-section-title">

                <h2>Shop Services</h2>

                <p>Manage available repair services.</p>

            </div>

            <div id="sp-service-list"
                 class="sp-service-list">

                @foreach($selectedServices as $service)

                    <div class="sp-service-row"
                         data-service="{{ $service }}">

                        <i data-lucide="wrench"></i>

                        <span>

                            {{ \App\Services\ShopServiceCatalog::label($service) }}

                        </span>

                        <input type="hidden"
                               name="services[]"
                               value="{{ $service }}">

                        <i data-lucide="circle-check"
                           class="sp-service-check"></i>

                        <button type="button"
                                class="sp-service-remove"
                                data-remove-service
                                aria-label="Remove {{ $service }}"
                                title="Remove service">

                            <i data-lucide="x"></i>

                        </button>

                    </div>

                @endforeach

            </div>

            <p class="sp-empty-services"
               id="sp-empty-services"
               @if(count($selectedServices) > 0) hidden @endif>

                No services selected. Add the repairs
                your shop can provide.

            </p>

            <button type="button"
                    class="sp-button sp-dashed sp-full"
                    data-open="sp-service-dialog">

                <i data-lucide="plus"></i>

                Add Service

            </button>

            <small class="sp-muted">

                Removing a service disables that service for
                future matching after saving your changes.

            </small>

        </section>


        {{-- SHOP LOCATION --}}

        <section class="sp-card">

            <div class="sp-section-title">

                <h2>Shop Location</h2>

            </div>

            <div class="sp-location-address">

                <i data-lucide="map-pin"></i>

                <span id="sp-address-display">

                    {{ old(
                        'service_address',
                        $profile->service_address
                    ) }}

                </span>

            </div>

            <input type="hidden"
                   id="sp-address"
                   name="service_address"
                   value="{{ old(
                       'service_address',
                       $profile->service_address
                   ) }}">

            <input type="hidden"
                   id="sp-latitude"
                   name="latitude"
                   value="{{ old(
                       'latitude',
                       $profile->latitude
                   ) }}">

            <input type="hidden"
                   id="sp-longitude"
                   name="longitude"
                   value="{{ old(
                       'longitude',
                       $profile->longitude
                   ) }}">

            <label class="sp-field">

                Shop contact number

                <input type="tel"
                       name="shop_phone"
                       maxlength="32"
                       placeholder="Enter shop contact number"
                       value="{{ old(
                           'shop_phone',
                           $profile->shop_phone
                       ) }}">

            </label>

            <button type="button"
                    id="sp-open-map"
                    class="sp-button sp-soft sp-full">

                <i data-lucide="map-pin"></i>

                Update GPS Location

            </button>

            <p id="sp-location-status"
               class="sp-muted"
               role="status">

                @if($profile->latitude && $profile->longitude)
                    Shop location is pinned.
                @else
                    No location selected.
                @endif

            </p>

        </section>


        {{-- SAVE CHANGES --}}

        <button type="submit"
                id="sp-save-button"
                class="sp-button sp-primary sp-full sp-save-button">

            <i data-lucide="save"></i>

            Save Changes

        </button>

    </form>

</div>


{{-- ==========================================
     EDIT OWNER INFORMATION MODAL
     ========================================== --}}

<dialog class="sp-dialog"
        id="sp-owner-dialog"
        aria-labelledby="sp-owner-title">

    <div class="sp-dialog-header">

        <div>

            <h2 id="sp-owner-title">
                Edit Owner Information
            </h2>

            <p>Update your account details and photo.</p>

        </div>

        <button type="button"
                class="sp-icon-button"
                data-close
                aria-label="Close">

            <i data-lucide="x"></i>

        </button>

    </div>

    <form method="POST"
          action="{{ route('mechanic.shop-profile.owner.update') }}"
          enctype="multipart/form-data"
          class="sp-dialog-form">

        @csrf

        <label class="sp-field">

            Full name

            <input value="{{ $owner->name }}"
                   readonly>

            <small>

                Registered owner name is protected
                by shop verification.

            </small>

        </label>

        <label class="sp-field">

            Email address

            <input type="email"
                   name="email"
                   value="{{ old('email', $owner->email) }}"
                   required
                   maxlength="255">

        </label>

        <label class="sp-field">

            Contact number

            <input type="tel"
                   name="phone"
                   value="{{ $owner->phone }}"
                   required
                   maxlength="32">

        </label>

        <label class="sp-upload">

            <i data-lucide="upload-cloud"></i>

            <strong>Upload Profile Picture</strong>

            <small>JPG, JPEG, PNG or WEBP — Max 5 MB</small>

            <input type="file"
                   name="profile_picture"
                   accept=".jpg,.jpeg,.png,.webp">

        </label>

        <div class="sp-dialog-actions">

            <button type="button"
                    class="sp-button sp-outline"
                    data-close>

                Cancel

            </button>

            <button type="submit"
                    class="sp-button sp-primary">

                Save Changes

            </button>

        </div>

    </form>

</dialog>


{{-- ==========================================
     ADD AND EDIT MECHANIC MODALS
     ========================================== --}}

@foreach(collect([null])->concat($teamMembers) as $member)

    @php
        $editing = $member !== null;
        $dialogId = $editing ? $member->id : 'new';
    @endphp

    <dialog class="sp-dialog"
            id="sp-member-dialog-{{ $dialogId }}"
            aria-labelledby="sp-member-title-{{ $dialogId }}">

        <div class="sp-dialog-header">

            <div>

                <h2 id="sp-member-title-{{ $dialogId }}">

                    {{ $editing
                        ? 'Edit Mechanic'
                        : 'Add Mechanic' }}

                </h2>

                <p>Provide the mechanic's personal details.</p>

            </div>

            <button type="button"
                    class="sp-icon-button"
                    data-close
                    aria-label="Close">

                <i data-lucide="x"></i>

            </button>

        </div>

        <form method="POST"
              action="{{ $editing
                  ? route(
                      'mechanic.shop-profile.members.update',
                      $member
                  )
                  : route(
                      'mechanic.shop-profile.members.store'
                  ) }}"
              enctype="multipart/form-data"
              class="sp-dialog-form">

            @csrf

            <label class="sp-field">

                Full name

                <input type="text"
                       name="name"
                       value="{{ $member?->name }}"
                       placeholder="Enter mechanic's full name"
                       maxlength="255"
                       required>

            </label>

            <label class="sp-field">

                Contact number

                <input type="tel"
                       name="phone"
                       value="{{ $member?->phone }}"
                       placeholder="09XXXXXXXXX"
                       maxlength="32"
                       required>

            </label>

            <label class="sp-field">

                Specialization

                <input type="text"
                       name="specialization"
                       value="{{ $member?->specialization }}"
                       placeholder="Example: Engine repair"
                       maxlength="255">

            </label>

            <label class="sp-field">

                Years of experience

                <input type="number"
                       name="years_of_experience"
                       value="{{ $member?->years_of_experience }}"
                       min="0"
                       max="80"
                       placeholder="Enter years of experience">

            </label>

            <label class="sp-upload">

                <i data-lucide="upload-cloud"></i>

                <strong>Mechanic Profile Picture</strong>

                <small>JPG, JPEG, PNG or WEBP — Max 5 MB</small>

                <input type="file"
                       name="profile_picture"
                       accept=".jpg,.jpeg,.png,.webp">

            </label>

            <div class="sp-dialog-actions">

                <button type="button"
                        class="sp-button sp-outline"
                        data-close>

                    Cancel

                </button>

                <button type="submit"
                        class="sp-button sp-primary">

                    {{ $editing
                        ? 'Save Changes'
                        : 'Add Mechanic' }}

                </button>

            </div>

        </form>

    </dialog>

@endforeach


{{-- ==========================================
     ADD SERVICE MODAL
     ========================================== --}}

<dialog class="sp-dialog"
        id="sp-service-dialog"
        aria-labelledby="sp-service-title">

    <div class="sp-dialog-header">

        <div>

            <h2 id="sp-service-title">Add Shop Service</h2>

            <p>Select an existing service or add a custom service.</p>

        </div>

        <button type="button"
                class="sp-icon-button"
                data-close
                aria-label="Close">

            <i data-lucide="x"></i>

        </button>

    </div>

    <div class="sp-dialog-form">

        <label class="sp-field">

            Available services

            <select id="sp-service-select">

                <option value="">Select a service</option>

                @foreach($defaultServices as $service)

                    <option value="{{ $service }}">

                        {{ \App\Services\ShopServiceCatalog::label($service) }}

                    </option>

                @endforeach

            </select>

        </label>

        <label class="sp-field">

            Custom service

            <input type="text"
                   id="sp-custom-service"
                   maxlength="100"
                   placeholder="Example: Change Oil">

        </label>

        <p id="sp-service-error"
           class="sp-form-error"
           role="status"></p>

        <div class="sp-dialog-actions">

            <button type="button"
                    class="sp-button sp-outline"
                    data-close>

                Cancel

            </button>

            <button type="button"
                    id="sp-add-service"
                    class="sp-button sp-primary">

                Add Service

            </button>

        </div>

    </div>

</dialog>


{{-- ==========================================
     GPS LOCATION MODAL
     ========================================== --}}

<dialog class="sp-dialog sp-map-dialog"
        id="sp-location-dialog"
        aria-labelledby="sp-map-title">

    <div class="sp-dialog-header">

        <div>

            <h2 id="sp-map-title">
                Update GPS Location
            </h2>

            <p>Select your shop's location within Calapan City.</p>

        </div>

        <button type="button"
                class="sp-icon-button"
                data-close
                aria-label="Close">

            <i data-lucide="x"></i>

        </button>

    </div>

    <div class="sp-map-content">

        <div class="sp-search-wrapper">

            <input type="search"
                   id="sp-map-search"
                   placeholder="Search barangay, street or landmark"
                   autocomplete="off"
                   aria-label="Search shop address">

            <div id="sp-map-results"
                 class="sp-map-results"
                 hidden>

            </div>

        </div>

        <div id="sp-map"
             class="sp-map"
             aria-label="Calapan City map">

        </div>

        <div class="sp-map-selection">

            <small>Selected location</small>

            <strong id="sp-selected-address">

                No location selected.

            </strong>

            <div class="sp-map-coordinates">

                <div>

                    <small>Latitude</small>

                    <strong id="sp-selected-latitude">—</strong>

                </div>

                <div>

                    <small>Longitude</small>

                    <strong id="sp-selected-longitude">—</strong>

                </div>

            </div>

        </div>

        <p id="sp-map-message"
           class="sp-muted"
           role="status">

            Click the map to pin your shop.

        </p>

        <div class="sp-dialog-actions">

            <button type="button"
                    class="sp-button sp-outline"
                    data-close>

                Cancel

            </button>

            <button type="button"
                    id="sp-confirm-location"
                    class="sp-button sp-primary"
                    disabled>

                Confirm Location

            </button>

        </div>

    </div>

</dialog>


{{-- JAVASCRIPT CONFIGURATION --}}

<script>

window.FixMotoShopProfile = {

    cityBoundary: {{ Js::from($cityBoundary ?? null) }},

    serviceLabels: {{ Js::from($serviceLabels) }},

    searchUrl: {{ Js::from(
        route('locations.search', [], false)
    ) }},

    reverseUrl: {{ Js::from(
        route('locations.reverse', [], false)
    ) }}

};

</script>

<script src="{{ asset('js/shop-profile.js') }}?v=1"
        defer>
</script>

@endsection