@extends('layouts.app')
@section('title', 'Shop Requests')
@section('page_title', 'Shop requests')

@section('content')

<div class="page mechanic-page">

    {{-- Error messages --}}
    @if($errors->any())
        <div class="alert error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Success messages --}}
    @if(session('success'))
        <div class="alert success" role="status">
            {{ session('success') }}
        </div>
    @endif


    {{-- SHOP AVAILABILITY --}}
    <section class="card">

        <div class="card-header">

            <div>
                <h2>Shop availability</h2>

                <p class="muted">
                    Only verified shops with active mechanics
                    can receive requests.
                </p>
            </div>

            <span class="badge {{ $profile->is_verified && $profile->is_online && $profile->active_members_count ? '' : 'amber' }}">

                {{ $profile->is_verified && $profile->is_online && $profile->active_members_count ? 'Available' : 'Offline' }}

            </span>

        </div>

        <form
            method="POST"
            action="{{ route('mechanic.availability') }}">

            @csrf

            <button
                type="submit"
                class="btn {{ $profile->is_online ? 'danger' : 'primary' }}"
                @disabled(!$profile->is_verified || (!$profile->is_online && !$profile->active_members_count))>

                {{ $profile->is_online ? 'Go offline' : 'Go online' }}

            </button>

        </form>

    </section>


    {{-- INCOMING RIDER REQUESTS --}}
    <section
        class="card"
        id="incoming-requests"
        style="margin-top:18px">

        <div class="card-header">

            <div>
                <h2>Incoming Rider Requests</h2>

                <p class="muted">
                    Accept or decline incoming roadside
                    assistance requests.
                </p>
            </div>

            <span class="badge amber">
                {{ $incomingOffers->count() }} incoming
            </span>

        </div>

        @forelse($incomingOffers as $offer)

            @php
                $incomingJob = $offer->serviceRequest;
            @endphp

            @if($incomingJob)

                <article
                    class="request-card incoming-offer"
                    data-offer-id="{{ $offer->id }}">

                    <div class="card-header">

                        <strong>
                            {{ $incomingJob->reference }}
                        </strong>

                        <span class="badge amber">
                            Awaiting response
                        </span>

                    </div>

                    <div class="completed-request-details">

                        <p>
                            <strong>Rider:</strong>

                            {{ $incomingJob->rider?->name ?? 'Unknown rider' }}
                        </p>

                        <p>
                            <strong>Vehicle:</strong>

                            {{ $incomingJob->vehicle?->brand }}
                            {{ $incomingJob->vehicle?->model }}
                        </p>

                        <p>
                            <strong>Plate number:</strong>

                            {{ $incomingJob->vehicle?->plate_number ?? 'Not specified' }}
                        </p>

                        <p>
                            <strong>Requested services:</strong>

                            {{ implode(', ', $incomingJob->services ?? [$incomingJob->issue_category]) }}
                        </p>

                        <p>
                            <strong>Problem description:</strong>

                            {{ $incomingJob->problem_description ?: 'No description provided' }}
                        </p>

                        <p>
                            <strong>Landmark:</strong>

                            {{ $incomingJob->landmark ?? 'Not specified' }}
                        </p>

                        <p>
                            <strong>Distance:</strong>

                            {{ number_format($offer->distance_km, 2) }} km
                        </p>

                        <p>
                            <strong>Time remaining:</strong>

                            <span
                                class="offer-countdown"
                                data-expires="{{ $offer->expires_at->toIso8601String() }}">

                                Calculating...

                            </span>
                        </p>

                    </div>


                    {{-- Rider GPS location --}}
                    <div style="margin-top:12px">

                        <a
                            class="btn outline"
                            href="https://www.google.com/maps?q={{ $incomingJob->latitude }},{{ $incomingJob->longitude }}"
                            target="_blank"
                            rel="noopener noreferrer">

                            <i data-lucide="map-pin"></i>

                            View rider location

                        </a>

                    </div>


                    {{-- Accept and decline buttons --}}
                    <div style="
                        display:flex;
                        gap:12px;
                        margin-top:15px;
                        flex-wrap:wrap;
                    ">

                        <form
                            method="POST"
                            action="{{ route('mechanic.offers.accept', $offer) }}">

                            @csrf

                            <button
                                type="submit"
                                class="btn primary offer-action">

                                <i data-lucide="check"></i>

                                Accept request

                            </button>

                        </form>

                        <form
                            method="POST"
                            action="{{ route('mechanic.offers.decline', $offer) }}"
                            onsubmit="return confirm('Decline this request?');">

                            @csrf

                            <button
                                type="submit"
                                class="btn danger offer-action">

                                <i data-lucide="x"></i>

                                Decline request

                            </button>

                        </form>

                    </div>

                </article>

            @endif

        @empty

            <div style="
                text-align:center;
                padding:30px;
            ">

                <i data-lucide="inbox"></i>

                <h3>No incoming requests</h3>

                <p class="muted">
                    New rider requests will appear here
                    automatically.
                </p>

            </div>

        @endforelse

    </section>


    {{-- ACTIVE REQUESTS --}}
    <section
        class="card"
        id="active-requests"
        style="margin-top:18px">

        <div class="card-header">

            <h2>Active requests</h2>

            <span class="badge">
                {{ $activeJobs->count() }}
            </span>

        </div>

        @forelse($activeJobs as $activeJob)

            <article
                class="request-card active-job"
                data-job-id="{{ $activeJob->id }}"
                data-job-status="{{ $activeJob->service_status }}"
                data-job-member="{{ $activeJob->mechanic_shop_member_id }}">

                <div class="card-header">

                    <strong>
                        {{ $activeJob->reference }}
                    </strong>

                    <span class="badge">
                        {{ $activeJob->serviceStatusLabel() }}
                    </span>

                </div>

                <p>

                    {{ $activeJob->rider?->name ?? 'Unknown rider' }}

                    ·

                    {{ $activeJob->vehicle?->brand }}
                    {{ $activeJob->vehicle?->model }}

                </p>

                <p class="muted">

                    {{ implode(', ', $activeJob->services ?? [$activeJob->issue_category]) }}

                </p>

                @if($activeJob->problem_description)

                    <p>

                        <strong>Problem:</strong>

                        {{ $activeJob->problem_description }}

                    </p>

                @endif

                <p>

                    <strong>Landmark:</strong>

                    {{ $activeJob->landmark ?? 'Not specified' }}

                </p>


                {{-- View Rider location --}}
                <a
                    class="btn outline"
                    href="https://www.google.com/maps?q={{ $activeJob->latitude }},{{ $activeJob->longitude }}"
                    target="_blank"
                    rel="noopener noreferrer">

                    <i data-lucide="map-pin"></i>

                    View rider location

                </a>


                {{-- Assigned mechanic --}}
                @if($activeJob->assignedMechanicMember)

                    <p style="margin-top:15px">

                        <strong>Assigned mechanic:</strong>

                        {{ $activeJob->assignedMechanicMember->name }}

                    </p>

                @elseif($activeJob->service_status === 'accepted')

                    <form
                        method="POST"
                        action="{{ route('mechanic.requests.assign', $activeJob) }}"
                        class="form-grid">

                        @csrf

                        <label>

                            Assign mechanic

                            <select
                                name="mechanic_shop_member_id"
                                required>

                                <option value="">
                                    Select a mechanic
                                </option>

                                @foreach($availableMembers as $member)

                                    <option value="{{ $member->id }}">

                                        {{ $member->name }}

                                    </option>

                                @endforeach

                            </select>

                        </label>

                        <button
                            type="submit"
                            class="btn primary"
                            @disabled($availableMembers->isEmpty())>

                            Assign mechanic

                        </button>

                    </form>

                    @if($availableMembers->isEmpty())

                        <p class="muted">

                            No available mechanics.
                            Add or activate a mechanic first.

                        </p>

                    @endif

                @endif


                {{-- MECHANIC STATUS CONTROL --}}
                @if($activeJob->assignedMechanicMember)

                    @if($activeJob->service_status === 'accepted')

                        <form
                            method="POST"
                            action="{{ route('mechanic.requests.status', $activeJob) }}"
                            class="form-grid">

                            @csrf

                            <input
                                type="hidden"
                                name="status"
                                value="on_the_way">

                            <label>

                                Progress note

                                <input
                                    name="note"
                                    maxlength="1000"
                                    placeholder="Optional update">

                            </label>

                            {{-- Plain text button without icon --}}
                            <button
                                type="submit"
                                class="btn amber">

                                Mark On the Way

                            </button>

                        </form>

                    @elseif(in_array($activeJob->service_status, [
                        'on_the_way',
                        'arrived',
                        'diagnosing',
                        'repairing'
                    ], true))

                        {{-- Waiting for Rider confirmation --}}
                        <div style="
                            margin-top:16px;
                            padding:18px;
                            background:#DCDCDF;
                            border:1px solid #d4e5da;
                            border-radius:12px;
                        ">

                            <div style="
                                display:flex;
                                align-items:center;
                                gap:10px;
                                color:#FF0000;
                            ">

                                <i data-lucide="clock"></i>

                                <strong>
                                    Waiting for Rider Confirmation
                                </strong>

                            </div>

                            <p class="muted" style="margin-top:10px">

                                @if($activeJob->service_status === 'on_the_way')

                                    The assigned mechanic is on the way.
                                    Waiting for the rider to confirm arrival.

                                @elseif(in_array($activeJob->service_status, [
                                    'arrived',
                                    'diagnosing'
                                ], true))

                                    The mechanic has arrived.
                                    Waiting for the rider to confirm
                                    that the repair has started.

                                @elseif($activeJob->service_status === 'repairing')

                                    Repair is currently in progress.
                                    Waiting for the rider to confirm
                                    service completion.

                                @endif

                            </p>

                        </div>

                    @endif

                @endif

            </article>

        @empty

            <div style="
                text-align:center;
                padding:24px;
            ">

                <i data-lucide="briefcase"></i>

                <p class="muted">

                    No active requests.

                </p>

            </div>

        @endforelse

    </section>


    {{-- RECENTLY COMPLETED REQUESTS --}}
    <section
        class="card"
        id="completed-requests"
        style="margin-top:18px">

        <div class="card-header">

            <h2>Recently completed</h2>

            <span class="badge">

                {{ $completedJobs->count() }}

            </span>

        </div>

        @forelse($completedJobs as $completedJob)

            @php
                $completedHistory = $completedJob->statusHistory
                    ->where('status', 'completed')
                    ->sortByDesc('created_at')
                    ->first();

                $completedAt = $completedHistory?->created_at
                    ?? $completedJob->updated_at;
            @endphp

            <article
                class="request-card completed-request-card"
                data-completed-id="{{ $completedJob->id }}">

                <div class="card-header">

                    <strong>

                        {{ $completedJob->reference }}

                    </strong>

                    <span class="badge">

                        Completed

                    </span>

                </div>

                <div class="completed-request-details">

                    <p>

                        <strong>Rider:</strong>

                        {{ $completedJob->rider?->name ?? 'Rider not recorded' }}

                    </p>

                    <p>

                        <strong>Mechanic:</strong>

                        {{ $completedJob->assignedMechanicMember?->name ?? 'Mechanic not recorded' }}

                    </p>

                    <p>

                        <strong>Vehicle:</strong>

                        {{ $completedJob->vehicle?->brand }}
                        {{ $completedJob->vehicle?->model }}

                        @if($completedJob->vehicle?->plate_number)

                            · {{ $completedJob->vehicle->plate_number }}

                        @endif

                    </p>

                    <p>

                        <strong>Services:</strong>

                        {{ implode(', ', $completedJob->services ?? [$completedJob->issue_category]) }}

                    </p>

                    @if($completedJob->problem_description)

                        <p>

                            <strong>Issue:</strong>

                            {{ $completedJob->problem_description }}

                        </p>

                    @endif

                </div>

                <small class="muted">

                    Completed

                    {{ $completedAt?->diffForHumans() ?? 'Time unavailable' }}

                    ·

                    {{ $completedAt?->format('M j, Y g:i A') ?? 'Date unavailable' }}

                </small>

            </article>

        @empty

            <div style="
                text-align:center;
                padding:24px;
            ">

                <i data-lucide="check-circle"></i>

                <p class="muted">

                    No completed shop requests yet.

                </p>

            </div>

        @endforelse

    </section>

