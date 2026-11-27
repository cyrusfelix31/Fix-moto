@extends('layouts.app')
@section('title', 'Shop Profile')
@section('page_title', 'Shop profile')
@section('content')
<div class="page mechanic-page shop-profile-page">
    @if($errors->any())
        <div class="alert error profile-alert" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <section class="profile-panel profile-shop-heading">
        <div class="profile-shop-brand">
            <div class="profile-shop-logo">
                @if($profile->logo_path)<img src="{{ Storage::disk('public')->url($profile->logo_path) }}" alt="{{ $profile->shop_name }} logo">@else<i data-lucide="store"></i>@endif
            </div>
            <div class="profile-shop-copy">
                <span class="profile-eyebrow">SHOP PROFILE</span>
                <h2>{{ $profile->shop_name ?: 'Your mechanic shop' }}</h2>
                <p><i data-lucide="map-pin"></i>{{ $profile->service_address }}</p>
                <span class="profile-verification {{ $profile->is_verified ? 'verified' : 'pending' }}"><i data-lucide="{{ $profile->is_verified ? 'badge-check' : 'clock-3' }}"></i>{{ $profile->is_verified ? 'Verified shop' : ucfirst(str_replace('_', ' ', $profile->verification_status ?? 'Pending verification')) }}</span>
            </div>
        </div>
        <button type="button" class="btn primary" data-scroll-to="shop-location-form"><i data-lucide="pencil"></i>Edit Shop Profile</button>
    </section>

    <section class="profile-panel profile-owner-panel">
        <div class="profile-section-title"><div><span class="profile-eyebrow">ACCOUNT</span><h2>Owner's profile</h2><p>Account details are kept in sync with your authenticated owner account.</p></div><button type="button" class="btn outline" data-dialog-open="owner-edit-dialog"><i data-lucide="pencil"></i>Edit Owner Information</button></div>
        <div class="owner-profile-row">
            <div class="owner-avatar-wrap">
                <div class="owner-avatar">
                    @if($owner->avatar_path)<img src="{{ Storage::disk('public')->url($owner->avatar_path) }}" alt="{{ $owner->name }} profile photo">@else<img src="{{ asset('images/default-profile-avatar.svg') }}" alt="Default profile avatar">@endif
                </div>
                <form method="POST" action="{{ route('mechanic.shop-profile.photo') }}" enctype="multipart/form-data" class="profile-photo-form" data-photo-cropper>@csrf
                    <label for="owner-photo-upload" class="photo-picker-label">Choose new photo</label>
                    <input id="owner-photo-upload" type="file" name="owner_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-crop-input>
                    <small class="profile-photo-file-name">JPG, PNG or WEBP, up to 5 MB. Drag and zoom to crop.</small>
                    <div class="settings-photo-crop-row">
                        <div class="photo-crop-viewport settings-photo-crop-viewport" data-crop-viewport aria-label="Profile photo crop preview"><img src="{{ $owner->avatar_path ? Storage::disk('public')->url($owner->avatar_path) : asset('images/default-profile-avatar.svg') }}" alt="Crop preview" data-crop-image></div>
                        <label class="photo-crop-zoom">Zoom<input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom disabled></label>
                    </div>
                    <small class="profile-photo-file-name" data-crop-file-name>Select a photo to preview and crop.</small>
                    <button class="btn outline" type="submit" data-photo-save>Crop and save photo</button>
                </form>
            </div>
            <div class="owner-details-grid">
                <div><span>Full name</span><strong>{{ $owner->name }}</strong></div>
                <div><span>Contact number</span><strong>{{ $owner->phone ?: 'Not provided' }}</strong></div>
                <div><span>Email address</span><strong>{{ $owner->email }}</strong></div>
                <div><span>Registration date</span><strong>{{ $owner->created_at?->format('F j, Y') }}</strong></div>
            </div>
        </div>
    </section>

    <section class="profile-panel profile-mechanics-panel">
        <div class="profile-section-title"><div><span class="profile-eyebrow">YOUR TEAM</span><h2>Mechanic information</h2><p>Changes here are shared with the Mechanic Team page and shop dashboard.</p></div><button type="button" class="btn primary" data-dialog-open="mechanic-add-dialog"><i data-lucide="user-plus"></i>Add Mechanic</button></div>
        @if($teamMembers->isEmpty())
            <div class="profile-empty"><i data-lucide="users"></i><strong>No mechanics added yet</strong><span>Add your first mechanic to your shop roster.</span></div>
        @else
            <div class="mechanic-profile-grid">
                @foreach($teamMembers as $member)
                    <article class="mechanic-profile-card">
                        <div class="mechanic-card-top">
                            <div class="mechanic-avatar">
                                @if($member->avatar_path)<img src="{{ Storage::disk('public')->url($member->avatar_path) }}" alt="{{ $member->name }}">@else<span>{{ mb_substr($member->name, 0, 1) }}</span>@endif
                            </div>
                            <span class="mechanic-availability {{ $member->is_active ? 'available' : 'unavailable' }}"><i></i>{{ $member->is_active ? 'Available' : 'Unavailable' }}</span>
                        </div>
                        <h3>{{ $member->name }}</h3>
                        <dl class="mechanic-card-details">
                            <div><dt><i data-lucide="phone"></i>Contact</dt><dd>{{ $member->phone ?: 'Not provided' }}</dd></div>
                            <div><dt><i data-lucide="wrench"></i>Specialization</dt><dd>{{ $member->specialization ?: 'General motorcycle repair' }}</dd></div>
                            <div><dt><i data-lucide="briefcase-business"></i>Experience</dt><dd>{{ $member->years_experience }} {{ $member->years_experience === 1 ? 'year' : 'years' }}</dd></div>
                        </dl>
                        @if($member->active_request_count)<p class="mechanic-assigned-note"><i data-lucide="radio"></i>Assigned to {{ $member->active_request_count }} active {{ IlluminateSupport\Str::plural('request', $member->active_request_count) }}</p>@endif
                        <div class="mechanic-card-actions">
                            <button type="button" class="btn outline" data-edit-mechanic data-id="{{ $member->id }}" data-name="{{ $member->name }}" data-phone="{{ $member->phone }}" data-specialization="{{ $member->specialization }}" data-years-experience="{{ $member->years_experience }}" data-is-active="{{ $member->is_active ? 1 : 0 }}"><i data-lucide="pencil"></i>Edit</button>
                            <form method="POST" action="{{ route('mechanic.team.destroy', $member) }}" data-confirm="Remove {{ $member->name }} from your mechanic team? This action cannot be undone.">@csrf @method('DELETE')<button type="submit" class="btn danger" @disabled($member->active_request_count > 0)><i data-lucide="user-round-minus"></i>Remove</button></form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="profile-panel profile-services-panel">
        <div class="profile-section-title"><div><span class="profile-eyebrow">DISPATCH SETTINGS</span><h2>Shop services</h2><p>Rider requests are sent only to verified, available shops with a matching enabled service.</p></div><button type="button" class="btn primary" data-dialog-open="service-add-dialog"><i data-lucide="plus"></i>Add service</button></div>
        <div class="profile-service-list">
            @forelse($shopServices as $service)
                <article class="profile-service-row {{ $service->is_enabled ? '' : 'is-disabled' }}">
                    <div class="profile-service-icon"><i data-lucide="{{ $service->is_enabled ? 'wrench' : 'wrench' }}"></i></div>
                    <div class="profile-service-copy"><strong>{{ $service->name }}</strong><small>{{ $service->is_enabled ? 'Eligible for rider dispatch' : 'Hidden from dispatch matching' }}</small></div>
                    <form method="POST" action="{{ route('mechanic.shop-services.toggle', $service) }}" class="profile-service-toggle-form">@csrf<button type="submit" class="profile-toggle" role="switch" aria-checked="{{ $service->is_enabled ? 'true' : 'false' }}" aria-label="{{ $service->is_enabled ? 'Disable' : 'Enable' }} {{ $service->name }}"><span></span></button></form>
                    <button type="button" class="profile-icon-button" data-edit-service data-id="{{ $service->id }}" data-name="{{ $service->name }}" aria-label="Edit {{ $service->name }}"><i data-lucide="pencil"></i></button>
                    <form method="POST" action="{{ route('mechanic.shop-services.destroy', $service) }}" data-confirm="Remove {{ $service->name }} from your shop services?">@csrf @method('DELETE')<button type="submit" class="profile-icon-button is-danger" aria-label="Remove {{ $service->name }}"><i data-lucide="trash-2"></i></button></form>
                </article>
            @empty
                <div class="profile-empty compact"><i data-lucide="wrench"></i><strong>No services configured</strong><span>Add services to receive matching requests.</span></div>
            @endforelse
        </div>
    </section>

    <section class="profile-panel profile-location-panel">
        <div class="profile-section-title"><div><span class="profile-eyebrow">SHOP DETAILS</span><h2>Shop location and contact</h2><p>Pin an exact location inside Calapan City to support GPS dispatch.</p></div></div>
        <form id="shop-location-form" method="POST" action="{{ route('mechanic.shop-profile.update') }}" enctype="multipart/form-data" class="profile-location-form">
            @csrf
            <div class="profile-location-fields">
                <label>Registered shop name<input name="shop_name" value="{{ old('shop_name', $profile->shop_name) }}" required maxlength="255"></label>
                <label>Shop contact number<input name="shop_phone" value="{{ old('shop_phone', $profile->shop_phone) }}" required maxlength="32" autocomplete="tel"></label>
                <label class="profile-location-address">Shop address
                    <div class="profile-address-wrap"><input id="profile-shop-address" name="service_address" value="{{ old('service_address', $profile->service_address) }}" required maxlength="1000" autocomplete="off" aria-describedby="profile-address-hint"><div id="profile-address-suggestions" class="profile-address-suggestions" hidden></div></div>
                </label>
                <label class="profile-upload-label">Shop logo <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"></label>
            </div>
            <div class="profile-location-meta"><span><i data-lucide="map"></i>Detected barangay</span><strong id="profile-detected-barangay">{{ $profile->barangay ?: 'Not detected' }}</strong><span><i data-lucide="crosshair"></i>GPS coordinates</span><strong id="profile-coordinate-label">{{ $profile->latitude && $profile->longitude ? $profile->latitude.', '.$profile->longitude : 'Pin a location on the map' }}</strong></div>
            <p id="profile-address-hint" class="profile-help-text">Search for an address or barangay, then choose a suggestion. You can also place or drag the map pin.</p>
            <button id="profile-use-current-location" type="button" class="btn outline"><i data-lucide="locate-fixed"></i>Use My Current Location</button>
            <div id="shop-profile-map" class="shop-profile-map" role="application" aria-label="Pin the mechanic shop location on the map"></div>
            <div id="shop-location-status" class="shop-location-status {{ $profile->latitude && $profile->longitude ? 'is-pinned' : '' }}" role="status" aria-live="polite"><i data-lucide="{{ $profile->latitude && $profile->longitude ? 'circle-check' : 'map-pin' }}"></i><span>{{ $profile->latitude && $profile->longitude ? 'Shop location successfully pinned.' : 'Place or move the pin to set your shop location.' }}</span></div>
            <input id="shop-latitude" name="latitude" type="hidden" value="{{ old('latitude', $profile->latitude) }}">
            <input id="shop-longitude" name="longitude" type="hidden" value="{{ old('longitude', $profile->longitude) }}">
            <div class="profile-location-actions"><button type="submit" class="btn primary"><i data-lucide="save"></i>Save shop profile</button></div>
        </form>
    </section>
