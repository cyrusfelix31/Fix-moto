@extends('layouts.app')
@section('title', 'Service Request '.$serviceRequest->reference)
@section('page_title', 'Request '.$serviceRequest->reference)

@section('content')

<div class="page rider-tracking-page">

    {{-- Breadcrumb --}}
    <nav class="rider-breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li>
                <a href="{{ route('rider.dashboard') }}">
                    Dashboard
                </a>
            </li>

            <li>
                <a href="{{ route('rider.history') }}">
                    Service history
                </a>
            </li>

            <li aria-current="page">
                {{ $serviceRequest->reference }}
            </li>
        </ol>
    </nav>

    {{-- Success message --}}
    @if(session('success'))
        <div class="alert success" role="status">
            {{ session('success') }}
        </div>
    @endif

    {{-- Warning message --}}
    @if(session('warning'))
        <div class="alert warning">
            {{ session('warning') }}
        </div>
    @endif

    {{-- Validation errors --}}
    @if($errors->any())
        <div class="alert error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif


    {{-- Service status configuration --}}
    @php
        $steps = [
            'accepted' => 'Request accepted',
            'on_the_way' => 'Mechanic on the way',
            'arrived' => 'Mechanic arrived',
            'repairing' => 'Repair in progress',
            'completed' => 'Service completed',
        ];

        $statusLabels = [
            'pending' => 'Waiting',
            'accepted' => 'Accepted',
            'on_the_way' => 'On the way',
            'arrived' => 'Arrived',
            'diagnosing' => 'Arrived',
            'repairing' => 'Repairing',
            'completed' => 'Completed',
        ];

        $currentStatus = $serviceRequest->service_status;

        $displayStatus = $currentStatus === 'diagnosing'
            ? 'arrived'
            : $currentStatus;

        $currentIndex = array_search(
            $displayStatus,
            array_keys($steps),
            true
        );

        $nextStatus = [
            'arrived' => 'repairing',
            'diagnosing' => 'repairing',
            'repairing' => 'completed',
        ][$currentStatus] ?? null;

        $buttonLabels = [
            'repairing' => 'Confirm Repair Started',
            'completed' => 'Confirm Service Completed',
        ];

        $instructions = [
            'repairing' =>
                'Confirm when the mechanic starts repairing your vehicle.',

            'completed' =>
                'Confirm only when the requested repair has been completed.',
        ];

        $modalTitles = [
            'repairing' => 'Confirm Repair Started?',
            'completed' => 'Confirm Service Completion?',
        ];

        $modalMessages = [
            'repairing' =>
                'Please confirm that the mechanic has started repairing your vehicle.',

            'completed' =>
                'Please confirm that the requested repair is finished before completing this service.',
        ];

        $canConfirm = $nextStatus
            && $serviceRequest->mechanic_shop_member_id
            && $serviceRequest->dispatch_status === 'assigned';
    @endphp


    {{-- Server status for automatic updates --}}
    <div
        id="server-service-status"
        data-status="{{ $currentStatus }}"
        data-dispatch="{{ $serviceRequest->dispatch_status }}"
        data-member="{{ $serviceRequest->mechanic_shop_member_id }}"
        hidden>
    </div>


    <div class="grid content-grid rider-tracking-grid">

        {{-- SERVICE PROGRESS --}}
        <section class="card rider-progress-card">

            <div class="rider-progress-header">

                <div>
                    <h2>Service progress</h2>

                    <p class="muted">
                        Track your roadside assistance.
                    </p>
                </div>

                <span class="rider-status-badge {{ $currentStatus === 'completed' ? 'status-completed' : '' }}">

                    {{ $statusLabels[$currentStatus] ?? $serviceRequest->serviceStatusLabel() }}

                </span>

            </div>


            {{-- Progress bar --}}
            <div class="rider-progress-bar">

                @foreach($steps as $status => $label)

                    @php
                        $index = array_search(
                            $status,
                            array_keys($steps),
                            true
                        );

                        $isDone = $currentIndex !== false
                            && $index <= $currentIndex;
                    @endphp

                    <span class="{{ $isDone ? 'done' : '' }}">
                    </span>

                @endforeach

            </div>


            {{-- Service timeline --}}
            <div class="rider-service-timeline">

                @foreach($steps as $status => $label)

                    @php
                        $index = array_search(
                            $status,
                            array_keys($steps),
                            true
                        );

                        $isDone = $currentIndex !== false
                            && $index <= $currentIndex;

                        $isCurrent = $displayStatus === $status;
                    @endphp

                    <div class="rider-timeline-item {{ $isDone ? 'is-done' : '' }} {{ $isCurrent ? 'is-current' : '' }}">

                        <span class="rider-timeline-icon">

                            <i data-lucide="{{ $isDone ? 'check' : 'clock' }}"></i>

                        </span>

                        <div class="rider-timeline-content">

                            <strong>
                                {{ $label }}
                            </strong>

                            @if($isCurrent)
                                <small class="muted">
                                    Current status
                                </small>
                            @endif

                        </div>

                    </div>

                @endforeach

            </div>


            {{-- Request details --}}
            <div class="rider-service-details">

                <div class="rider-detail-row">

                    <span>Services</span>

                    <strong>
                        {{ implode(', ', $serviceRequest->services ?? [$serviceRequest->issue_category]) }}
                    </strong>

                </div>

                @if($serviceRequest->notes || $serviceRequest->problem_description)

                    <div class="rider-detail-row">

                        <span>Notes</span>

                        <strong>
                            {{ $serviceRequest->notes ?: $serviceRequest->problem_description }}
                        </strong>

                    </div>

                @endif

                <div class="rider-detail-row">

                    <span>Vehicle</span>

                    <strong>
                        {{ $serviceRequest->vehicle?->brand }}
                        {{ $serviceRequest->vehicle?->model }}
                    </strong>

                </div>

                <div class="rider-detail-row">

                    <span>Vehicle category</span>

                    <strong>
                        {{ $serviceRequest->vehicle?->vehicle_category === 'four_wheeled' ? 'Four-wheeled' : 'Two-wheeled' }}
                    </strong>

                </div>

                <div class="rider-detail-row">

                    <span>Mechanic shop</span>

                    <strong>
                        {{ $serviceRequest->mechanic?->mechanicProfile?->shop_name ?? 'Waiting for a shop' }}
                    </strong>

                </div>

                <div class="rider-detail-row">

                    <span>Assigned mechanic</span>

                    <strong>
                        {{ $serviceRequest->assignedMechanicMember?->name ?? 'Shop will assign a mechanic' }}
                    </strong>

                </div>

            </div>


            {{-- RIDER CONFIRMATION --}}
            <section class="rider-confirmation-card">

                @if($currentStatus === 'completed')

                    <div class="rider-completion-message">

                        <span class="rider-completion-icon">
                            <i data-lucide="check"></i>
                        </span>

                        <h3>Service Completed</h3>

                        <p>
                            Your roadside assistance has
                            been successfully completed.
                        </p>

                        <a
                            href="{{ route('rider.history') }}"
                            class="btn primary rider-confirm-btn">

                            View Service History

                        </a>

                    </div>

                @elseif($canConfirm)

                    <div class="rider-confirmation-heading">

                        <h3>Confirm Service Progress</h3>

                        <p class="muted">
                            {{ $instructions[$nextStatus] }}
                        </p>

                    </div>

                    <form
                        method="POST"
                        id="rider-confirmation-form"
                        action="{{ route('rider.requests.status', $serviceRequest) }}">

                        @csrf

                        <input
                            type="hidden"
                            name="status"
                            value="{{ $nextStatus }}">

                        <label
                            for="rider-progress-note"
                            class="rider-note-label">

                            Progress note
                            <span>(optional)</span>

                        </label>

                        <textarea
                            id="rider-progress-note"
                            name="note"
                            rows="3"
                            maxlength="1000"
                            placeholder="Add an optional update...">{{ old('note') }}</textarea>

                        <label class="rider-progress-confirmation">
                            <input type="checkbox" name="confirmed" value="1" required>
                            <span>
                                @if($nextStatus === 'repairing')
                                    I confirm the mechanic has started working on my vehicle.
                                @else
                                    I confirm the requested service has been completed.
                                @endif
                            </span>
                        </label>

                        {{-- Plain text confirmation button --}}
                        <button
                            id="open-confirmation-modal"
                            type="button"
                            class="btn primary rider-confirm-btn">

                            {{ $buttonLabels[$nextStatus] }}

                        </button>

                    </form>

                @elseif($serviceRequest->dispatch_status === 'no_mechanics')

                    <h3>No mechanic available</h3>

                    <p class="muted">
                        No eligible mechanic shop was found.
                    </p>

                @elseif($serviceRequest->dispatch_status === 'cancelled')

                    <h3>Request cancelled</h3>

                    <p class="muted">
                        This request is no longer active.
                    </p>

                @else

                    <h3>Waiting for Mechanic</h3>

                    <p class="muted">
                        The confirmation button will appear
                        when your assigned mechanic is on the way.
                    </p>

                @endif

            </section>

        </section>


        {{-- DISPATCH STATUS AND MAP --}}
        <section class="card rider-dispatch-card">

            <div class="rider-progress-header">

                <h2>Dispatch status</h2>

                <span class="rider-status-badge">

                    {{ $currentStatus === 'completed'
                        ? 'Completed'
                        : ucwords(str_replace('_', ' ', $serviceRequest->dispatch_status)) }}

                </span>

            </div>

            <p class="muted rider-coordinate-text">

                Coordinates:
                {{ $serviceRequest->latitude }},
                {{ $serviceRequest->longitude }}

            </p>

            <div
                id="request-map"
                class="map rider-request-map">
            </div>

            <div class="rider-landmark">

                <strong>Landmark</strong>

                <p>
                    {{ $serviceRequest->landmark ?? 'Not specified' }}
                </p>

            </div>

        </section>

    </div>


    {{-- SERVICE ACTIVITY --}}
    <section class="card rider-activity-card">

        <div class="card-header">

            <h2>Service activity</h2>

            <span class="badge">
                {{ $serviceRequest->statusHistory->count() }}
            </span>

        </div>

        @forelse($serviceRequest->statusHistory->sortByDesc('created_at') as $history)

            <article class="request-card rider-activity-item">

                <div>

                    <strong>
                        {{ $statusLabels[$history->status] ?? ucwords(str_replace('_', ' ', $history->status)) }}
                    </strong>

                    @if($history->note)
                        <p>
                            {{ $history->note }}
                        </p>
                    @endif

                </div>

                <small class="muted">
                    {{ $history->created_at?->format('M j, Y g:i A') }}
                </small>

            </article>

        @empty

            <p class="muted">
                No service progress updates yet.
            </p>

        @endforelse

    </section>

