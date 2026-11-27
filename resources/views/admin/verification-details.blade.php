@extends('layouts.app')

@section('title', 'Shop Verification')

@section('content')

@php

    $isPending = in_array($profile->verification_status, [
        'pending_verification', 'under_review', 'partially_verified',
    ], true);
    $statusLabels = [
        'pending_verification' => 'Pending Verification',
        'under_review' => 'Under Review',
        'partially_verified' => 'Partially Verified — Resubmission Required',
        'fully_verified' => 'Fully Verified',
        'rejected' => 'Rejected',
    ];

    $documents = $profile->documents
        ->keyBy('document_type');

    $documentTypes =
        \App\Models\MechanicDocument::TYPES;

@endphp

<div class="vf-admin-page">

    <!-- PAGE HEADER -->

    <div class="vf-admin-heading">

        <div>

            <a href="{{ route('admin.verification') }}"
               class="vf-back-link">

                <i data-lucide="arrow-left"></i>

                Back to Verification

            </a>

            <h1>Mechanic Shop Verification</h1>

            <p>

                Review shop information and submitted documents.

            </p>

        </div>

        <span class="vf-badge
            vf-badge-{{ $profile->verification_status }}">

            {{ $statusLabels[$profile->verification_status] ?? ucfirst($profile->verification_status) }}

        </span>

    </div>

    <section class="vf-panel vf-progress-card" aria-label="Verification progress">
        <div class="vf-progress-heading">
            <strong>Verification progress</strong>
            <span>{{ $progress['percentage'] }}% — {{ $progress['approved'] }} of {{ $progress['total'] }} requirements approved</span>
        </div>
        <div class="vf-progress-track" role="progressbar" aria-valuenow="{{ $progress['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
            <span style="width: {{ $progress['percentage'] }}%"></span>
        </div>
    </section>


    <!-- SUCCESS MESSAGE -->

    @if (session('success'))

        <div class="vf-alert vf-alert-success">

            {{ session('success') }}

        </div>

    @endif


    <!-- VALIDATION ERRORS -->

    @if ($errors->any())

        <div class="vf-alert vf-alert-danger">

            <div>

                <strong>Unable to complete the action.</strong>

                <ul>

                    @foreach ($errors->all() as $error)

                        <li>{{ $error }}</li>

                    @endforeach

                </ul>

            </div>

        </div>

    @endif


    <!-- SHOP INFORMATION -->

    <section class="vf-panel">

        <div class="vf-section-heading">

            <h2>Shop Information</h2>

        </div>

        <div class="vf-shop-summary">

            <div class="vf-shop-icon">

                <i data-lucide="store"></i>

            </div>

            <div>

                <h3>{{ $profile->shop_name }}</h3>

                <p>{{ $profile->service_address }}</p>

            </div>

        </div>

        <div class="vf-info-grid">

            <div>

                <small>Shop owner</small>

                <strong>{{ $user->name }}</strong>

            </div>

            <div>

                <small>Email address</small>

                <strong>{{ $user->email }}</strong>

            </div>

            <div>

                <small>Owner's contact number</small>

                <strong>{{ $user->phone ?? 'Not provided' }}</strong>

            </div>

            <div>

                <small>Shop contact number</small>

                <strong>{{ $profile->shop_phone ?? 'Not provided' }}</strong>

            </div>

            <div>

                <small>Barangay</small>

                <strong>{{ $profile->getAttribute('barangay') ?? 'Unassigned' }}</strong>

            </div>

            <div>

                <small>Registered mechanics</small>

                <strong>{{ $profile->members->count() }}</strong>

            </div>

            <div>

                <small>Latitude</small>

                <strong>{{ $profile->latitude ?? 'Unavailable' }}</strong>

            </div>

            <div>

                <small>Longitude</small>

                <strong>{{ $profile->longitude ?? 'Unavailable' }}</strong>

            </div>

        </div>

    </section>


    <!-- MECHANIC TEAM -->

    <section class="vf-panel">

        <div class="vf-section-heading">

            <h2>Registered Mechanics</h2>

        </div>

        <div class="vf-document-list">

            @forelse ($profile->members as $member)

                <div class="vf-document">

                    <div class="vf-document-title">

                        <i data-lucide="user-round"></i>

                        <strong>{{ $member->name }}</strong>

                    </div>

                </div>

            @empty

                <p class="vf-muted">

                    No registered mechanics.

                </p>

            @endforelse

        </div>

    </section>


    <!-- SUBMITTED DOCUMENTS -->

    <section class="vf-panel">

        <div class="vf-section-heading">

            <h2>Submitted Documents</h2>

            <p>

                Review each required document before
                approving the application.

            </p>

        </div>

        <div class="vf-document-list">

            @foreach ($documentTypes as $type => $label)

                @php

                    $document = $documents->get($type);

                    $documentStatus = $document?->status ?? 'pending';

                @endphp

                <article class="vf-document">

                    <!-- DOCUMENT STATUS -->

                    <div class="vf-document-header">

                        <div class="vf-document-title">

                            <i data-lucide="file-text"></i>

                            <strong>{{ $label }}</strong>

                        </div>

                        <span class="vf-badge
                            vf-badge-{{ $documentStatus }}">

                            {{ ucfirst($documentStatus) }}

                        </span>

                    </div>

                    @if ($document)

                        <div class="vf-document-content">

                            <p class="vf-muted">

                                File:

                                {{ $document->original_filename }}

                            </p>

                            @if ($document->expiration_date)

                                <p class="vf-expiration">

                                    Expiration:

                                    {{ $document->expiration_date
                                        ->format('F d, Y') }}

                                </p>

                            @endif


                            <!-- EXISTING REJECTION REASON -->

                            @if ($document->rejection_reason)

                                <div class="vf-rejection-summary">

                                    <div>

                                        <h3>Rejection Reason</h3>

                                        <p class="vf-preserve-lines">{{ $document->rejection_reason }}</p>

                                    </div>

                                </div>

                            @endif


                            <!-- DOCUMENT ACTIONS -->

                            <div class="vf-document-actions">

                                <a

                                    href="{{ action(
                                        [
                                            \App\Http\Controllers\ShopVerificationController::class,
                                            'viewDocument'
                                        ],
                                        ['document' => $document->id]
                                    ) }}"

                                    target="_blank"

                                    rel="noopener noreferrer"

                                    class="vf-button vf-outline">

                                    <i data-lucide="eye"></i>

                                    View Document

                                </a>

                                @if ($isPending && ($document->status !== 'approved' || $document->isExpired()))

                                    <form

                                        method="POST"

                                        action="{{ action(
                                            [
                                                \App\Http\Controllers\ShopVerificationController::class,
                                                'reviewDocument'
                                            ],
                                            ['document' => $document->id]
                                        ) }}">

                                        @csrf

                                        <input type="hidden"
                                               name="decision"
                                               value="approve">

                                        <button type="submit"

                                                class="vf-button vf-primary"

                                                @disabled(
                                                    $document->isExpired()
                                                    || $document->status === 'approved'
                                                )>

                                            <i data-lucide="check"></i>

                                            Approve

                                        </button>

                                    </form>


                                    <button

                                        type="button"

                                        class="vf-button vf-danger-outline"

                                        data-vf-reject

                                        data-target-label="{{ $label }}"

                                        data-target-type="document"

                                        data-target-url="{{ action(
                                            [
                                                \App\Http\Controllers\ShopVerificationController::class,
                                                'reviewDocument'
                                            ],
                                            ['document' => $document->id]
                                        ) }}">

                                        <i data-lucide="x"></i>

                                        Reject

                                    </button>

                                @endif

                            </div>

                        </div>

                    @else

                        <p class="vf-reason">

                            This required document has not been submitted.

                        </p>

                    @endif

                </article>

            @endforeach

        </div>

    </section>


    <!-- OVERALL APPLICATION FEEDBACK -->

    @if (
        $profile->verification_status === 'rejected'
        && $latestVerification?->rejection_reason
    )

        <section class="vf-panel">

            <h2>Application Rejection Reason</h2>

            <div class="vf-rejection-summary">

                <p class="vf-preserve-lines">{{ $latestVerification->rejection_reason }}</p>

            </div>

        </section>

    @endif


    <!-- FINAL APPLICATION DECISION -->

    @if ($isPending)

        <section class="vf-panel">

            <div class="vf-section-heading">

                <h2>Application Decision</h2>

                <p>Individual document decisions update the overall status automatically. Reject the application only when you are denying the full application.</p>

            </div>

            <div class="vf-final-actions">

                <button

                    type="button"

                    class="vf-button vf-danger-outline"

                    data-vf-reject

                    data-target-label="{{ $profile->shop_name }}"

                    data-target-type="application"

                    data-target-url="{{ action(
                        [
                            \App\Http\Controllers\ShopVerificationController::class,
                            'decide'
                        ],
                        ['user' => $user->id]
                    ) }}">

                    <i data-lucide="circle-x"></i>

                    Reject Application

                </button>

            </div>

        </section>

    @endif