</div>

<dialog id="owner-edit-dialog" class="profile-dialog"><form method="POST" action="{{ route('mechanic.shop-profile.owner.update') }}" class="profile-dialog-form">@csrf<div class="profile-dialog-heading"><div><span class="profile-eyebrow">OWNER ACCOUNT</span><h2>Edit owner information</h2></div><button type="button" class="profile-icon-button" data-dialog-close aria-label="Close"><i data-lucide="x"></i></button></div><label>Full name<input name="name" value="{{ $owner->name }}" required maxlength="255"></label><label>Contact number<input name="phone" value="{{ $owner->phone }}" maxlength="32"></label><label>Email address<input type="email" name="email" value="{{ $owner->email }}" required maxlength="255"></label><div class="profile-dialog-actions"><button type="button" class="btn outline" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save information</button></div></form></dialog>

<dialog id="mechanic-add-dialog" class="profile-dialog"><form method="POST" action="{{ route('mechanic.team.store') }}" enctype="multipart/form-data" class="profile-dialog-form">@csrf<div class="profile-dialog-heading"><div><span class="profile-eyebrow">SHOP TEAM</span><h2>Add mechanic</h2></div><button type="button" class="profile-icon-button" data-dialog-close aria-label="Close"><i data-lucide="x"></i></button></div><div class="profile-dialog-grid"><label class="dialog-full">Full name<input name="name" required maxlength="255"></label><label>Contact number<input name="phone" maxlength="32"></label><label>Specialization<input name="specialization" maxlength="120" placeholder="e.g. Engine repair"></label><label>Years of experience<input type="number" name="years_experience" min="0" max="60" value="0"></label><label>Profile picture<input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp"></label></div><div class="profile-dialog-actions"><button type="button" class="btn outline" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add mechanic</button></div></form></dialog>

