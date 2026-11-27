/* ==========================================
   FIXMOTO COMPLETE REGISTRATION SYSTEM
   ========================================== */

document.addEventListener('DOMContentLoaded', () => {

    const config = window.FixMotoRegistrationConfig;

    const get = id => document.getElementById(id);

    const form = get('registration-form');

    if (!form || !config) {
        console.error('Registration configuration is missing.');
        return;
    }

    const addressInput = get('service-address');
    const latitudeInput = get('shop-latitude');
    const longitudeInput = get('shop-longitude');
    const suggestions = get('address-suggestions');
    const locationStatus = get('shop-location-status');
    const modal = get('shop-map-modal');
    const mapMessage = get('shop-map-message');
    const confirmButton = get('confirm-shop-location');
    const roster = get('mechanic-roster-fields');
    const addMechanic = get('add-mechanic');

    const calapanCenter = [13.4112, 121.1803];

    let map = null;
    let marker = null;
    let boundaryLayer = null;
    let selectedLocation = null;
    let searchTimer = null;
    let searchController = null;
    let searchVersion = 0;
    let locationVersion = 0;

    const requiredElements = [
        addressInput,
        latitudeInput,
        longitudeInput,
        suggestions,
        locationStatus,
        modal,
        mapMessage,
        confirmButton,
        roster,
        addMechanic
    ];

    if (requiredElements.some(element => !element)) {
        console.error('Registration elements are missing.');
        return;
    }

    /* ======================================
       ACCOUNT ROLE
       ====================================== */

    function isMechanicRole() {

        return form.querySelector(
            'input[name="role"]:checked'
        )?.value === 'mechanic';

    }

    /* ======================================
       MECHANIC ROSTER
       ====================================== */

    const existingMechanics = Object.values(
        config.existingMechanics || {}
    );

    let mechanicNames = existingMechanics.length

        ? existingMechanics.map(
            mechanic => mechanic.name ?? ''
        )

        : [''];

    function renderMechanics() {

        roster.replaceChildren();

        mechanicNames.forEach((name, index) => {

            const row = document.createElement('div');

            row.className = 'registration-mechanic-row';

            const label = document.createElement('label');

            label.textContent = `Mechanic ${index + 1} name`;

            const input = document.createElement('input');

            input.type = 'text';
            input.name = `mechanics[${index}][name]`;
            input.placeholder = 'Enter mechanic name';
            input.value = name;
            input.maxLength = 255;
            input.disabled = !isMechanicRole();
            input.required = isMechanicRole();

            input.addEventListener('input', () => {

                mechanicNames[index] = input.value;

            });

            label.appendChild(input);

            row.appendChild(label);

            if (mechanicNames.length > 1) {

                const remove = document.createElement('button');

                remove.type = 'button';
                remove.className = 'btn outline';
                remove.textContent = 'Remove';
                remove.disabled = !isMechanicRole();

                remove.addEventListener('click', () => {

                    mechanicNames.splice(index, 1);

                    renderMechanics();

                });

                row.appendChild(remove);

            }

            roster.appendChild(row);

        });

        if (get('mechanic-count')) {

            get('mechanic-count').textContent =
                `${mechanicNames.length} ${
                    mechanicNames.length === 1
                        ? 'mechanic'
                        : 'mechanics'
                }`;

        }

        addMechanic.disabled =
            !isMechanicRole()
            || mechanicNames.length >= 50;

    }

    addMechanic.addEventListener('click', () => {

        if (mechanicNames.length < 50) {

            mechanicNames.push('');

            renderMechanics();

        }

    });

    /* ======================================
       FIX MECHANIC FIELD VISIBILITY
       ====================================== */

    function updateRegistrationFields() {

        const mechanic = isMechanicRole();

        form.querySelectorAll(
            '.mechanic-fields'
        ).forEach(section => {

            section.hidden = !mechanic;

            section.style.setProperty(
                'display',
                mechanic ? 'block' : 'none',
                'important'
            );

            section.querySelectorAll(
                'input, select, textarea, button'
            ).forEach(element => {

                element.disabled = !mechanic;

            });

        });

        get('shop-name').required = mechanic;

        addressInput.required = mechanic;

        const ownerPhone = form.querySelector(
            'input[name="phone"]'
        );

        if (ownerPhone) {
            ownerPhone.required = mechanic;
        }

        get('information-title').textContent =
            mechanic
                ? "Owner's Information"
                : 'Personal Information';

        get('account-name-label').textContent =
            mechanic
                ? "Owner's full name"
                : 'Full name';

        get('registration-submit').textContent =
            mechanic
                ? 'Register Mechanic Shop'
                : 'Create Account';

        renderMechanics();

        if (!mechanic) {

            stopSearch();

            if (modal.open) {
                modal.close();
            }

        }

        window.lucide?.createIcons();

    }

    form.querySelectorAll(
        'input[name="role"]'
    ).forEach(input => {

        input.addEventListener(
            'change',
            updateRegistrationFields
        );

    });

    /* ======================================
       DOCUMENT UPLOAD VALIDATION
       ====================================== */

    form.querySelectorAll(
        'input[type="file"]'
    ).forEach(input => {

        input.addEventListener('change', () => {

            const card = input.closest(
                '.document-upload-card'
            );

            if (!card) return;

            let status = card.querySelector('.file-status');

            if (!status) {

                status = document.createElement('p');

                status.className = 'file-status';

                status.setAttribute('role', 'status');

                card.appendChild(status);

            }

            input.setCustomValidity('');

            status.className = 'file-status';

            const file = input.files[0];

            if (!file) {

                status.textContent = 'No file selected.';

                return;

            }

            const validExtensions =
                /\.(pdf|jpg|jpeg|png)$/i;

            if (!validExtensions.test(file.name)) {

                input.value = '';

                status.className = 'file-status error';

                status.textContent =
                    'Only PDF, JPG, JPEG and PNG files are allowed.';

                return;

            }

            if (file.size > 5 * 1024 * 1024) {

                input.value = '';

                status.className = 'file-status error';

                status.textContent =
                    'Maximum file size is 5 MB.';

                return;

            }

            status.textContent =
                `Selected file: ${file.name}`;

        });

    });

    /* ======================================
       ADDRESS AUTOCOMPLETE
       ====================================== */

    function hideSuggestions() {

        suggestions.replaceChildren();

        suggestions.hidden = true;

    }

    function stopSearch() {

        clearTimeout(searchTimer);

        searchController?.abort();

        searchVersion++;

        hideSuggestions();

    }

    function clearLocation() {

        latitudeInput.value = '';

        longitudeInput.value = '';

        locationStatus.textContent =
            'Please confirm your shop location.';

        locationStatus.className =
            'shop-location-status';

    }

    function showSuggestionsMessage(message) {

        suggestions.replaceChildren();

        const paragraph = document.createElement('p');

        paragraph.className = 'address-empty-message';

        paragraph.textContent = message;

        suggestions.appendChild(paragraph);

        suggestions.hidden = false;

    }

    addressInput.addEventListener('input', () => {

        clearLocation();

        stopSearch();

        const query = addressInput.value.trim();

        if (query.length < 2) return;

        const version = searchVersion;

        searchTimer = setTimeout(() => {

            searchAddresses(query, version);

        }, 500);

    });

    async function searchAddresses(query, version) {

        searchController = new AbortController();

        showSuggestionsMessage('Searching addresses...');

        try {

            const url = new URL(
                config.searchUrl,
                window.location.origin
            );

            url.searchParams.set('q', query);

            const response = await fetch(url, {

                headers: {
                    Accept: 'application/json'
                },

                signal: searchController.signal

            });

            if (!response.ok) {

                throw new Error(
                    'Address search unavailable.'
                );

            }

            const results = await response.json();

            if (
                version !== searchVersion
                || !isMechanicRole()
            ) {
                return;
            }

            suggestions.replaceChildren();

            suggestions.hidden = false;

            if (!Array.isArray(results) || !results.length) {

                showSuggestionsMessage(
                    'No matching address found. Use the GPS map.'
                );

                return;

            }

            results.forEach(place => {

                const button = document.createElement('button');

                button.type = 'button';

                button.className = 'address-suggestion-item';

                const icon = document.createElement('i');

                icon.setAttribute('data-lucide', 'map-pin');

                const details = document.createElement('span');

                const title = document.createElement('strong');

                title.textContent = place.address;

                const subtitle = document.createElement('small');

                subtitle.textContent = place.type === 'barangay'

                    ? 'Barangay — pin exact location'

                    : 'Calapan City, Oriental Mindoro';

                details.append(title, subtitle);

                button.append(icon, details);

                button.addEventListener('click', () => {

                    stopSearch();

                    clearLocation();

                    addressInput.value = place.address;

                    if (!openMap()) return;

                    const hasCoordinates =

                        place.latitude !== null

                        && place.latitude !== undefined

                        && place.longitude !== null

                        && place.longitude !== undefined

                        && Number.isFinite(Number(place.latitude))

                        && Number.isFinite(Number(place.longitude));

                    if (hasCoordinates) {

                        selectLocation(

                            Number(place.latitude),

                            Number(place.longitude)

                        );

                    } else {

                        mapMessage.textContent =
                            'Select your exact shop location on the map.';

                    }

                });

                suggestions.appendChild(button);

            });

            window.lucide?.createIcons();

        } catch (error) {

            if (error.name === 'AbortError') return;

            if (version !== searchVersion) return;

            showSuggestionsMessage(
                'Search is unavailable. Please use the GPS map.'
            );

        }

    }

    document.addEventListener('click', event => {

        if (!event.target.closest('.shop-address-wrapper')) {

            stopSearch();

        }

    });

    addressInput.addEventListener('keydown', event => {

        if (event.key === 'Escape') {

            stopSearch();

        }

    });

    /* ======================================
       INITIALIZE LEAFLET MAP
       ====================================== */

    function initializeMap() {

        if (map) return;

        map = L.map('shop-location-map', {

            center: calapanCenter,

            zoom: 13,

            minZoom: 10,

            maxZoom: 19,

            zoomControl: true

        });

        L.tileLayer(

            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

            {
                attribution:
                    '&copy; OpenStreetMap contributors',

                maxZoom: 19
            }

        ).addTo(map);

        if (config.cityBoundary) {

            try {

                boundaryLayer = L.geoJSON(
                    config.cityBoundary,
                    {
                        interactive: false,

                        style: {
                            color: '#FF0000',
                            weight: 2,
                            fillColor: '#FF0000',
                            fillOpacity: 0.06
                        }
                    }
                ).addTo(map);

                if (boundaryLayer.getBounds().isValid()) {

                    map.fitBounds(
                        boundaryLayer.getBounds()
                    );

                }

            } catch (error) {

                console.warn(
                    'City boundary could not be displayed.',
                    error
                );

            }

        }

        map.on('click', event => {

            selectLocation(
                event.latlng.lat,
                event.latlng.lng
            );

        });

    }

    /* ======================================
       OPEN GPS MAP
       ====================================== */

    function openMap() {

        if (!isMechanicRole()) return false;

        if (typeof L === 'undefined') {

            locationStatus.textContent =
                'Map service could not be loaded.';

            locationStatus.className =
                'shop-location-status location-error';

            return false;

        }

        stopSearch();

        locationVersion++;

        selectedLocation = null;

        confirmButton.disabled = true;

        get('selected-shop-address').textContent =
            'No location selected.';

        get('selected-latitude').textContent = '—';

        get('selected-longitude').textContent = '—';

        get('selected-barangay').textContent = '—';

        mapMessage.className = 'shop-map-message';

        mapMessage.textContent =
            'Click the map to place your shop location pin.';

        if (!modal.open) {

            modal.showModal();

        }

        initializeMap();

        if (marker) {

            map.removeLayer(marker);

            marker = null;

        }

        const hasPreviousLocation =

            latitudeInput.value !== ''

            && longitudeInput.value !== '';

        requestAnimationFrame(() => {

            map.invalidateSize();

            if (!hasPreviousLocation && !marker) {

                if (
                    boundaryLayer
                    && boundaryLayer.getBounds().isValid()
                ) {

                    map.fitBounds(
                        boundaryLayer.getBounds()
                    );

                } else {

                    map.setView(calapanCenter, 13);

                }

            }

        });

        if (hasPreviousLocation) {

            selectLocation(

                Number(latitudeInput.value),

                Number(longitudeInput.value)

            );

        }

        return true;

    }

    get('open-shop-map').addEventListener(
        'click',
        openMap
    );

    /* ======================================
       MANUAL GPS PINNING
       ====================================== */

    async function selectLocation(latitude, longitude) {

        if (!map || !modal.open) return;

        const version = ++locationVersion;

        selectedLocation = null;

        confirmButton.disabled = true;

        if (

            !Number.isFinite(latitude)

            || !Number.isFinite(longitude)

            || latitude < -90

            || latitude > 90

            || longitude < -180

            || longitude > 180

        ) {

            mapMessage.textContent =
                'Invalid GPS coordinates.';

            mapMessage.className =
                'shop-map-message location-error';

            return;

        }

        const position = [latitude, longitude];

        if (!marker) {

            marker = L.marker(position, {

                draggable: true

            }).addTo(map);

            marker.on('dragstart', () => {

                selectedLocation = null;

                confirmButton.disabled = true;

                locationVersion++;

            });

            marker.on('dragend', event => {

                const coordinates =
                    event.target.getLatLng();

                selectLocation(
                    coordinates.lat,
                    coordinates.lng
                );

            });

        } else {

            marker.setLatLng(position);

        }

        map.setView(
            position,
            Math.max(map.getZoom(), 16)
        );

        get('selected-latitude').textContent =
            latitude.toFixed(7);

        get('selected-longitude').textContent =
            longitude.toFixed(7);

        get('selected-shop-address').textContent =
            'Retrieving address...';

        get('selected-barangay').textContent =
            'Checking barangay...';

        mapMessage.className = 'shop-map-message';

        mapMessage.textContent =
            'Verifying shop location...';

        try {

            const url = new URL(
                config.reverseUrl,
                window.location.origin
            );

            url.searchParams.set(
                'latitude',
                latitude
            );

            url.searchParams.set(
                'longitude',
                longitude
            );

            const response = await fetch(url, {

                headers: {
                    Accept: 'application/json'
                }

            });

            const result = await response.json()
                .catch(() => ({}));

            if (!response.ok) {

                throw new Error(

                    result.message

                    || 'Unable to verify shop location.'

                );

            }

            if (
                version !== locationVersion
                || !modal.open
            ) {
                return;
            }

            if (!result.address) {

                throw new Error(
                    'No address was returned.'
                );

            }

            selectedLocation = {

                latitude,

                longitude,

                address: result.address,

                barangay: result.barangay

            };

            get('selected-shop-address').textContent =
                result.address;

            get('selected-barangay').textContent =
                result.barangay ?? 'Not identified';

            mapMessage.textContent =
                'Location selected. Drag the pin to adjust if necessary.';

            mapMessage.className =
                'shop-map-message location-confirmed';

            confirmButton.disabled = false;

        } catch (error) {

            if (
                version !== locationVersion
                || !modal.open
            ) {
                return;
            }

            selectedLocation = null;

            confirmButton.disabled = true;

            get('selected-shop-address').textContent =
                'Location not verified';

            get('selected-barangay').textContent = '—';

            mapMessage.textContent = error.message;

            mapMessage.className =
                'shop-map-message location-error';

        }

    }

    /* ======================================
       CONFIRM SHOP LOCATION
       ====================================== */

    confirmButton.addEventListener('click', () => {

        if (!selectedLocation) return;

        latitudeInput.value =
            selectedLocation.latitude.toFixed(7);

        longitudeInput.value =
            selectedLocation.longitude.toFixed(7);

        addressInput.value =
            selectedLocation.address;

        locationStatus.textContent =

            'Shop location successfully confirmed.'

            + (

                selectedLocation.barangay

                    ? ` Barangay: ${selectedLocation.barangay}`

                    : ''

            );

        locationStatus.className =
            'shop-location-status location-confirmed';

        hideSuggestions();

        modal.close();

    });

    /* ======================================
       CLOSE GPS MODAL
       ====================================== */

    function closeMap() {

        locationVersion++;

        selectedLocation = null;

        modal.close();

    }

    get('close-shop-map').addEventListener(
        'click',
        closeMap
    );

    get('cancel-shop-location').addEventListener(
        'click',
        closeMap
    );

    modal.addEventListener('close', () => {

        locationVersion++;

        selectedLocation = null;

        confirmButton.disabled = true;

    });

    /* ======================================
       REGISTRATION VALIDATION
       ====================================== */

    form.addEventListener('submit', event => {

        if (!isMechanicRole()) return;

        if (!latitudeInput.value || !longitudeInput.value) {

            event.preventDefault();

            locationStatus.textContent =
                'Please confirm your shop location before registering.';

            locationStatus.className =
                'shop-location-status location-error';

            openMap();

            return;

        }

    });

    /* ======================================
       INITIALIZE REGISTRATION
       ====================================== */

    updateRegistrationFields();

    window.lucide?.createIcons();

    console.log(
        'FIXMOTO registration initialized successfully.'
    );

});