</div>


{{-- PROFESSIONAL CONFIRMATION MODAL --}}
@if($canConfirm)

    <dialog
        id="service-confirmation-modal"
        class="fixmoto-confirmation-modal"
        aria-labelledby="confirmation-modal-title"
        aria-describedby="confirmation-modal-description">

        <div class="fixmoto-modal-content">

            <button
                type="button"
                id="close-confirmation-modal"
                class="fixmoto-modal-close"
                aria-label="Close confirmation">

                &times;

            </button>

            <div class="fixmoto-modal-icon">

                <i data-lucide="clipboard-check"></i>

            </div>

            <h3 id="confirmation-modal-title">

                {{ $modalTitles[$nextStatus] }}

            </h3>

            <p id="confirmation-modal-description">

                {{ $modalMessages[$nextStatus] }}

            </p>

            <div class="fixmoto-modal-actions">

                <button
                    type="button"
                    id="cancel-confirmation"
                    class="fixmoto-modal-btn cancel-btn">

                    Cancel

                </button>

                <button
                    type="button"
                    id="confirm-service-action"
                    class="fixmoto-modal-btn confirm-btn">

                    Confirm

                </button>

            </div>

        </div>

    </dialog>

@endif


<script>
(() => {

    let isChecking = false;
    let isSubmitting = false;

    const latitude = Number(
        @json($serviceRequest->latitude)
    );

    const longitude = Number(
        @json($serviceRequest->longitude)
    );


    // INITIALIZE GPS MAP
    if (
        typeof L !== 'undefined' &&
        Number.isFinite(latitude) &&
        Number.isFinite(longitude)
    ) {

        const requestMap = L.map('request-map')
            .setView([latitude, longitude], 14);

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }
        ).addTo(requestMap);

        L.marker([latitude, longitude])
            .addTo(requestMap)
            .bindPopup('Service location');

        window.setTimeout(() => {
            requestMap.invalidateSize();
        }, 100);

    }


    // GET FORM AND MODAL ELEMENTS
    const statusMarker = document.getElementById(
        'server-service-status'
    );

    const confirmationForm = document.getElementById(
        'rider-confirmation-form'
    );

    const confirmationModal = document.getElementById(
        'service-confirmation-modal'
    );

    const openModalButton = document.getElementById(
        'open-confirmation-modal'
    );

    const closeModalButton = document.getElementById(
        'close-confirmation-modal'
    );

    const cancelButton = document.getElementById(
        'cancel-confirmation'
    );

    const confirmButton = document.getElementById(
        'confirm-service-action'
    );


    // OPEN CUSTOM CONFIRMATION MODAL
    openModalButton?.addEventListener('click', () => {

        if (!isSubmitting && confirmationModal) {
            confirmationModal.showModal();
        }

    });


    // CLOSE MODAL
    function closeConfirmationModal() {

        if (
            confirmationModal?.open &&
            !isSubmitting
        ) {
            confirmationModal.close();
        }

    }

    closeModalButton?.addEventListener(
        'click',
        closeConfirmationModal
    );

    cancelButton?.addEventListener(
        'click',
        closeConfirmationModal
    );


    // CLOSE WHEN CLICKING THE BACKDROP
    confirmationModal?.addEventListener('click', event => {

        if (event.target === confirmationModal) {
            closeConfirmationModal();
        }

    });


    // PREVENT CLOSING DURING SUBMISSION
    confirmationModal?.addEventListener('cancel', event => {

        if (isSubmitting) {
            event.preventDefault();
        }

    });


    // CONFIRM SERVICE STATUS
    confirmButton?.addEventListener('click', () => {

        if (!isSubmitting && confirmationForm) {

            confirmationForm.requestSubmit();

        }

    });


    // PREVENT DUPLICATE SUBMISSIONS
    confirmationForm?.addEventListener('submit', event => {

        if (isSubmitting) {

            event.preventDefault();

            return;

        }

        isSubmitting = true;

        if (confirmButton) {

            confirmButton.disabled = true;

            confirmButton.textContent = 'Confirming...';

        }

        if (cancelButton) {
            cancelButton.disabled = true;
        }

        if (closeModalButton) {
            closeModalButton.disabled = true;
        }

        if (openModalButton) {
            openModalButton.disabled = true;
        }

    });


    // CHECK IF RIDER IS EDITING THE FORM
    function isEditingForm() {

        const note = confirmationForm?.querySelector(
            'textarea[name="note"]'
        );

        return Boolean(
            confirmationModal?.open ||
            (
                note &&
                (
                    note === document.activeElement ||
                    note.value.trim() !== ''
                )
            )
        );

    }


    // AUTOMATIC SERVICE STATUS UPDATES
    const requestUrl = @json(
        route('rider.requests.show', $serviceRequest)
    );


    async function checkServiceStatus() {

        if (
            isChecking ||
            isSubmitting ||
            document.hidden ||
            isEditingForm() ||
            !statusMarker ||
            statusMarker.dataset.status === 'completed'
        ) {
            return;
        }

        isChecking = true;

        try {

            const response = await fetch(requestUrl, {

                method: 'GET',

                credentials: 'same-origin',

                cache: 'no-store'

            });

            if (!response.ok || response.redirected) {
                return;
            }

            const html = await response.text();

            const updatedDocument = new DOMParser()
                .parseFromString(html, 'text/html');

            const updatedMarker = updatedDocument
                .getElementById('server-service-status');

            if (
                !updatedMarker ||
                isSubmitting ||
                isEditingForm()
            ) {
                return;
            }

            if (
                updatedMarker.dataset.status !==
                    statusMarker.dataset.status ||

                updatedMarker.dataset.dispatch !==
                    statusMarker.dataset.dispatch ||

                updatedMarker.dataset.member !==
                    statusMarker.dataset.member
            ) {

                window.location.reload();

            }

        } catch (error) {

            console.error(
                'Unable to update service status:',
                error
            );

        } finally {

            isChecking = false;

        }

    }


    checkServiceStatus();

    setInterval(checkServiceStatus, 5000);

})();
</script>

@endsection