<dialog id="mechanic-edit-dialog" class="profile-dialog"><form id="mechanic-edit-form" method="POST" enctype="multipart/form-data" class="profile-dialog-form">@csrf @method('PUT')<div class="profile-dialog-heading"><div><span class="profile-eyebrow">SHOP TEAM</span><h2>Edit mechanic</h2></div><button type="button" class="profile-icon-button" data-dialog-close aria-label="Close"><i data-lucide="x"></i></button></div><div class="profile-dialog-grid"><label class="dialog-full">Full name<input name="name" required maxlength="255"></label><label>Contact number<input name="phone" maxlength="32"></label><label>Specialization<input name="specialization" maxlength="120"></label><label>Years of experience<input type="number" name="years_experience" min="0" max="60"></label><label>Availability<select name="is_active"><option value="1">Available</option><option value="0">Unavailable</option></select></label><label>Change profile picture<input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp"></label></div><div class="profile-dialog-actions"><button type="button" class="btn outline" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save changes</button></div></form></dialog>

<dialog id="service-add-dialog" class="profile-dialog"><form method="POST" action="{{ route('mechanic.shop-services.store') }}" class="profile-dialog-form">@csrf<div class="profile-dialog-heading"><div><span class="profile-eyebrow">SHOP SERVICES</span><h2>Add custom service</h2></div><button type="button" class="profile-icon-button" data-dialog-close aria-label="Close"><i data-lucide="x"></i></button></div><label>Service name<input name="name" required maxlength="120" placeholder="Enter a service your shop provides"></label><div class="profile-dialog-actions"><button type="button" class="btn outline" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add service</button></div></form></dialog>