</div>


{{-- AUTOMATIC REQUEST UPDATES --}}
<script>

(() => {

    let isChecking = false;

    let isSubmitting = false;


    // UPDATE REQUEST COUNTDOWN
    function updateCountdowns() {

        document.querySelectorAll('.offer-countdown')
            .forEach(element => {

                const expiresAt = new Date(
                    element.dataset.expires
                ).getTime();

                if (!Number.isFinite(expiresAt)) {

                    element.textContent = 'Unavailable';

                    return;

                }

                const remaining = Math.max(
                    0,
                    Math.ceil(
                        (expiresAt - Date.now()) / 1000
                    )
                );

                const card = element.closest(
                    '.incoming-offer'
                );

                if (remaining <= 0) {

                    element.textContent = 'Expired';

                    element.style.color = '#dc2626';

                    card?.querySelectorAll('.offer-action')
                        .forEach(button => {

                            button.disabled = true;

                        });

                    return;

                }

                const minutes = Math.floor(
                    remaining / 60
                );

                const seconds = remaining % 60;

                element.textContent =
                    `${minutes}:${String(seconds).padStart(2, '0')}`;

                element.style.color =
                    remaining <= 30
                        ? '#dc2626'
                        : '#FF0000';

            });

    }


    // PREVENT UPDATES DURING FORM SUBMISSION
    document.addEventListener('submit', event => {

        if (!event.defaultPrevented) {

            isSubmitting = true;

        }

    });


    // CHECK IF THE MECHANIC IS EDITING A FORM
    function isEditingForm() {

        const activeElement = document.activeElement;

        if (
            activeElement?.matches(
                'input, textarea, select'
            )
        ) {

            return true;

        }

        return [...document.querySelectorAll(
            '#active-requests input[name="note"], ' +
            '#active-requests textarea, ' +
            '#active-requests select'
        )].some(element => {

            return element.value.trim() !== '';

        });

    }


    // GENERATE REQUEST SECTION SIGNATURE
    function getSectionSignature(section, selector) {

        return [...section.querySelectorAll(selector)]
            .map(element => [

                element.dataset.offerId ?? '',

                element.dataset.jobId ?? '',

                element.dataset.jobStatus ?? '',

                element.dataset.jobMember ?? '',

                element.dataset.completedId ?? ''

            ].join(':'))

            .join('|');

    }


    // AUTOMATICALLY CHECK UPDATED REQUESTS
    async function checkRequests() {

        if (
            isChecking ||
            isSubmitting ||
            document.hidden
        ) {

            return;

        }

        isChecking = true;

        try {

            const response = await fetch(

                @json(route('mechanic.shop-requests')),

                {

                    method: 'GET',

                    credentials: 'same-origin',

                    cache: 'no-store'

                }

            );

            if (
                !response.ok ||
                response.redirected
            ) {

                return;

            }

            const html = await response.text();

            const updatedDocument = new DOMParser()
                .parseFromString(html, 'text/html');

            if (isSubmitting) {

                return;

            }


            const sections = [

                {

                    id: 'incoming-requests',

                    selector: '[data-offer-id]'

                },

                {

                    id: 'active-requests',

                    selector: '[data-job-id]'

                },

                {

                    id: 'completed-requests',

                    selector: '[data-completed-id]'

                }

            ];


            let changed = false;


            sections.forEach(section => {

                // Preserve active form edits
                if (
                    section.id === 'active-requests' &&
                    isEditingForm()
                ) {

                    return;

                }

                const currentSection =
                    document.getElementById(section.id);

                const updatedSection =
                    updatedDocument.getElementById(section.id);

                if (
                    !currentSection ||
                    !updatedSection
                ) {

                    return;

                }


                const currentSignature = getSectionSignature(

                    currentSection,

                    section.selector

                );

                const updatedSignature = getSectionSignature(

                    updatedSection,

                    section.selector

                );


                const currentOptions = [
                    ...currentSection.querySelectorAll('select')
                ].map(select => select.innerHTML).join('|');

                const updatedOptions = [
                    ...updatedSection.querySelectorAll('select')
                ].map(select => select.innerHTML).join('|');


                if (
                    currentSignature !== updatedSignature ||
                    currentOptions !== updatedOptions
                ) {

                    currentSection.innerHTML =
                        updatedSection.innerHTML;

                    changed = true;

                }

            });


            // Refresh icons
            if (
                changed &&
                typeof window.lucide?.createIcons === 'function'
            ) {

                window.lucide.createIcons();

            }

            updateCountdowns();

        } catch (error) {

            console.error(

                'Unable to update shop requests:',

                error

            );

        } finally {

            isChecking = false;

        }

    }


    // INITIALIZE
    updateCountdowns();

    checkRequests();


    // Update countdown every second
    setInterval(updateCountdowns, 1000);


    // Check new requests every five seconds
    setInterval(checkRequests, 5000);

})();

</script>

@endsection
