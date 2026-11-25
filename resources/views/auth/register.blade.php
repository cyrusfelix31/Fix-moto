
@extends('layouts.app')
@section('title', 'Create account - FIXMOTO')
@section('content')

<section class="auth-card wide">

    <div class="auth-brand">
        <span class="brand-mark">F</span>
        <strong>FIXMOTO</strong>
    </div>

    <h2>Create your account</h2>
    <p>Join the FIXMOTO roadside service network</p>

    {{-- Display validation errors --}}
    @if($errors->any())
        <div class="alert error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">

        @csrf

        {{-- Account Role --}}
        <div class="role-picker">

            <label>
                <input
                    type="radio"
                    name="role"
                    value="rider"
                    @checked(old('role', 'rider') === 'rider')
                    required
                >

                <span>
                    <i data-lucide="bike"></i>
                    <strong>Rider</strong>
                    <small>I need roadside help</small>
                </span>
            </label>

            <label>
                <input
                    type="radio"
                    name="role"
                    value="mechanic"
                    @checked(old('role') === 'mechanic')
                    required
                >

                <span>
                    <i data-lucide="building-2"></i>
                    <strong>Mechanic shop</strong>
                    <small>We provide repairs</small>
                </span>
            </label>

        </div>

        <div class="form-grid">

            {{-- Account Information --}}
            <label>
                <span id="account-name-label">
                    Your name
                </span>

                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    required
                >
            </label>

            <label>
                Phone

                <input
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    autocomplete="tel"
                >
            </label>

            <label>
                Email

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                >
            </label>

            <label>
                Password

                <input
                    type="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <label>
                Confirm password

                <input
                    type="password"
                    name="password_confirmation"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            {{-- Mechanic Shop Information --}}
            <label class="mechanic-fields"
                   style="display: none;">

                Shop name

                <input
                    type="text"
                    id="shop-name"
                    name="shop_name"
                    value="{{ old('shop_name') }}"
                    disabled
                >

            </label>

            <label class="mechanic-fields"
                   style="display: none;">

                Service address

                <textarea
                    id="service-address"
                    name="service_address"
                    disabled
                >{{ old('service_address') }}</textarea>

            </label>

            {{-- GPS Shop Location --}}
            <div class="mechanic-fields"
                 style="display: none;">

                <strong>Pin your shop location</strong>

                <p class="muted">
                    Tap or click the map to place the pin
                    in the Calapan service area.
                </p>

                <div
                    id="shop-location-map"
                    class="request-location-map"
                    role="application"
                    aria-label="Choose mechanic shop location on map">
                </div>

                <p
                    id="shop-location-status"
                    class="muted"
                    role="status"
                    aria-live="polite">

                    No shop location selected.

                </p>

            </div>

            <input
                id="shop-latitude"
                name="latitude"
                type="hidden"
                value="{{ old('latitude') }}"
                disabled
            >

            <input
                id="shop-longitude"
                name="longitude"
                type="hidden"
                value="{{ old('longitude') }}"
                disabled
            >

            {{-- Mechanic Roster --}}
            <div class="mechanic-fields mechanic-roster"
                 style="display: none;">

                <div class="mechanic-roster-header">

                    <strong>
                        Mechanics in your shop
                    </strong>

                    <span id="mechanic-count">
                        0 mechanics
                    </span>

                </div>

                <div
                    id="mechanic-roster-fields"
                    class="mechanic-roster-fields">
                </div>

                <button
                    id="add-mechanic"
                    type="button"
                    class="btn outline">

                    Add mechanic

                </button>

            </div>

        </div>

        <button
            class="btn primary"
            type="submit">

            Create account

        </button>

    </form>

    <p class="auth-switch">
        Already have an account?

        <a href="{{ route('login') }}">
            Sign in
        </a>
    </p>

</section>

<script>

const mechanicRoleInputs = [
    ...document.querySelectorAll('input[name="role"]')
];

const mechanicFields = [
    ...document.querySelectorAll('.mechanic-fields')
];

const mechanicRosterFields =
    document.getElementById('mechanic-roster-fields');

const mechanicCount =
    document.getElementById('mechanic-count');

const shopNameInput =
    document.getElementById('shop-name');

const serviceAddressInput =
    document.getElementById('service-address');

const accountNameLabel =
    document.getElementById('account-name-label');

const shopLatitudeInput =
    document.getElementById('shop-latitude');

const shopLongitudeInput =
    document.getElementById('shop-longitude');

const shopMapElement =
    document.getElementById('shop-location-map');

const shopLocationStatus =
    document.getElementById('shop-location-status');

const addMechanicButton =
    document.getElementById('add-mechanic');

const existingMechanics =
    {{ Js::from(old('mechanics', [])) }};

let mechanicNames =
    Array.isArray(existingMechanics) &&
    existingMechanics.length
        ? existingMechanics.map(
            mechanic => mechanic.name ?? ''
        )
        : [''];

let shopMap = null;
let shopLocationMarker = null;


// Check selected account role
function isMechanicSelected() {

    const selectedRole = document.querySelector(
        'input[name="role"]:checked'
    );

    return selectedRole?.value === 'mechanic';
}


