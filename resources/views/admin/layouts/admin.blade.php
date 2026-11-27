<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    <title>
        @yield('title', 'FIXMOTO Administrator')
    </title>

    {{-- MAIN CSS AND JAVASCRIPT --}}

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    {{-- LEAFLET MAP CSS --}}

    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    {{-- LEAFLET MAP JAVASCRIPT --}}

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    {{-- CHART JS --}}

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js">
    </script>

    {{-- LUCIDE ICONS --}}

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js">
    </script>

    @stack('styles')

</head>

<body class="admin-body">

@php

    $pendingVerifications = \App\Models\User::pendingVerification()->count();

@endphp

<div class="admin-shell">

    {{-- =====================================
         ADMIN SIDEBAR
         ===================================== --}}

    <aside class="admin-sidebar"
           id="admin-sidebar">

        {{-- FIXMOTO BRAND --}}

        <a class="admin-brand"
           href="{{ route('admin.dashboard') }}">

            <span class="admin-brand-mark">

                <i data-lucide="shield-check"></i>

            </span>

            <span>FIXMOTO</span>

        </a>


        {{-- SERVICE AREA --}}

        <div class="service-area">

            <span class="area-dot"></span>

            <div>

                <small>SERVICE AREA</small>

                <strong>Calapan City</strong>

            </div>

        </div>


        {{-- =====================================
             ADMIN NAVIGATION
             ===================================== --}}

        <nav class="admin-nav">

            @foreach([

                [
                    'admin.dashboard',
                    'layout-dashboard',
                    'Dashboard'
                ],

                [
                    'admin.live-dispatch',
                    'map-pin',
                    'Live dispatch'
                ],

                [
                    'admin.users-mechanics',
                    'users',
                    'Users & shops'
                ],

                [
                    'admin.verification',
                    'badge-check',
                    'Verification'
                ],

                [
                    'admin.monitoring',
                    'activity',
                    'System monitoring'
                ],

                [
                    'admin.audit-trail',
                    'clipboard-list',
                    'Audit logs'
                ],

                [
                    'admin.settings',
                    'settings',
                    'Settings'
                ],

            ] as [$route, $icon, $label])

                @php

                    $active = $route === 'admin.verification'

                        ? request()->routeIs('admin.verification*')

                        : request()->routeIs($route);

                @endphp

                <a href="{{ route($route) }}"

                   class="admin-nav-link
                   {{ $active ? 'active' : '' }}">

                    <i data-lucide="{{ $icon }}"></i>

                    <span>{{ $label }}</span>

                    @if(
                        $route === 'admin.verification'
                        && $pendingVerifications > 0
                    )

                        <b>{{ $pendingVerifications }}</b>

                    @endif

                </a>

            @endforeach

        </nav>


        {{-- LOGOUT BUTTON --}}

        <form method="POST"
              action="{{ route('logout') }}"
              class="admin-logout"
              style="margin-top: auto;">

            @csrf

            <button type="submit">

                <i data-lucide="log-out"></i>

                Log out

            </button>

        </form>

    </aside>


    {{-- =====================================
         ADMIN MAIN CONTENT
         ===================================== --}}

    <main class="admin-main">


        {{-- ADMIN TOPBAR --}}

        <header class="admin-topbar">


            {{-- MOBILE NAVIGATION BUTTON --}}

            <button class="admin-menu"
                    type="button"
                    id="admin-menu-button"
                    aria-label="Open navigation"
                    aria-controls="admin-sidebar"
                    aria-expanded="false">

                <i data-lucide="menu"></i>

            </button>


            {{-- PAGE TITLE --}}

            <h2>

                @yield('page_title', 'Dashboard')

            </h2>


            {{-- ADMIN ACCOUNT --}}

            <div class="admin-account">

                <details class="profile-menu">

                    <summary>

                        <span class="admin-avatar">

                            {{ strtoupper(
                                substr(
                                    auth()->user()->name,
                                    0,
                                    1
                                )
                            ) }}

                        </span>

                        <span class="admin-name">

                            {{ auth()->user()->name }}

                        </span>

                        <i data-lucide="chevron-down"></i>

                    </summary>


                    {{-- PROFILE DROPDOWN --}}

                    <div class="profile-popover">

                        <strong>Administrator</strong>

                        <form method="POST"
                              action="{{ route('logout') }}">

                            @csrf

                            <button type="submit">

                                Log out

                            </button>

                        </form>

                    </div>

                </details>


                {{-- NOTIFICATION BUTTON --}}

                <button class="notification-button"
                        type="button"
                        id="admin-notification-button"
                        aria-label="Notifications"
                        aria-controls="notifications"
                        aria-expanded="false">

                    <i data-lucide="bell"></i>

                    @if($pendingVerifications > 0)

                        <span></span>

                    @endif

                </button>


                {{-- NOTIFICATION POPOVER --}}

                <div class="notifications-popover"
                     id="notifications">

                    <strong>Notifications</strong>

                    @if($pendingVerifications > 0)

                        <p>

                            {{ $pendingVerifications }}

                            mechanic shop
                            {{ $pendingVerifications === 1
                                ? 'application'
                                : 'applications' }}

                            pending review.

                        </p>

                        <a href="{{ route('admin.verification') }}">

                            View applications

                        </a>

                    @else

                        <p>No pending shop applications.</p>

                    @endif

                </div>

            </div>

        </header>


        {{-- =====================================
             SUCCESS MESSAGE
             ===================================== --}}

        @if(session('success'))

            <div class="admin-alert success"
                 role="status">

                {{ session('success') }}

            </div>

        @endif


        {{-- =====================================
             ERROR MESSAGE
             ===================================== --}}

        @if(session('error'))

            <div class="admin-alert error"
                 role="alert">

                {{ session('error') }}

            </div>

        @endif


        {{-- VALIDATION ERRORS --}}

        @if($errors->any())

            <div class="admin-alert error"
                 role="alert">

                @foreach($errors->all() as $error)

                    <div>{{ $error }}</div>

                @endforeach

            </div>

        @endif


        {{-- =====================================
             PAGE CONTENT
             ===================================== --}}

        @yield('content')

    </main>