<dialog id="service-edit-dialog" class="profile-dialog"><form id="service-edit-form" method="POST" class="profile-dialog-form">@csrf @method('PUT')<div class="profile-dialog-heading"><div><span class="profile-eyebrow">SHOP SERVICES</span><h2>Edit service name</h2></div><button type="button" class="profile-icon-button" data-dialog-close aria-label="Close"><i data-lucide="x"></i></button></div><label>Service name<input name="name" required maxlength="120"></label><div class="profile-dialog-actions"><button type="button" class="btn outline" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save service</button></div></form></dialog>

<script>
document.querySelectorAll('[data-dialog-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal()));
document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));
document.querySelectorAll('[data-scroll-to]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.scrollTo)?.scrollIntoView({behavior: 'smooth', block: 'start'})));
document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
document.querySelectorAll('[data-edit-mechanic]').forEach(button => button.addEventListener('click', () => {
    const data = button.dataset;
    const form = document.getElementById('mechanic-edit-form');
    form.action = `{{ url('/mechanic/team') }}/${data.id}`;
    form.elements.name.value = data.name ?? '';
    form.elements.phone.value = data.phone ?? '';
    form.elements.specialization.value = data.specialization ?? '';
    form.elements.years_experience.value = data.yearsExperience ?? 0;
    form.elements.is_active.value = data.isActive ?? '1';
    document.getElementById('mechanic-edit-dialog').showModal();
}));
document.querySelectorAll('[data-edit-service]').forEach(button => button.addEventListener('click', () => {
    const data = button.dataset;
    const form = document.getElementById('service-edit-form');
    form.action = `{{ url('/mechanic/shop-services') }}/${data.id}`;
    form.elements.name.value = data.name;
    document.getElementById('service-edit-dialog').showModal();
}));
document.querySelectorAll('[data-auto-submit]').forEach(input => input.addEventListener('change', () => {
    if (!input.files?.length) return;
    const label = input.form.querySelector('label');
    if (label) label.textContent = 'Uploading…';
    input.form.requestSubmit();
}));
document.querySelectorAll('.shop-profile-page form:not([data-photo-cropper])').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const button = event.submitter || form.querySelector('button[type=submit]');
    if (!button || button.disabled) return;
    button.disabled = true;
    if (button.classList.contains('profile-toggle')) button.setAttribute('aria-label', 'Updating service…');
    else if (!button.classList.contains('danger')) button.textContent = 'Saving…';
}));
document.querySelectorAll('.profile-dialog-form').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const button = event.submitter || form.querySelector('button[type=submit]');
    if (button && !button.disabled) { button.disabled = true; button.textContent = 'Saving…'; }
}));

