@extends('layouts.app')
@section('title', 'Request Roadside Help')
@section('page_title', 'Request roadside help')

@section('content')

<div class="page">

    {{-- Breadcrumb --}}
    <nav class="rider-breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li>
                <a href="{{ route('rider.dashboard') }}">
                    Dashboard
                </a>
            </li>
            <li aria-current="page">Request help</li>
        </ol>
    </nav>

    <div class="card rider-request-card">

        <h2>Request roadside help</h2>

        <p class="muted request-intro">
            Complete these three steps to get help.
        </p>

        {{-- Validation errors --}}
        @if($errors->any())
            <div class="alert error rider-error" role="alert">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Request stepper --}}
        <nav class="request-stepper" aria-label="Request steps">

            <button
                type="button"
                class="stepper-step active"
                data-step-target="0"
                aria-current="step">

                <span>1</span>
                <b>Select Vehicle</b>

            </button>

            <i aria-hidden="true"></i>

            <button
                type="button"
                class="stepper-step"
                data-step-target="1">

                <span>2</span>
                <b>Select Services</b>

            </button>

            <i aria-hidden="true"></i>

            <button
                type="button"
                class="stepper-step"
                data-step-target="2">

                <span>3</span>
                <b>Location &amp; Notes</b>

            </button>

        </nav>


        <form
            id="service-request-form"
            method="POST"
            action="{{ route('rider.requests.store') }}"
            class="request-wizard"
            novalidate>

            @csrf


            {{-- STEP 1: SELECT VEHICLE --}}
            <section
                class="wizard-panel"
                data-wizard-step="0">

                <label for="request-vehicle">
                    Select Vehicle
                </label>

                <select
                    id="request-vehicle"
                    name="vehicle_id"
                    required>

                    <option value="">
                        Choose one of your vehicles
                    </option>

                    @foreach($vehicles as $vehicle)

                        <option
                            value="{{ $vehicle->id }}"
                            @selected(old('vehicle_id') == $vehicle->id)>

                            {{ $vehicleCategories[$vehicle->vehicle_category] ?? 'Vehicle' }}
                            ·
                            {{ $vehicle->brand }}
                            {{ $vehicle->model }}
                            ({{ $vehicle->year }})

                        </option>

                    @endforeach

                </select>

                @if($vehicles->isEmpty())

                    <p class="wizard-hint">

                        Add your vehicle on the

                        <a href="{{ route('rider.vehicles.index') }}">
                            Vehicles page
                        </a>

                        before requesting service.

                    </p>

                @endif

                <div class="wizard-actions">

                    <span></span>

                    <button
                        type="button"
                        class="btn primary"
                        data-next>

                        Continue to services

                    </button>

                </div>

            </section>


            {{-- STEP 2: SELECT SERVICES --}}
            <section
                class="wizard-panel"
                data-wizard-step="1"
                hidden>

                <fieldset class="service-fieldset">

                    <legend>Select Services</legend>

                    <p class="wizard-hint">
                        Choose all the services you need.
                    </p>

                    <div class="service-options">

                        @foreach($availableServices as $service)

                            <label class="service-option">

                                <input
                                    type="checkbox"
                                    name="services[]"
                                    value="{{ $service }}"
                                    @checked(in_array($service, old('services', []), true))>

                                <span class="service-option-icon">

                                    <i data-lucide="{{ $service === 'Flat tire'
                                        ? 'circle-dot'
                                        : ($service === 'Battery problem'
                                            ? 'battery-warning'
                                            : 'wrench') }}">
                                    </i>

                                </span>

                                <span class="service-option-label">
                                    {{ $service }}
                                </span>

                                <span
                                    class="service-check"
                                    aria-hidden="true">

                                    <i data-lucide="check"></i>

                                </span>

                            </label>

                        @endforeach

                    </div>

                </fieldset>

                <div class="wizard-actions">

                    <button
                        type="button"
                        class="btn outline"
                        data-back>

                        Back

                    </button>

                    <button
                        type="button"
                        class="btn primary"
                        data-next>

                        Continue to location

                    </button>

                </div>

            </section>


            {{-- STEP 3: LOCATION AND NOTES --}}
            <section
                class="wizard-panel"
                data-wizard-step="2"
                hidden>

                <div class="pin-heading">

                    <div>

                        <h3>Pin Location</h3>

                        <p class="wizard-hint">

                            Use your current GPS location
                            or manually select a location
                            on the map.

                        </p>

                    </div>

                    <button
                        id="gps-button"
                        type="button"
                        class="btn outline gps-button"
                        onclick="locateRider()">

                        <i data-lucide="crosshair"></i>

                        Use GPS

                    </button>

                </div>


                {{-- GPS map --}}
                <div
                    id="request-location-map"
                    class="request-location-map"
                    role="application"
                    aria-label="Choose service location on map">
                </div>


                {{-- GPS status --}}
                <p
                    id="gps-status"
                    class="wizard-hint"
                    role="status"
                    aria-live="polite">

                    No location selected yet.

                </p>


                {{-- GPS coordinate information --}}
                <div
                    id="gps-details"
                    style="display:none; margin:12px 0;">

                    <p>
                        <strong>Latitude:</strong>
                        <span id="display-latitude">—</span>
                    </p>

                    <p>
                        <strong>Longitude:</strong>
                        <span id="display-longitude">—</span>
                    </p>

                    <p>
                        <strong>Location accuracy:</strong>
                        <span id="display-accuracy">—</span>
                    </p>

                </div>


                {{-- Hidden GPS fields --}}
                <input
                    id="latitude"
                    name="latitude"
                    type="hidden"
                    value="{{ old('latitude') }}">

                <input
                    id="longitude"
                    name="longitude"
                    type="hidden"
                    value="{{ old('longitude') }}">


                {{-- Nearby landmark --}}
                <label for="landmark">

                    Nearby landmark

                    <span class="muted">
                        (optional)
                    </span>

                    <input
                        id="landmark"
                        name="landmark"
                        value="{{ old('landmark') }}"
                        maxlength="255"
                        placeholder="Street, building, or nearby landmark">

                </label>


                {{-- Additional notes --}}
                <label for="request-notes">

                    Add Notes

                    <span class="muted">
                        (optional)
                    </span>

                    <textarea
                        id="request-notes"
                        name="notes"
                        maxlength="2000"
                        placeholder="Describe any helpful details about your vehicle or location">{{ old('notes') }}</textarea>

                </label>


                <div class="wizard-actions">

                    <button
                        type="button"
                        class="btn outline"
                        data-back>

                        Back

                    </button>

                    <button
                        id="submit-request"
                        type="submit"
                        class="btn amber">

                        Request assistance

                    </button>

                </div>

            </section>


            {{-- Wizard error message --}}
            <p
                class="wizard-error"
                id="wizard-error"
                role="alert"
                hidden>
            </p>

        </form>

    </div>