</div>


{{-- =====================================
     ADMIN JAVASCRIPT
     ===================================== --}}

<script>

document.addEventListener('DOMContentLoaded', () => {

    const sidebar = document.getElementById(
        'admin-sidebar'
    );

    const menuButton = document.getElementById(
        'admin-menu-button'
    );

    const notificationButton = document.getElementById(
        'admin-notification-button'
    );

    const notifications = document.getElementById(
        'notifications'
    );


    // MOBILE SIDEBAR

    menuButton?.addEventListener('click', () => {

        const opened = sidebar.classList.toggle('open');

        menuButton.setAttribute(
            'aria-expanded',
            String(opened)
        );

    });


    // NOTIFICATION DROPDOWN

    notificationButton?.addEventListener('click', event => {

        event.stopPropagation();

        const opened = notifications.classList.toggle('open');

        notificationButton.setAttribute(
            'aria-expanded',
            String(opened)
        );

    });


    // CLOSE DROPDOWNS WHEN CLICKING OUTSIDE

    document.addEventListener('click', event => {

        if (

            notifications

            && !notifications.contains(event.target)

            && !notificationButton?.contains(event.target)

        ) {

            notifications.classList.remove('open');

            notificationButton?.setAttribute(
                'aria-expanded',
                'false'
            );

        }

        document.querySelectorAll(
            '.profile-menu[open], .verification-action-menu[open]'
        ).forEach(menu => {

            if (!menu.contains(event.target)) {

                menu.removeAttribute('open');

            }

        });

    });


    // CLOSE MENUS USING ESCAPE

    document.addEventListener('keydown', event => {

        if (event.key === 'Escape') {

            sidebar?.classList.remove('open');

            notifications?.classList.remove('open');

            menuButton?.setAttribute(
                'aria-expanded',
                'false'
            );

            notificationButton?.setAttribute(
                'aria-expanded',
                'false'
            );

        }

    });


    // INITIALIZE LUCIDE ICONS

    window.lucide?.createIcons();

});

</script>

@stack('scripts')

</body>

</html>