const mapElement = document.getElementById('shop-profile-map');
const latInput = document.getElementById('shop-latitude');
const lngInput = document.getElementById('shop-longitude');
const addressInput = document.getElementById('profile-shop-address');
const suggestions = document.getElementById('profile-address-suggestions');
const statusElement = document.getElementById('shop-location-status');
const boundary = {{ Js::from($cityBoundary) }};
const setStatus = (kind, message, icon) => { statusElement.className = `shop-location-status ${kind}`; statusElement.innerHTML = `<i data-lucide="${icon}"></i><span>${message}</span>`; window.lucide?.createIcons(); };
const profileMap = L.map(mapElement, { minZoom: 10, maxZoom: 19 });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(profileMap);
const boundaryLayer = L.geoJSON(boundary, {style: {color: '#FF0000', weight: 2, fillColor: '#FF0000', fillOpacity: 0.06}, interactive: false}).addTo(profileMap);
profileMap.fitBounds(boundaryLayer.getBounds());
let marker = null;
const updateCoords = (lat, lng) => { latInput.value = Number(lat).toFixed(7); lngInput.value = Number(lng).toFixed(7); document.getElementById('profile-coordinate-label').textContent = `${latInput.value}, ${lngInput.value}`; };
const movePin = async (lat, lng, reverse = true) => {
    if (!window.FixMotoGeography.isInside(boundary.geometry, Number(lat), Number(lng))) { latInput.value = ''; lngInput.value = ''; document.getElementById('profile-coordinate-label').textContent = 'Pin a location on the map'; document.getElementById('profile-detected-barangay').textContent = 'Not detected'; setStatus('is-invalid', 'Choose a location within Calapan City boundaries.', 'circle-alert'); return; }
    updateCoords(lat, lng);
    profileMap.setView([lat, lng], Math.max(profileMap.getZoom(), 16));
    if (!marker) { marker = L.marker([lat, lng], {draggable: true}).addTo(profileMap); marker.on('dragend', event => { const point = event.target.getLatLng(); movePin(point.lat, point.lng); }); }
    else marker.setLatLng([lat, lng]);
    setStatus('is-pinned', 'Shop location successfully pinned.', 'circle-check');
    document.getElementById('profile-detected-barangay').textContent = 'Checking…';
    if (reverse) {
        try {
            const url = new URL(@json(route('locations.reverse')), location.origin); url.searchParams.set('latitude', lat); url.searchParams.set('longitude', lng);
            const response = await fetch(url, {headers: {Accept: 'application/json'}}); const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Location could not be checked.');
            if (result.address) addressInput.value = result.address;
            if (!result.barangay) throw new Error('This pin does not match a known barangay. Move the pin to a clear location inside a barangay.');
            document.getElementById('profile-detected-barangay').textContent = result.barangay;
        } catch (error) { latInput.value = ''; lngInput.value = ''; document.getElementById('profile-coordinate-label').textContent = 'Pin a location on the map'; document.getElementById('profile-detected-barangay').textContent = 'Not identified'; setStatus('is-invalid', error.message || 'This location could not be verified.', 'circle-alert'); }
    }
};
document.getElementById('profile-use-current-location').addEventListener('click', () => {
    if (!navigator.geolocation) { setStatus('is-invalid', 'This browser does not support location detection.', 'circle-alert'); return; }
    setStatus('', 'Getting your current location…', 'locate-fixed');
    navigator.geolocation.getCurrentPosition(position => {
        movePin(position.coords.latitude, position.coords.longitude, true);
    }, () => setStatus('is-invalid', 'Location access was unavailable. Place the pin on the map instead.', 'circle-alert'), {enableHighAccuracy: true, timeout: 12000, maximumAge: 60000});
});
if (latInput.value && lngInput.value) {
    window.addEventListener('DOMContentLoaded', () => movePin(Number(latInput.value), Number(lngInput.value), false), {once: true});
}
profileMap.on('click', event => movePin(event.latlng.lat, event.latlng.lng));
const resizeProfileMap = () => profileMap.invalidateSize(); window.addEventListener('load', resizeProfileMap); window.addEventListener('resize', resizeProfileMap); if (window.ResizeObserver) new ResizeObserver(resizeProfileMap).observe(mapElement);
let searchTimer = null; let searchController = null;
addressInput.addEventListener('input', () => {
    latInput.value = ''; lngInput.value = ''; document.getElementById('profile-coordinate-label').textContent = 'Pin a location on the map';
    clearTimeout(searchTimer); searchController?.abort();
    const query = addressInput.value.trim(); if (query.length < 2) { suggestions.hidden = true; return; }
    searchTimer = setTimeout(async () => {
        searchController = new AbortController(); suggestions.replaceChildren(); const loading = document.createElement('p'); loading.textContent = 'Searching addresses…'; suggestions.append(loading); suggestions.hidden = false;
        try {
            const url = new URL(@json(route('locations.search')), location.origin); url.searchParams.set('q', query);
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: searchController.signal}); const results = await response.json(); suggestions.replaceChildren();
            if (!results.length) { const empty = document.createElement('p'); empty.textContent = 'No matching address found. Pin the exact location on the map.'; suggestions.append(empty); return; }
            results.forEach(place => { const option = document.createElement('button'); option.type = 'button'; option.textContent = place.address; option.addEventListener('click', () => { suggestions.hidden = true; addressInput.value = place.address; if (place.latitude !== null && place.longitude !== null) { profileMap.setView([place.latitude, place.longitude], 16); movePin(place.latitude, place.longitude, true); } else { document.getElementById('profile-detected-barangay').textContent = place.barangay || 'Pin exact location'; } }); suggestions.append(option); });
        } catch (error) { if (error.name !== 'AbortError') { suggestions.hidden = true; } }
    }, 400);
});
document.addEventListener('click', event => { if (!event.target.closest('.profile-address-wrap')) suggestions.hidden = true; });
document.getElementById('shop-location-form').addEventListener('submit', event => { const button = event.currentTarget.querySelector('button[type=submit]'); if (!latInput.value || !lngInput.value) { event.preventDefault(); setStatus('is-invalid', 'Pin the shop location inside Calapan City before saving.', 'circle-alert'); mapElement.scrollIntoView({behavior: 'smooth', block: 'center'}); return; } button.disabled = true; button.innerHTML = '<span class="shop-profile-spinner" aria-hidden="true"></span><span>Saving…</span>'; });
</script>
<script src="{{ asset('js/mechanic-profile-photo.js') }}?v=1" defer></script>
@endsection