</div>


<!-- =====================================
     REUSABLE REJECTION MODAL
     ===================================== -->

<dialog id="vf-rejection-dialog"
        class="vf-dialog">

    <form method="POST"
          id="vf-rejection-form">

        @csrf

        <input type="hidden"
               name="decision"
               value="reject">


        <!-- MODAL HEADER -->

        <div class="vf-dialog-header">

            <div class="vf-status-icon vf-red-icon">

                <i data-lucide="circle-x"></i>

            </div>

            <button type="button"

                    id="vf-close-dialog"

                    class="vf-close-button"

                    aria-label="Close rejection form">

                <i data-lucide="x"></i>

            </button>

            <h2 id="vf-dialog-title">

                Reject Document

            </h2>

            <p>

                Provide a clear reason why this
                submission cannot be approved.

            </p>

        </div>


        <!-- DOCUMENT UNDER REVIEW -->

        <div class="vf-dialog-target">

            <small id="vf-target-caption">

                Document under review

            </small>

            <strong id="vf-target-name"></strong>

        </div>


        <!-- REJECTION REASONS -->

        <fieldset class="vf-reason-options">

            <legend>

                Reason for rejection *

            </legend>

            @foreach ($rejectionReasons as $reason)

                <label class="vf-radio-option">

                    <input

                        type="radio"

                        name="reason_category"

                        value="{{ $reason }}"

                        required

                    >

                    <span>{{ $reason }}</span>

                </label>

            @endforeach

        </fieldset>


        <!-- ADDITIONAL EXPLANATION -->

        <label class="vf-field">

            <span>Additional explanation *</span>

            <textarea

                name="explanation"

                id="vf-explanation"

                rows="4"

                maxlength="850"

                placeholder="Explain what the shop owner needs to correct..."

                required

            ></textarea>

        </label>

        <p class="vf-muted">

            The rejection reason will be visible to the shop owner.

        </p>


        <!-- MODAL BUTTONS -->

        <div class="vf-dialog-actions">

            <button

                type="reset"

                class="vf-button vf-outline">

                Reset

            </button>

            <button

                type="submit"

                id="vf-confirm-rejection"

                class="vf-button vf-danger"

                disabled>

                Confirm Rejection

            </button>

        </div>

    </form>