</div>


<script>

const requestForm =
    document.getElementById('service-request-form');

const wizardPanels = [
    ...document.querySelectorAll('[data-wizard-step]')
];

const wizardSteps = [
    ...document.querySelectorAll('.stepper-step')
];

const wizardError =
    document.getElementById('wizard-error');

const latitudeInput =
    document.getElementById('latitude');

const longitudeInput =
    document.getElementById('longitude');

const gpsStatus =
    document.getElementById('gps-status');

const gpsButton =
    document.getElementById('gps-button');

const gpsDetails =
    document.getElementById('gps-details');

const submitButton =
    document.getElementById('submit-request');


let currentWizardStep = 0;

let requestMap = null;

let requestLocationMarker = null;

let accuracyCircle = null;

let isLocating = false;

let locationRequestId = 0;


// Calapan service area coordinate limits
const serviceArea = {

    minLatitude: 13.1,

    maxLatitude: 13.7,

    minLongitude: 121.1,

    maxLongitude: 121.5

};


// Validate selected coordinates
function isValidServiceLocation(latitude, longitude) {

    return (

        Number.isFinite(latitude) &&

        Number.isFinite(longitude) &&

        latitude >= serviceArea.minLatitude &&

        latitude <= serviceArea.maxLatitude &&

        longitude >= serviceArea.minLongitude &&

        longitude <= serviceArea.maxLongitude

    );

}


// Display location status
function showGpsStatus(message, type = 'normal') {

    gpsStatus.textContent = message;

    if (type === 'success') {

        gpsStatus.style.color = '#145740';

    } else if (type === 'error') {

        gpsStatus.style.color = '#dc2626';

    } else {

        gpsStatus.style.color = '';

    }

}


// Clear invalid location
function clearRiderLocation() {

    latitudeInput.value = '';

    longitudeInput.value = '';

    gpsDetails.style.display = 'none';

    if (requestMap && requestLocationMarker) {

        requestMap.removeLayer(requestLocationMarker);

        requestLocationMarker = null;

    }

    if (requestMap && accuracyCircle) {

        requestMap.removeLayer(accuracyCircle);

        accuracyCircle = null;

    }

}


