@extends('layouts.app')
@section('title', 'Rider Dashboard')
@section('page_title', 'Rider dashboard')
@section('content')
<div class="page">
    <nav class="rider-breadcrumb" aria-label="Breadcrumb">
        <ol><li><a href="{{ route('rider.dashboard') }}">Rider</a></li><li aria-current="page">Dashboard</li></ol>
    </nav>

    <div class="grid stats">
        <div class="card stat-card"><div><span class="muted">My vehicles</span><strong>{{ $vehicleCount }}</strong></div><span class="stat-icon"><i data-lucide="car"></i></span></div>
        <div class="card stat-card"><div><span class="muted">Available mechanic shops</span><strong>{{ $availableShopCount }}</strong></div><span class="stat-icon"><i data-lucide="building-2"></i></span></div>
        <div class="card stat-card"><div><span class="muted">Active requests</span><strong>{{ $activeRequestCount }}</strong></div><span class="stat-icon"><i data-lucide="radio"></i></span></div>
        <div class="card stat-card"><div><span class="muted">Completed</span><strong>{{ $completedRequestCount }}</strong></div><span class="stat-icon"><i data-lucide="circle-check"></i></span></div>
    </div>

    <div class="grid content-grid">
        <section class="card map-card">
            <div class="card-header rider-map-header">
                <div><h2>Mechanic discovery</h2><p class="muted">Calapan City</p></div>
                <span class="badge amber">{{ $mechanicShops->count() }} verified shops</span>
            </div>
            <div id="rider-map" class="map" aria-label="Map of verified mechanic shops"></div>
        </section>

        <section class="card rider-quick-links">
            <div class="card-header"><h2>Quick access</h2></div>
            <nav class="rider-dashboard-links" aria-label="Rider pages">
                <a class="rider-dashboard-link" href="{{ route('rider.request-help') }}">
                    <span class="stat-icon"><i data-lucide="siren"></i></span>
                    <span><strong>Request help</strong><small>Get roadside assistance</small></span>
                    <i data-lucide="arrow-right"></i>
                </a>
                <a class="rider-dashboard-link" href="{{ route('rider.vehicles.index') }}">
                    <span class="stat-icon"><i data-lucide="car"></i></span>
                    <span><strong>My vehicles</strong><small>Manage your registered vehicles</small></span>
                    <i data-lucide="arrow-right"></i>
                </a>
                <a class="rider-dashboard-link" href="{{ route('rider.history') }}">
                    <span class="stat-icon"><i data-lucide="history"></i></span>
                    <span><strong>Service history</strong><small>Review your past requests</small></span>
                    <i data-lucide="arrow-right"></i>
                </a>
            </nav>
        </section>
    </div>
</div>
<script>
const riderMap = L.map('rider-map').setView([13.4112, 121.1803], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(riderMap);

@foreach($mechanicShops as $profile)
    @php($shopName = $profile->shop_name ?: ($profile->mechanic?->name ?? 'Mechanic shop'))
    @php($shopStatus = $profile->is_online && $profile->active_members_count > 0 ? 'Available' : 'Offline')
    {
        const popup = document.createElement('div');
        const shopTitle = document.createElement('strong');
        shopTitle.textContent = {{ Js::from($shopName) }};
        popup.append(shopTitle);
        popup.append(document.createElement('br'));
        popup.append(document.createTextNode({{ Js::from($shopStatus) }}));
        popup.append(document.createElement('br'));
        popup.append(document.createTextNode({{ Js::from($profile->active_members_count.' active mechanic(s)') }}));
        popup.append(document.createElement('br'));
        popup.append(document.createTextNode({{ Js::from($profile->service_address) }}));
        L.marker([{{ $profile->latitude }}, {{ $profile->longitude }}]).addTo(riderMap).bindPopup(popup);
    }
@endforeach
</script>
@endsection
