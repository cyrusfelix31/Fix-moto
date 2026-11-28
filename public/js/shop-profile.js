/* ==========================================
   FIXMOTO SHOP PROFILE FUNCTIONALITY
   ========================================== */

document.addEventListener('DOMContentLoaded', () => {

    const configElement = document.getElementById(
        'sp-shop-profile-config'
    );
    const config = configElement
        ? JSON.parse(configElement.textContent)
        : window.FixMotoShopProfile;
    const get = id => document.getElementById(id);

    if (!config || !get('sp-main-form')) return;

    const refreshIcons = () => {
        window.lucide?.createIcons();
    };


    /* ======================================
       OPEN AND CLOSE MODALS
       ====================================== */

    document.querySelectorAll('[data-open]')
        .forEach(button => {

            button.addEventListener('click', () => {

                if (button.disabled) return;

                const dialog = get(button.dataset.open);

                if (dialog && !dialog.open) {
                    dialog.showModal();
                }

            });

        });

    document.querySelectorAll('[data-close]')
        .forEach(button => {

            button.addEventListener('click', () => {
                button.closest('dialog')?.close();
            });

        });


    /* ======================================
       MECHANIC TEAM NAVIGATION
       ====================================== */

    const query = new URLSearchParams(
        window.location.search
    );

    if (query.get('add_member') === '1') {

        const button = document.querySelector(
            '[data-open="sp-member-dialog-new"]'
        );

        if (button && !button.disabled) {
            get('sp-member-dialog-new')?.showModal();
        }

    }

    const editMember = query.get('edit_member');

    if (editMember && /^\d+$/.test(editMember)) {
        get(`sp-member-dialog-${editMember}`)?.showModal();
    }


    /* ======================================
       PROFILE PICTURE VALIDATION
       ====================================== */

    document.querySelectorAll(
        '.sp-dialog input[type="file"]'
    ).forEach(input => {

        input.addEventListener('change', () => {

            input.setCustomValidity('');

            const file = input.files[0];

            if (!file) return;

            const allowedExtensions =
                /\.(jpg|jpeg|png|webp)$/i;

            if (!allowedExtensions.test(file.name)) {

                input.value = '';

                alert(
                    'Only JPG, JPEG, PNG and WEBP files are allowed.'
                );

                return;

            }

            if (file.size > 5 * 1024 * 1024) {

                input.value = '';

                alert(
                    'Profile picture must not exceed 5 MB.'
                );

            }

        });

    });


    /* ======================================
       SHOP SERVICE VARIABLES
       ====================================== */

    const serviceList = get('sp-service-list');
    const serviceSelect = get('sp-service-select');
    const customService = get('sp-custom-service');
    const serviceError = get('sp-service-error');
    const serviceDialog = get('sp-service-dialog');


    /* ======================================
       NORMALIZE SERVICE NAME
       ====================================== */

    function normalizeService(value) {

        const name = String(value || '').trim();

        const match = Object.entries(
            config.serviceLabels || {}
        ).find(([key, label]) => {

            return key.toLowerCase() === name.toLowerCase()
                || label.toLowerCase() === name.toLowerCase();

        });

        return match ? match[0] : name;

    }


    /* ======================================
       EMPTY SERVICE STATE
       ====================================== */

    function updateEmptyServices() {

        get('sp-empty-services').hidden =
            serviceList.querySelectorAll(
                '[data-service]'
            ).length > 0;

    }


    /* ======================================
       CREATE LUCIDE ICON
       ====================================== */

    function createIcon(name) {

        const icon = document.createElement('i');

        icon.setAttribute('data-lucide', name);

        return icon;

    }


    /* ======================================
       ADD SHOP SERVICE
       ====================================== */

    function addService(name) {

        const service = normalizeService(name);

        if (!service) {

            serviceError.textContent =
                'Please select or enter a service.';

            return;

        }

        if (service.length > 100) {

            serviceError.textContent =
                'Service name must not exceed 100 characters.';

            return;

        }

        const existing = Array.from(
            serviceList.querySelectorAll('[data-service]')
        );

        if (existing.length >= 50) {

            serviceError.textContent =
                'Maximum of 50 services allowed.';

            return;

        }

        const duplicate = existing.some(row => {

            return normalizeService(
                row.dataset.service
            ).toLowerCase() === service.toLowerCase();

        });

        if (duplicate) {

            serviceError.textContent =
                'This service is already added.';

            return;

        }

        const row = document.createElement('div');

        row.className = 'sp-service-row';

        row.dataset.service = service;

        const text = document.createElement('span');

        text.textContent =
            config.serviceLabels?.[service] || service;

        const input = document.createElement('input');

        input.type = 'hidden';
        input.name = 'services[]';
        input.value = service;

        const check = createIcon('circle-check');

        check.className = 'sp-service-check';

        const remove = document.createElement('button');

        remove.type = 'button';
        remove.className = 'sp-service-remove';

        remove.setAttribute('data-remove-service', '');

        remove.setAttribute(
            'aria-label',
            `Remove ${service}`
        );

        remove.title = 'Remove service';

        remove.appendChild(
            createIcon('x')
        );

        row.append(
            createIcon('wrench'),
            text,
            input,
            check,
            remove
        );

        serviceList.appendChild(row);

        updateEmptyServices();

        refreshIcons();

        serviceSelect.value = '';
        customService.value = '';
        serviceError.textContent = '';

        serviceDialog.close();

    }


    /* ======================================
       SERVICE BUTTON EVENTS
       ====================================== */

    get('sp-add-service').addEventListener('click', () => {

        const name =
            customService.value.trim()
            || serviceSelect.value;

        addService(name);

    });

    serviceSelect.addEventListener('change', () => {

        customService.value = '';
        serviceError.textContent = '';

    });

    customService.addEventListener('input', () => {

        if (customService.value.trim()) {
            serviceSelect.value = '';
        }

        serviceError.textContent = '';

    });

    customService.addEventListener('keydown', event => {

        if (event.key === 'Enter') {

            event.preventDefault();

            addService(customService.value);

        }

    });


    /* ======================================
       REMOVE SHOP SERVICE
       ====================================== */

    serviceList.addEventListener('click', event => {

        const button = event.target.closest(
            '[data-remove-service]'
        );

        if (!button) return;

        const row = button.closest('[data-service]');

        if (!row) return;

        const confirmed = window.confirm(
            `Remove ${row.dataset.service} from your shop services?`
        );

        if (!confirmed) return;

        row.remove();

        updateEmptyServices();

    });

    serviceDialog.addEventListener('close', () => {

        serviceError.textContent = '';
        serviceSelect.value = '';
        customService.value = '';

    });

    updateEmptyServices();


    /* ======================================
       GPS MAP VARIABLES
       ====================================== */

    const mapDialog = get('sp-location-dialog');
    const searchInput = get('sp-map-search');
    const searchResults = get('sp-map-results');
    const mapMessage = get('sp-map-message');
    const confirmButton = get('sp-confirm-location');

    const latitudeInput = get('sp-latitude');
    const longitudeInput = get('sp-longitude');
    const addressInput = get('sp-address');

    const calapanCenter = [13.4112, 121.1803];

    let map = null;
    let marker = null;
    let boundaryLayer = null;
    let pendingLocation = null;

    let searchTimer = null;
    let searchVersion = 0;
    let locationVersion = 0;
    let searchController = null;


    /* ======================================
       INITIALIZE LEAFLET MAP
       ====================================== */

    function initializeMap() {

        if (map) return;

        map = L.map('sp-map', {

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
                            color: '#145740',
                            weight: 2,
                            fillColor: '#145740',
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
                    'Unable to display Calapan boundary.',
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
       CREATE DRAGGABLE MAP MARKER
       ====================================== */

    function placeMarker(latitude, longitude) {

        const position = [latitude, longitude];

        if (!marker) {

            marker = L.marker(
                position,
                { draggable: true }
            ).addTo(map);

            marker.on('dragstart', () => {

                locationVersion++;

                pendingLocation = null;

                confirmButton.disabled = true;

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

    }


    /* ======================================
       STOP ADDRESS SEARCH
       ====================================== */

    function stopSearch() {

        clearTimeout(searchTimer);

        searchController?.abort();

        searchVersion++;

        searchResults.replaceChildren();

        searchResults.hidden = true;

    }


    /* ======================================
       OPEN GPS LOCATION MODAL
       ====================================== */

    function openMap() {

        if (typeof L === 'undefined') {

            get('sp-location-status').textContent =
                'Map service could not be loaded.';

            return;

        }

        pendingLocation = null;

        locationVersion++;

        confirmButton.disabled = true;

        stopSearch();

        searchInput.value = '';

        get('sp-selected-address').textContent =
            'No new location selected.';

        get('sp-selected-latitude').textContent = '—';

        get('sp-selected-longitude').textContent = '—';

        mapMessage.textContent =
            'Click the map or search for your shop location.';

        mapMessage.className = 'sp-muted';

        if (!mapDialog.open) {
            mapDialog.showModal();
        }

        initializeMap();

        if (marker) {

            map.removeLayer(marker);

            marker = null;

        }

        requestAnimationFrame(() => {

            map.invalidateSize();

            const latitude = Number(latitudeInput.value);

            const longitude = Number(longitudeInput.value);

            if (

                latitudeInput.value !== ''

                && longitudeInput.value !== ''

                && Number.isFinite(latitude)

                && Number.isFinite(longitude)

            ) {

                placeMarker(latitude, longitude);

                map.setView(
                    [latitude, longitude],
                    16
                );

                get('sp-selected-address').textContent =
                    addressInput.value;

                get('sp-selected-latitude').textContent =
                    latitude.toFixed(7);

                get('sp-selected-longitude').textContent =
                    longitude.toFixed(7);

                mapMessage.textContent =
                    'This is your current shop location. Move the pin to update it.';

            } else if (

                boundaryLayer

                && boundaryLayer.getBounds().isValid()

            ) {

                map.fitBounds(
                    boundaryLayer.getBounds()
                );

            } else {

                map.setView(calapanCenter, 13);

            }

        });

    }

    get('sp-open-map').addEventListener(
        'click',
        openMap
    );


    /* ======================================
       VERIFY GPS LOCATION
       ====================================== */

    async function selectLocation(latitude, longitude) {

        if (!mapDialog.open || !map) return;

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

            mapMessage.className = 'sp-form-error';

            return;

        }

        const version = ++locationVersion;

        pendingLocation = null;

        confirmButton.disabled = true;

        placeMarker(latitude, longitude);

        get('sp-selected-latitude').textContent =
            latitude.toFixed(7);

        get('sp-selected-longitude').textContent =
            longitude.toFixed(7);

        get('sp-selected-address').textContent =
            'Checking address...';

        mapMessage.textContent =
            'Verifying location within Calapan City...';

        mapMessage.className = 'sp-muted';

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

            if (

                version !== locationVersion

                || !mapDialog.open

            ) return;

            if (!response.ok || !result.address) {

                throw new Error(

                    result.message

                    || 'Unable to verify location.'

                );

            }

            pendingLocation = {

                latitude,

                longitude,

                address: result.address,

                barangay: result.barangay

            };

            get('sp-selected-address').textContent =
                result.address;

            mapMessage.textContent =
                'Location verified. Confirm your shop location.';

            mapMessage.className = 'sp-muted';

            confirmButton.disabled = false;

        } catch (error) {

            if (

                version !== locationVersion

                || !mapDialog.open

            ) return;

            pendingLocation = null;

            confirmButton.disabled = true;

            get('sp-selected-address').textContent =
                'Location not verified.';

            mapMessage.textContent = error.message;

            mapMessage.className = 'sp-form-error';

        }

    }


    /* ======================================
       ADDRESS AUTOCOMPLETE
       ====================================== */

    function showSearchMessage(message) {

        const paragraph = document.createElement('p');

        paragraph.textContent = message;

        searchResults.replaceChildren(paragraph);

        searchResults.hidden = false;

    }

    searchInput.addEventListener('input', () => {

        stopSearch();

        const keyword = searchInput.value.trim();

        if (keyword.length < 2) return;

        const version = searchVersion;

        searchTimer = setTimeout(() => {

            searchAddresses(keyword, version);

        }, 450);

    });


    /* ======================================
       SEARCH CALAPAN ADDRESSES
       ====================================== */

    async function searchAddresses(keyword, version) {

        searchController = new AbortController();

        showSearchMessage('Searching addresses...');

        try {

            const url = new URL(
                config.searchUrl,
                window.location.origin
            );

            url.searchParams.set('q', keyword);

            const response = await fetch(url, {

                headers: {
                    Accept: 'application/json'
                },

                signal: searchController.signal

            });

            if (!response.ok) {

                throw new Error(
                    'Search is unavailable.'
                );

            }

            const results = await response.json();

            if (

                version !== searchVersion

                || !mapDialog.open

            ) return;

            searchResults.replaceChildren();

            searchResults.hidden = false;

            if (!Array.isArray(results) || !results.length) {

                showSearchMessage(
                    'No matching address found. Use the map.'
                );

                return;

            }

            results.forEach(place => {

                const button =
                    document.createElement('button');

                button.type = 'button';

                button.className = 'sp-map-result';

                button.textContent = place.address;

                button.addEventListener('click', () => {

                    searchInput.value = place.address;

                    stopSearch();

                    const hasCoordinates =

                        place.latitude !== null

                        && place.latitude !== undefined

                        && place.longitude !== null

                        && place.longitude !== undefined

                        && Number.isFinite(
                            Number(place.latitude)
                        )

                        && Number.isFinite(
                            Number(place.longitude)
                        );

                    if (hasCoordinates) {

                        const latitude =
                            Number(place.latitude);

                        const longitude =
                            Number(place.longitude);

                        map.setView(
                            [latitude, longitude],
                            16
                        );

                        selectLocation(
                            latitude,
                            longitude
                        );

                    } else {

                        pendingLocation = null;

                        locationVersion++;

                        confirmButton.disabled = true;

                        mapMessage.textContent =
                            'Barangay selected. Pin the exact shop location on the map.';

                        mapMessage.className = 'sp-muted';

                    }

                });

                searchResults.appendChild(button);

            });

        } catch (error) {

            if (error.name === 'AbortError') return;

            if (version !== searchVersion) return;

            showSearchMessage(
                'Search is unavailable. Please use the GPS map.'
            );

        }

    }


    /* ======================================
       CLOSE ADDRESS SUGGESTIONS
       ====================================== */

    searchInput.addEventListener('keydown', event => {

        if (event.key === 'Escape') {

            stopSearch();

        }

    });

    mapDialog.addEventListener('click', event => {

        if (!event.target.closest('.sp-search-wrapper')) {

            stopSearch();

        }

    });


    /* ======================================
       CONFIRM GPS LOCATION
       ====================================== */

    confirmButton.addEventListener('click', () => {

        if (!pendingLocation) return;

        latitudeInput.value =
            pendingLocation.latitude.toFixed(7);

        longitudeInput.value =
            pendingLocation.longitude.toFixed(7);

        addressInput.value =
            pendingLocation.address;

        get('sp-address-display').textContent =
            pendingLocation.address;

        get('sp-location-status').textContent =
            'GPS location confirmed. Save changes to update your shop.';

        mapDialog.close();

    });


    /* ======================================
       CLOSE GPS MODAL
       ====================================== */

    mapDialog.addEventListener('close', () => {

        stopSearch();

        locationVersion++;

        pendingLocation = null;

        confirmButton.disabled = true;

    });

    window.addEventListener('resize', () => {

        if (map && mapDialog.open) {

            map.invalidateSize();

        }

    });


    /* ======================================
       SAVE SHOP PROFILE
       ====================================== */

    get('sp-main-form').addEventListener('submit', event => {

        if (

            !latitudeInput.value

            || !longitudeInput.value

        ) {

            event.preventDefault();

            get('sp-location-status').textContent =
                'Please select your shop location first.';

            openMap();

            return;

        }

        const button = get('sp-save-button');

        button.disabled = true;

        button.textContent = 'Saving Changes...';

    });


    /* ======================================
       INITIALIZE SHOP PROFILE
       ====================================== */

    refreshIcons();

    console.log(
        'FIXMOTO Shop Profile loaded successfully.'
    );

});