// Set selected rider location
function setRiderLocation(
    latitude,
    longitude,
    accuracy = null,
    source = 'manual'
) {

    if (!isValidServiceLocation(latitude, longitude)) {

        clearRiderLocation();

        showGpsStatus(

            'The selected location is outside the FIXMOTO service area. Please select a location within the Calapan service area.',

            'error'

        );

        return false;

    }

    const location = [latitude, longitude];


    // Store coordinates
    latitudeInput.value = latitude.toFixed(7);

    longitudeInput.value = longitude.toFixed(7);


    // Display coordinates
    document.getElementById(
        'display-latitude'
    ).textContent = latitude.toFixed(7);

    document.getElementById(
        'display-longitude'
    ).textContent = longitude.toFixed(7);

    document.getElementById(
        'display-accuracy'
    ).textContent = Number.isFinite(accuracy)

        ? `Approximately ${Math.round(accuracy)} meters`

        : 'Manually selected location';

    gpsDetails.style.display = 'block';


    // Update map marker
    if (requestMap) {

        requestMap.setView(location, 16);

        if (requestLocationMarker) {

            requestMap.removeLayer(
                requestLocationMarker
            );

        }

        if (accuracyCircle) {

            requestMap.removeLayer(accuracyCircle);

            accuracyCircle = null;

        }

        requestLocationMarker = L.marker(location, {

            draggable: true

        })

            .addTo(requestMap)

            .bindPopup('Selected service location')

            .openPopup();


        // Allow rider to adjust selected location
        requestLocationMarker.on('dragend', event => {

            locationRequestId++;

            finishGpsRequest();

            const position = event.target.getLatLng();

            setRiderLocation(

                position.lat,

                position.lng,

                null,

                'manual'

            );

        });


        // Display GPS accuracy radius
        if (
            Number.isFinite(accuracy) &&
            accuracy > 0
        ) {

            accuracyCircle = L.circle(location, {

                radius: accuracy,

                color: '#145740',

                weight: 1,

                fillColor: '#145740',

                fillOpacity: 0.10

            }).addTo(requestMap);

        }

    }


    // Display confirmation message
    if (source === 'gps') {

        showGpsStatus(

            'Current GPS location detected. Confirm that the pin matches your actual location.',

            'success'

        );

    } else {

        showGpsStatus(

            'Service location successfully pinned on the map.',

            'success'

        );

    }

    return true;

}


// Initialize OpenStreetMap
function initializeRequestMap() {

    if (typeof L === 'undefined') {

        showGpsStatus(

            'Map service is unavailable. Please refresh the page.',

            'error'

        );

        return;

    }

    if (!requestMap) {

        requestMap = L.map(

            'request-location-map'

        ).setView(

            [13.4112, 121.1803],

            13

        );


        L.tileLayer(

            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

            {

                attribution: '&copy; OpenStreetMap contributors',

                maxZoom: 19

            }

        ).addTo(requestMap);


        // Restore previously selected coordinates
        if (
            latitudeInput.value &&
            longitudeInput.value
        ) {

            setRiderLocation(

                Number(latitudeInput.value),

                Number(longitudeInput.value)

            );

        }


        // Allow manual map selection
        requestMap.on('click', event => {

            locationRequestId++;

            finishGpsRequest();

            setRiderLocation(

                event.latlng.lat,

                event.latlng.lng,

                null,

                'manual'

            );

        });

    }

    window.setTimeout(() => {

        requestMap.invalidateSize();

    }, 100);

}


// Display selected wizard step
function showWizardStep(step) {

    currentWizardStep = step;


    wizardPanels.forEach((panel, index) => {

        panel.hidden = index !== step;

    });


    wizardSteps.forEach((button, index) => {

        button.classList.toggle(

            'active',

            index === step

        );

        button.classList.toggle(

            'complete',

            index < step

        );

        if (index === step) {

            button.setAttribute(

                'aria-current',

                'step'

            );

        } else {

            button.removeAttribute(

                'aria-current'

            );

        }

        button.disabled = index > step;

    });


    wizardError.hidden = true;


    if (step === 2) {

        initializeRequestMap();

    }

}


