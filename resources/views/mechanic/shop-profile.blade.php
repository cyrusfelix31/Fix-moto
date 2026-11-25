@extends('layouts.app')
@section('title', 'Shop Profile')
@section('page_title', 'Shop profile')
@section('content')
<div class="page shop-profile-page mechanic-page">
    @if($errors->any())
        <div class="alert error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <section class="shop-profile-card">
        <header class="shop-profile-header">
            <h2>Shop details and location</h2>
            <p>Manage your shop information and pin its location on the map.</p>
        </header>
        <form method="POST" action="{{ route('mechanic.shop-profile.update') }}" class="shop-profile-form">
            @csrf
            <div class="shop-profile-fields">
                <label>
                    <span>Shop name</span>
                    <input name="shop_name" value="{{ old('shop_name', $profile->shop_name) }}" required maxlength="255">
                </label>
                <label>
                    <span>Service address</span>
                    <input name="service_address" value="{{ old('service_address', $profile->service_address) }}" required maxlength="1000">
                </label>
            </div>
            <div class="shop-location-section">
                <div id="shop-profile-map" class="shop-profile-map" role="application" aria-label="Pin the mechanic shop location on the map"></div>
                <div id="shop-location-status" class="shop-location-status {{ $profile->latitude && $profile->longitude ? 'is-pinned' : '' }}" role="status" aria-live="polite">
                    @if($profile->latitude && $profile->longitude)
                        <i data-lucide="circle-check"></i>
                        <span>Shop location successfully pinned.</span>
                    @else
                        <i data-lucide="map-pin"></i>
                        <span>Place or move the pin to set your shop location.</span>
                    @endif
                </div>
                <input id="shop-latitude" name="latitude" type="hidden" value="{{ old('latitude', $profile->latitude) }}">
                <input id="shop-longitude" name="longitude" type="hidden" value="{{ old('longitude', $profile->longitude) }}">
            </div>
            <div class="shop-profile-actions">
                <button type="submit" class="shop-profile-save">
                    <i data-lucide="save"></i>
                    <span>Save shop profile</span>
                </button>
            </div>
        </form>
    </section>
</div>
<script>
const shopProfileMapElement = document.getElementById('shop-profile-map');
const shopLatitudeInput = document.getElementById('shop-latitude');
const shopLongitudeInput = document.getElementById('shop-longitude');
const shopLocationStatus = document.getElementById('shop-location-status');
const shopProfileMap = L.map(shopProfileMapElement);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(shopProfileMap);
const cityBoundary = {{ Js::from($cityBoundary) }};
const cityBoundaryLayer = L.geoJSON(cityBoundary, {
    style: { color: '#145740', weight: 2, fillColor: '#145740', fillOpacity: 0.08 },
}).addTo(shopProfileMap);
shopProfileMap.fitBounds(cityBoundaryLayer.getBounds());
let shopLocationMarker = null;
const shopGpsIcon = L.divIcon({
    className: 'shop-gps-marker',
    html: '<span></span>',
    iconSize: [28, 28],
    iconAnchor: [14, 14]
});
const setShopLocation = (latitude, longitude) => {
    if (!window.FixMotoGeography.isInside(cityBoundary.geometry, latitude, longitude)) {
        if (shopLocationMarker && shopLatitudeInput.value && shopLongitudeInput.value) {
            shopLocationMarker.setLatLng([Number(shopLatitudeInput.value), Number(shopLongitudeInput.value)]);
        }
        shopLocationStatus.className = 'shop-location-status is-invalid';
        shopLocationStatus.innerHTML = '<i data-lucide="circle-alert"></i><span>Choose a location within Calapan City boundaries.</span>';
        window.lucide?.createIcons();

        return;
    }

    shopLatitudeInput.value = latitude.toFixed(7);
    shopLongitudeInput.value = longitude.toFixed(7);
    shopLocationStatus.className = 'shop-location-status is-pinned';
    shopLocationStatus.innerHTML = '<i data-lucide="circle-check"></i><span>Shop location successfully pinned.</span>';
    window.lucide?.createIcons();
    if (shopLocationMarker) shopProfileMap.removeLayer(shopLocationMarker);
    shopLocationMarker = L.marker([latitude, longitude], {
        icon: shopGpsIcon,
        draggable: true
    }).addTo(shopProfileMap);
    shopLocationMarker.on('dragend', event => {
        const position = event.target.getLatLng();
        setShopLocation(position.lat, position.lng);
    });
};
if (shopLatitudeInput.value && shopLongitudeInput.value) {
    window.addEventListener('DOMContentLoaded', () => {
        setShopLocation(Number(shopLatitudeInput.value), Number(shopLongitudeInput.value));
    }, { once: true });
}
shopProfileMap.on('click', event => setShopLocation(event.latlng.lat, event.latlng.lng));
const resizeShopProfileMap = () => shopProfileMap.invalidateSize();
window.addEventListener('load', resizeShopProfileMap);
window.addEventListener('resize', resizeShopProfileMap);
if (window.ResizeObserver) {
    new ResizeObserver(resizeShopProfileMap).observe(shopProfileMapElement);
}
document.querySelector('.shop-profile-form')?.addEventListener('submit', event => {
    const button = event.currentTarget.querySelector('.shop-profile-save');
    button.classList.add('is-loading');
    button.disabled = true;
    button.innerHTML = '<span class="shop-profile-spinner" aria-hidden="true"></span><span>Saving...</span>';
});
</script>
@endsection
