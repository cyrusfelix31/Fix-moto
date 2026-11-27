<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}">

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/fixmoto-mark.svg') }}?v=4">

    <title>
        @yield('title', 'FIXMOTO')
    </title>

    {{-- Main stylesheet and Rider stylesheet --}}
    @vite([
        'resources/css/app.css',
        'resources/css/rider.css',
        'resources/js/app.js'
    ])

    {{-- Leaflet map --}}
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    {{-- Chart.js --}}
    <script
        src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js">
    </script>

    {{-- Lucide icons --}}
    <script
        src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js">
    </script>

    @stack('styles')

</head>

<body>

@auth

    @php

        $user = auth()->user();

        $navigation = match($user->role) {

            'rider' => [
                ['rider.dashboard', 'layout-dashboard', 'Dashboard'],
                ['rider.vehicles.index', 'car-front', 'Vehicles'],
                ['rider.request-help', 'siren', 'Request Help'],
                ['rider.history', 'history', 'History'],
            ],

            'mechanic' => [
                ['mechanic.dashboard', 'layout-dashboard', 'Dashboard'],
                ['mechanic.shop-requests', 'clipboard-list', 'Shop Requests'],
                ['mechanic.team', 'users', 'Mechanic Team'],
                ['mechanic.shop-profile', 'store', 'Shop Profile'],
            ],

            'admin' => [
                ['admin.dashboard', 'layout-dashboard', 'Dashboard'],
                ['admin.live-dispatch', 'map-pin', 'Live Dispatch'],
                ['admin.users-mechanics', 'users', 'Users & Shops'],
                ['admin.verification', 'badge-check', 'Verification'],
                ['admin.monitoring', 'activity', 'System Monitoring'],
                ['admin.audit-trail', 'clipboard-list', 'Audit Logs'],
                ['admin.settings', 'settings', 'Settings'],
            ],

            default => [],
        };

        $roleLabel = match($user->role) {
            'rider' => 'Rider',
            'mechanic' => 'Mechanic Shop',
            'admin' => 'Administrator',
            default => ucfirst($user->role),
        };

    @endphp


    <div class="app-shell">

        {{-- SIDEBAR --}}
        <aside class="sidebar admin-app-sidebar" id="sidebar">

            <a
                class="brand"
                href="{{ route($user->role.'.dashboard') }}"
                aria-label="FixMoto dashboard">

                <span class="admin-brand-tile">
                    <img class="fixmoto-logo" src="{{ asset('images/motofix-logo.png') }}?v=1" alt="">
                </span>

            </a>


            {{-- Navigation --}}
            <nav aria-label="Main navigation">

                @foreach($navigation as [$routeName, $icon, $label])

                    @php

                        $isActive = request()->routeIs($routeName);

                        if (
                            $routeName === 'rider.history' &&
                            request()->routeIs('rider.requests.show')
                        ) {
                            $isActive = true;
                        }

                        if (
                            $routeName === 'rider.vehicles.index' &&
                            request()->routeIs('rider.vehicles.*')
                        ) {
                            $isActive = true;
                        }

                    @endphp

                    <a
                        href="{{ route($routeName) }}"
                        class="nav-link {{ $isActive ? 'active' : '' }}"
                        @if($isActive) aria-current="page" @endif>

                        <i data-lucide="{{ $icon }}"></i>

                        <span>
                            {{ $label }}
                        </span>

                    </a>

                @endforeach

            </nav>


            {{-- Logout --}}
            <form
                method="POST"
                action="{{ route('logout') }}"
                class="logout-form">

                @csrf

                <button
                    type="submit"
                    class="nav-link">

                    <i data-lucide="log-out"></i>

                    <span>
                        Log out
                    </span>

                </button>

            </form>

        </aside>


        {{-- MAIN CONTENT --}}
        <main class="dashboard-main">

            {{-- TOP BAR --}}
            <header class="topbar">

                <div style="
                    display:flex;
                    align-items:center;
                    gap:12px;
                    min-width:0;
                ">

                    <button
                        type="button"
                        id="menu-toggle"
                        class="menu-button"
                        aria-label="Toggle navigation"
                        aria-controls="sidebar"
                        aria-expanded="false">

                        <i data-lucide="menu"></i>

                    </button>


                    <div>

                        <p class="eyebrow">
                            FIXMOTO
                        </p>

                        <h1>
                            @yield('page_title', 'Dashboard')
                        </h1>

                    </div>

                </div>


                {{-- Profile --}}
                <div class="profile-chip">

                    <span>

                        {{ strtoupper(substr($user->name, 0, 1)) }}

                    </span>

                    <div>

                        <strong>
                            {{ $user->name }}
                        </strong>

                        <small>
                            {{ $roleLabel }}
                        </small>

                    </div>

                </div>

            </header>


            {{-- Flash messages --}}
            @if(!request()->routeIs(
                'rider.requests.show',
                'mechanic.shop-requests'
            ))

                @if(session('success'))

                    <div class="alert success" role="status">

                        {{ session('success') }}

                    </div>

                @endif

                @if(session('warning'))

                    <div class="alert warning" role="alert">

                        {{ session('warning') }}

                    </div>

                @endif

                @if(session('error'))

                    <div class="alert error" role="alert">

                        {{ session('error') }}

                    </div>

                @endif

            @endif


            {{-- Page content --}}
            @yield('content')

        </main>

    </div>

@else

    {{-- LOGIN AND REGISTRATION --}}
    <main class="auth-main">

        @yield('content')

    </main>

@endauth


<script>

document.addEventListener('DOMContentLoaded', () => {

    // Initialize Lucide icons
    if (typeof window.lucide?.createIcons === 'function') {

        window.lucide.createIcons();

    }


    // Responsive sidebar
    const sidebar = document.getElementById('sidebar');

    const menuToggle = document.getElementById('menu-toggle');


    function closeSidebar() {

        sidebar?.classList.remove('open');

        menuToggle?.setAttribute('aria-expanded', 'false');

    }


    menuToggle?.addEventListener('click', () => {

        const isOpen = sidebar?.classList.toggle('open');

        menuToggle.setAttribute(

            'aria-expanded',

            String(Boolean(isOpen))

        );

    });


    // Close navigation when clicking outside
    document.addEventListener('click', event => {

        if (!sidebar || !menuToggle) {

            return;

        }

        if (

            window.innerWidth <= 900 &&

            sidebar.classList.contains('open') &&

            !sidebar.contains(event.target) &&

            !menuToggle.contains(event.target)

        ) {

            closeSidebar();

        }

    });


    // Close sidebar after selecting a page on mobile
    sidebar?.querySelectorAll('nav a').forEach(link => {

        link.addEventListener('click', () => {

            if (window.innerWidth <= 900) {

                closeSidebar();

            }

        });

    });


    // Close sidebar using Escape
    document.addEventListener('keydown', event => {

        if (event.key === 'Escape') {

            closeSidebar();

        }

    });

});

</script>

@stack('scripts')

</body>

</html>