// Validate each request step
function validateWizardStep(step) {


    // STEP 1: Vehicle validation
    if (
        step === 0 &&
        !document.getElementById('request-vehicle').value
    ) {

        return 'Please select a vehicle to continue.';

    }


    // STEP 2: Service validation
    if (
        step === 1 &&
        !requestForm.querySelector(
            'input[name="services[]"]:checked'
        )
    ) {

        return 'Select at least one service to continue.';

    }


    // STEP 3: Location validation
    if (step === 2) {

        if (isLocating) {

            return 'Please wait while your GPS location is being detected.';

        }

        const latitude = Number(latitudeInput.value);

        const longitude = Number(longitudeInput.value);

        if (

            !latitudeInput.value ||

            !longitudeInput.value ||

            !isValidServiceLocation(
                latitude,
                longitude
            )

        ) {

            return 'Pin a valid service location within the Calapan service area.';

        }

    }

    return null;

}


// Continue button
requestForm.querySelectorAll('[data-next]')
    .forEach(button => {

        button.addEventListener('click', () => {

            const error = validateWizardStep(

                currentWizardStep

            );

            if (error) {

                wizardError.textContent = error;

                wizardError.hidden = false;

                return;

            }

            showWizardStep(

                Math.min(

                    currentWizardStep + 1,

                    wizardPanels.length - 1

                )

            );

        });

    });


// Back button
requestForm.querySelectorAll('[data-back]')
    .forEach(button => {

        button.addEventListener('click', () => {

            showWizardStep(

                Math.max(

                    currentWizardStep - 1,

                    0

                )

            );

        });

    });


// Stepper navigation
wizardSteps.forEach(button => {

    button.addEventListener('click', () => {

        const target = Number(

            button.dataset.stepTarget

        );

        if (target <= currentWizardStep) {

            showWizardStep(target);

        }

    });

});


// Reset GPS button
function finishGpsRequest() {

    isLocating = false;

    gpsButton.disabled = false;

    gpsButton.innerHTML = `

        <i data-lucide="crosshair"></i>

        Use GPS

    `;

    if (window.lucide) {

        window.lucide.createIcons();

    }

}


// Get actual device GPS location
function locateRider() {

    if (isLocating) {

        return;

    }


    // Secure connection validation
    if (!window.isSecureContext) {

        showGpsStatus(

            'GPS requires HTTPS or localhost. Open FIXMOTO using a secure connection, or manually select a location on the map.',

            'error'

        );

        return;

    }


    // Browser support validation
    if (!('geolocation' in navigator)) {

        showGpsStatus(

            'Your browser does not support GPS location. Please select your location on the map.',

            'error'

        );

        return;

    }


    isLocating = true;

    const requestId = ++locationRequestId;

    gpsButton.disabled = true;

    gpsButton.textContent = 'Locating...';

    showGpsStatus(

        'Requesting your current GPS location. Please allow location access.'

    );


    navigator.geolocation.getCurrentPosition(

        // Successfully detected GPS location
        position => {

            if (requestId !== locationRequestId) {

                return;

            }

            const latitude = position.coords.latitude;

            const longitude = position.coords.longitude;

            const accuracy = position.coords.accuracy;

            const locationAccepted = setRiderLocation(

                latitude,

                longitude,

                accuracy,

                'gps'

            );

            if (
                locationAccepted &&
                accuracy > 100
            ) {

                showGpsStatus(

                    `GPS accuracy is approximately ${Math.round(accuracy)} meters. Please adjust the map pin if necessary.`

                );

            }

            finishGpsRequest();

        },


        // GPS error handling
        error => {

            if (requestId !== locationRequestId) {

                return;

            }

            const messages = {

                1: 'Location permission denied. Allow location access in your browser settings or manually pin your location.',

                2: 'Your current location is unavailable. Enable your device location services and try again.',

                3: 'GPS request timed out. Try again or manually select your location on the map.'

            };

            showGpsStatus(

                messages[error.code] ||

                'Unable to retrieve your current location.',

                'error'

            );

            finishGpsRequest();

        },


        // Improved GPS configuration
        {

            enableHighAccuracy: true,

            timeout: 30000,

            maximumAge: 0

        }

    );

}


// Validate form before submission
requestForm.addEventListener('submit', event => {

    for (
        let step = 0;
        step < wizardPanels.length;
        step++
    ) {

        const error = validateWizardStep(step);

        if (error) {

            event.preventDefault();

            showWizardStep(step);

            wizardError.textContent = error;

            wizardError.hidden = false;

            return;

        }

    }


    // Prevent duplicate service requests
    submitButton.disabled = true;

    submitButton.textContent = 'Sending request...';

});


// Make GPS function accessible to the button
window.locateRider = locateRider;


// Initialize request wizard
showWizardStep(0);

</script>

@endsection