</dialog>


<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            const dialog =
                document.getElementById(
                    'vf-rejection-dialog'
                );

            const form =
                document.getElementById(
                    'vf-rejection-form'
                );

            const explanation =
                document.getElementById(
                    'vf-explanation'
                );

            const confirmButton =
                document.getElementById(
                    'vf-confirm-rejection'
                );

            let submitted = false;


            // Enable confirmation only when
            // both required fields are completed.

            function validateForm() {

                const selectedReason =
                    form.querySelector(
                        'input[name="reason_category"]:checked'
                    );

                confirmButton.disabled =

                    submitted

                    || !selectedReason

                    || explanation.value.trim() === '';

            }


            // Open the correct rejection modal.

            document.querySelectorAll(
                '[data-vf-reject]'
            ).forEach(button => {

                button.addEventListener(
                    'click',
                    function () {

                        submitted = false;

                        form.reset();

                        form.action =
                            button.dataset.targetUrl;

                        const isApplication =

                            button.dataset.targetType
                                === 'application';

                        document.getElementById(
                            'vf-dialog-title'
                        ).textContent =

                            isApplication

                                ? 'Reject Application'

                                : 'Reject Document';

                        document.getElementById(
                            'vf-target-caption'
                        ).textContent =

                            isApplication

                                ? 'Application under review'

                                : 'Document under review';

                        document.getElementById(
                            'vf-target-name'
                        ).textContent =
                            button.dataset.targetLabel;

                        confirmButton.textContent =
                            'Confirm Rejection';

                        validateForm();

                        dialog.showModal();

                    }
                );

            });


            // Validate inputs.

            form.addEventListener(
                'input',
                validateForm
            );

            form.addEventListener(
                'change',
                validateForm
            );

            form.addEventListener(
                'reset',
                function () {

                    requestAnimationFrame(
                        validateForm
                    );

                }
            );


            // Close modal.

            document.getElementById(
                'vf-close-dialog'
            ).addEventListener(
                'click',
                function () {

                    dialog.close();

                }
            );


            // Prevent invalid and duplicate submissions.

            form.addEventListener(
                'submit',
                function (event) {

                    validateForm();

                    if (confirmButton.disabled) {

                        event.preventDefault();

                        return;

                    }

                    submitted = true;

                    confirmButton.disabled = true;

                    confirmButton.textContent =
                        'Submitting Rejection...';

                }
            );

            window.lucide?.createIcons();

        }
    );

</script>

@endsection
