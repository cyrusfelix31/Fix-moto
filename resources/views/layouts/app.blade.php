<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FIXMOTO')</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>
<body>
<div class="app-shell">
    @auth
    <aside class="sidebar">
        <a class="brand" href="{{ route(auth()->user()->role . '.dashboard') }}"><span class="brand-mark">F</span><span>FIXMOTO</span></a>
        <nav>
            <a href="{{ route(auth()->user()->role . '.dashboard') }}" class="nav-link {{ request()->routeIs(auth()->user()->role . '.dashboard') ? 'active' : '' }}"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
            @if(auth()->user()->role === 'rider')
                <a href="{{ route('rider.vehicles.index') }}" class="nav-link {{ request()->routeIs('rider.vehicles.*') ? 'active' : '' }}"><i data-lucide="car"></i><span>Vehicles</span></a>
                <a href="{{ route('rider.request-help') }}" class="nav-link {{ request()->routeIs('rider.request-help') ? 'active' : '' }}"><i data-lucide="siren"></i><span>Request Help</span></a>
                <a href="{{ route('rider.history') }}" class="nav-link {{ request()->routeIs('rider.history', 'rider.requests.show') ? 'active' : '' }}"><i data-lucide="history"></i><span>History</span></a>
            @elseif(auth()->user()->role === 'mechanic')
                <a href="{{ route('mechanic.shop-requests') }}" class="nav-link {{ request()->routeIs('mechanic.shop-requests') ? 'active' : '' }}"><i data-lucide="briefcase"></i><span>Shop requests</span></a>
                <a href="{{ route('mechanic.team') }}" class="nav-link {{ request()->routeIs('mechanic.team') ? 'active' : '' }}"><i data-lucide="users"></i><span>Mechanic team</span></a>
                <a href="{{ route('mechanic.shop-profile') }}" class="nav-link {{ request()->routeIs('mechanic.shop-profile') ? 'active' : '' }}"><i data-lucide="map-pin"></i><span>Shop profile</span></a>
            @else
                <a href="{{ route('admin.users-mechanics') }}" class="nav-link"><i data-lucide="building-2"></i><span>Mechanic shops</span></a>
                <a href="#requests" class="nav-link"><i data-lucide="radio"></i><span>Requests</span></a>
            @endif
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button class="nav-link logout"><i data-lucide="log-out"></i><span>Log out</span></button></form>
    </aside>
    @endauth
    <main class="@auth dashboard-main @else auth-main @endauth">
        @auth
        <header class="topbar">
            <button class="menu-button" type="button" onclick="document.querySelector('.sidebar').classList.toggle('open')" aria-label="Open menu"><i data-lucide="menu"></i></button>
            <div><p class="eyebrow">FIXMOTO</p><h1>@yield('page_title')</h1></div>
            <div class="profile-chip"><span>{{ substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'mechanic' ? 'Mechanic shop' : ucfirst(auth()->user()->role) }}</small></div></div>
        </header>
        @endauth
        @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="alert warning">{{ session('warning') }}</div>@endif
        @yield('content')
    </main>
</div>
<script>window.lucide?.createIcons();</script>
</body>
</html>