// Render mechanic roster
function renderMechanicRoster() {

    const isMechanicShop = isMechanicSelected();

    mechanicRosterFields.replaceChildren();

    mechanicCount.textContent =
        `${mechanicNames.length} ${
            mechanicNames.length === 1
                ? 'mechanic'
                : 'mechanics'
        }`;

    mechanicNames.forEach((name, index) => {

        const row = document.createElement('div');

        row.className = 'mechanic-roster-row';

        const label = document.createElement('label');

        label.textContent =
            `Mechanic ${index + 1} name`;

        const input = document.createElement('input');

        input.type = 'text';

        input.name = `mechanics[${index}][name]`;

        input.value = name;

        input.maxLength = 255;

        // Disable mechanic fields for Rider registration
        input.required = isMechanicShop;
        input.disabled = !isMechanicShop;

        // Preserve entered names
        input.addEventListener('input', () => {
            mechanicNames[index] = input.value;
        });

        label.append(input);

        row.append(label);

        if (mechanicNames.length > 1) {

            const removeButton =
                document.createElement('button');

            removeButton.type = 'button';

            removeButton.className = 'btn outline';

            removeButton.textContent = 'Remove';

            removeButton.disabled = !isMechanicShop;

            removeButton.addEventListener('click', () => {

                mechanicNames.splice(index, 1);

                renderMechanicRoster();

            });

            row.append(removeButton);
        }

        mechanicRosterFields.append(row);

    });

    addMechanicButton.disabled =
        !isMechanicShop || mechanicNames.length >= 50;

}


// Update fields based on selected role
function updateRegistrationFields() {

    const isMechanicShop = isMechanicSelected();

    mechanicFields.forEach(field => {

        field.style.display =
            isMechanicShop ? 'grid' : 'none';

    });

    // Shop name
    shopNameInput.required = isMechanicShop;
    shopNameInput.disabled = !isMechanicShop;

    // Shop address
    serviceAddressInput.required = isMechanicShop;
    serviceAddressInput.disabled = !isMechanicShop;

    // GPS coordinates
    shopLatitudeInput.disabled = !isMechanicShop;
    shopLongitudeInput.disabled = !isMechanicShop;

    // Account name label
    accountNameLabel.textContent = isMechanicShop
        ? 'Shop owner / manager name'
        : 'Your name';

    // Render roster before updating disabled fields
    renderMechanicRoster();

    mechanicRosterFields.querySelectorAll('input')
        .forEach(input => {

            input.disabled = !isMechanicShop;
            input.required = isMechanicShop;

        });

    // Initialize map only for mechanic registration
    if (isMechanicShop) {

        window.setTimeout(() => {

            if (isMechanicSelected()) {
                initializeShopLocationMap();
            }

        }, 50);

    }

}


// Set mechanic shop GPS location
function setShopLocation(latitude, longitude) {

    if (!window.FixMotoGeography.isInside(
        {{ Js::from($cityBoundary['geometry']) }},
        latitude,
        longitude
    )) {

        shopLatitudeInput.value = '';
        shopLongitudeInput.value = '';

        if (shopMap && shopLocationMarker) {

            shopMap.removeLayer(shopLocationMarker);

            shopLocationMarker = null;

        }

        shopLocationStatus.className =
            'muted shop-location-status is-invalid';

        shopLocationStatus.textContent =
            'Choose a location within Calapan City boundaries.';

        return;

    }

    const location = [latitude, longitude];

    shopLatitudeInput.value = latitude.toFixed(7);

    shopLongitudeInput.value = longitude.toFixed(7);

    shopLocationStatus.className =
        'muted shop-location-status is-pinned';

    shopLocationStatus.textContent =
        'Shop location pinned on the map.';

    if (shopMap) {

        shopMap.setView(location, 15);

        if (shopLocationMarker) {

            shopMap.removeLayer(shopLocationMarker);

        }

        shopLocationMarker = L.marker(location)
            .addTo(shopMap)
            .bindPopup('Shop location')
            .openPopup();

    }

}


// Initialize GPS map
function initializeShopLocationMap() {

    if (!isMechanicSelected()) {
        return;
    }

    if (typeof L === 'undefined' ||
        !window.FixMotoGeography) {

        shopLocationStatus.textContent =
            'Map services are unavailable. Please refresh the page.';

        return;
    }

    if (!shopMap) {

        shopMap = L.map(shopMapElement);

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                attribution: '&copy; OpenStreetMap'
            }
        ).addTo(shopMap);

        const cityBoundaryLayer = L.geoJSON(

            {{ Js::from($cityBoundary) }},

            {
                style: {
                    color: '#145740',
                    weight: 2,
                    fillColor: '#145740',
                    fillOpacity: 0.08
                }
            }

        ).addTo(shopMap);

        shopMap.fitBounds(
            cityBoundaryLayer.getBounds()
        );

        shopMap.on('click', event => {

            setShopLocation(
                event.latlng.lat,
                event.latlng.lng
            );

        });

        const latitude =
            Number(shopLatitudeInput.value);

        const longitude =
            Number(shopLongitudeInput.value);

        if (
            shopLatitudeInput.value &&
            shopLongitudeInput.value
        ) {

            setShopLocation(latitude, longitude);

        }

    }

    shopMap.invalidateSize();

}


// Add mechanic
addMechanicButton.addEventListener('click', () => {

    if (
        isMechanicSelected() &&
        mechanicNames.length < 50
    ) {

        mechanicNames.push('');

        renderMechanicRoster();

    }

});


// Handle role selection
mechanicRoleInputs.forEach(input => {

    input.addEventListener(
        'change',
        updateRegistrationFields
    );

});


// Initialize registration form
updateRegistrationFields();

</script>

@endsection
