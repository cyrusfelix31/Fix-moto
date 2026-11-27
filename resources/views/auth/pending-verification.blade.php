<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Verification Status | FixMoto</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"
            defer></script>

</head>

@php

    $status = $profile->verification_status;

    $isRejected = $status === 'rejected';
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

    $requiredExpiration =
        \App\Services\ShopDocumentService::EXPIRATION_REQUIRED;

@endphp

<body class="vf-body">

    <main class="vf-container">

        <!-- FIXMOTO HEADER -->

        <header class="vf-brand-header">

            <div class="vf-brand">

                <img class="vf-brand-logo"
                     src="{{ asset('images/fixmoto-login-logo-edited.png') }}?v=2"
                     alt="FixMoto">

                <small>Mechanic Shop Verification</small>

            </div>

            <form method="POST"
                  action="{{ route('logout') }}">

                @csrf

                <button type="submit"
                        class="vf-button vf-outline">

                    <i data-lucide="log-out"></i>

                    Logout

                </button>

            </form>

        </header>


        <section class="vf-panel">

            <!-- APPLICATION STATUS -->

            <div class="vf-status-header">

                <div class="vf-status-icon
                    {{ $isRejected ? 'vf-red-icon' : ($status === 'fully_verified' ? 'vf-green-icon' : 'vf-amber-icon') }}">

                    <i data-lucide="{{ $isRejected ? 'circle-x' : ($status === 'fully_verified' ? 'shield-check' : 'clock-3') }}"></i>

                </div>

                <h1>

                    {{ $statusLabels[$status] ?? 'Pending Verification' }}

                </h1>

                <p>

                    @if ($isRejected)

                        Your application requires corrections
                        before it can be approved.

                    @elseif ($status === 'partially_verified')

                        Some requirements are approved. Upload only rejected,
                        expired, or missing documents; approved documents remain on file.

                    @elseif ($status === 'under_review')

                        Your application is currently being
                        reviewed by the FixMoto administrator.

                    @else

                        Your requirements were submitted successfully and are waiting for administrator review.

                    @endif

                </p>

            </div>

            @php
                $verificationNotices = auth()->user()->notifications()->latest()->limit(3)->get();
            @endphp
            @if($verificationNotices->isNotEmpty())
                <section class="vf-notification-list" aria-label="Verification notifications">
                    <h2>Verification updates</h2>
                    @foreach($verificationNotices as $notice)
                        <article class="vf-notification-item">
                            <strong>{{ ucfirst(str_replace('_', ' ', $notice->data['status'] ?? 'Status update')) }}</strong>
                            <p>{{ $notice->data['message'] ?? 'Your verification status was updated.' }}</p>
                            @if(!empty($notice->data['reason']))
                                <p class="vf-preserve-lines">{{ $notice->data['reason'] }}</p>
                            @endif
                            <small>{{ $notice->created_at?->diffForHumans() }}</small>
                        </article>
                    @endforeach
                </section>
            @endif

            <section class="vf-progress-card" aria-label="Verification progress">
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

                    <i data-lucide="circle-check"></i>

                    <span>{{ session('success') }}</span>

                </div>

            @endif


            <!-- VALIDATION ERRORS -->

            @if ($errors->any())

                <div class="vf-alert vf-alert-danger">

                    <div>

                        <strong>
                            Please correct the following:
                        </strong>

                        <ul>

                            @foreach ($errors->all() as $error)

                                <li>{{ $error }}</li>

                            @endforeach

                        </ul>

                    </div>

                </div>

            @endif


            <!-- SHOP INFORMATION -->

            <div class="vf-shop-summary">

                <div class="vf-shop-icon">

                    <i data-lucide="store"></i>

                </div>

                <div>

                                <h3>{{ $profile->shop_name ?: 'Your mechanic shop' }}</h3>

                    <p>{{ $profile->service_address }}</p>

                </div>

            </div>


            <!-- OVERALL APPLICATION REJECTION REASON -->

            @if (
                $isRejected
                && $latestVerification?->rejection_reason
            )

                <div class="vf-rejection-summary">

                    <i data-lucide="info"></i>

                    <div>

                        <h3>Administrator's Feedback</h3>

                        <p class="vf-preserve-lines">{{ $latestVerification->rejection_reason }}</p>

                        @if ($latestVerification->reviewed_at)

                            <small>

                                Reviewed on

                                {{ $latestVerification
                                    ->reviewed_at
                                    ->format('F d, Y - h:i A') }}

                            </small>

                        @endif

                    </div>

                </div>

            @endif


            <!-- DOCUMENT RESUBMISSION FORM -->

            <form method="POST"

                  action="{{ route('mechanic.verification.resubmit') }}"

                  enctype="multipart/form-data"

                  id="vf-resubmit-form">

                @csrf

                <div class="vf-document-list">

                    @foreach ($documentTypes as $type => $label)

                        @php

                            $document = $documents->get($type);

                            $needsReplacement = in_array(
                                $type,
                                $replacementTypes,
                                true
                            );

                            $isExpired =
                                $document?->isExpired() ?? false;

                            $documentStatus = $document?->status ?? 'pending';

                            $reason = trim(
                                $document?->rejection_reason ?? ''
                            );

                            $reasonParts = explode(
                                "\n",
                                $reason,
                                2
                            );

                        @endphp


                        <article class="vf-document">

                            <!-- DOCUMENT TITLE AND STATUS -->

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


                            <!-- DOCUMENT EXPIRATION -->

                            @if ($document?->expiration_date)

                                <p class="vf-expiration">

                                    <i data-lucide="calendar-days"></i>

                                    Expiration date:

                                    {{ $document->expiration_date
                                        ->format('F d, Y') }}

                                </p>

                            @endif


                            <!-- APPROVED DOCUMENT -->

                            @if (
                                $documentStatus === 'approved'
                                && !$needsReplacement
                            )

                                <p class="vf-approved-note">

                                    <i data-lucide="shield-check"></i>

                                    This document is approved.
                                    No replacement is needed.

                                </p>

                            @endif


                            <!-- REJECTED OR INVALID DOCUMENT -->

                            @if ($needsReplacement)

                                <div class="vf-document-content">

                                    @if ($reason !== '')

                                        @if (count($reasonParts) === 2)

                                            <h4 class="vf-reason">

                                                Reason:
                                                {{ $reasonParts[0] }}

                                            </h4>

                                            <p class="vf-explanation
                                                vf-preserve-lines">

                                                {{ $reasonParts[1] }}

                                            </p>

                                        @else

                                            <h4 class="vf-reason">

                                                Reason for rejection

                                            </h4>

                                            <p class="vf-explanation
                                                vf-preserve-lines">

                                                {{ $reason }}

                                            </p>

                                        @endif

                                    @elseif ($isExpired)

                                        <h4 class="vf-reason">

                                            Reason: Expired document

                                        </h4>

                                        <p class="vf-explanation">

                                            Please upload a renewed
                                            and valid document.

                                        </p>

                                    @elseif (!$document)

                                        <h4 class="vf-reason">

                                            Reason: Missing document

                                        </h4>

                                        <p class="vf-explanation">

                                            Please upload the required document.

                                        </p>

                                    @else

                                        <h4 class="vf-reason">

                                            Document requires correction

                                        </h4>

                                        <p class="vf-explanation">

                                            Please provide a readable,
                                            complete and valid replacement
                                            document.

                                        </p>

                                    @endif


                                    @if ($canResubmit)

                                        <!-- DOCUMENT UPLOAD -->

                                        <label class="vf-upload">

                                            <i data-lucide="cloud-upload"></i>

                                            <strong>

                                                Re-upload Document

                                            </strong>

                                            <small>

                                                PDF, JPG or PNG — Max 5 MB

                                            </small>

                                            <input

                                                type="file"

                                                name="{{ $type }}"

                                                accept=".pdf,.jpg,.jpeg,.png"

                                                data-vf-upload

                                            >

                                            <span class="vf-file-name"
                                                  aria-live="polite">

                                                No file selected

                                            </span>

                                        </label>

                                        @error($type)

                                            <p class="vf-field-error">

                                                {{ $message }}

                                            </p>

                                        @enderror


                                        <!-- EXPIRATION DATE -->

                                        @if ($type !== 'bir_certificate')

                                            <label class="vf-field">

                                                <span>

                                                    New expiration date

                                                    @if (
                                                        in_array(
                                                            $type,
                                                            $requiredExpiration,
                                                            true
                                                        )
                                                    )

                                                        *

                                                    @else

                                                        (Optional)

                                                    @endif

                                                </span>

                                                <input

                                                    type="date"

                                                    name="{{ $type }}_expiration"

                                                    min="{{ now()->toDateString() }}"

                                                    value="{{ old($type.'_expiration') }}"

                                                    @if (
                                                        in_array(
                                                            $type,
                                                            $requiredExpiration,
                                                            true
                                                        )
                                                    )
                                                    data-vf-expiration-required
                                                    @endif

                                                >

                                            </label>

                                            @error($type.'_expiration')

                                                <p class="vf-field-error">

                                                    {{ $message }}

                                                </p>

                                            @enderror

                                        @endif

                                    @else

                                        <p class="vf-muted">

                                            Replacement uploads will become
                                            available when the administrator
                                            returns your application.

                                        </p>

                                    @endif

                                </div>

                            @endif


                            <!-- PENDING DOCUMENT -->

                            @if (
                                $documentStatus === 'pending'
                                && !$needsReplacement
                            )

                                <p class="vf-muted">

                                    This document is waiting for
                                    administrator review.

                                </p>

                            @endif

                        </article>

                    @endforeach

                </div>


                <!-- ADDITIONAL CORRECTION NOTE -->

                @if ($canResubmit)

                    <label class="vf-field">

                        <span>

                            Correction note (Optional)

                        </span>

                        <textarea

                            name="correction_note"

                            rows="3"

                            maxlength="1000"

                            placeholder="Explain the corrections you have made..."

                        >{{ old('correction_note') }}</textarea>

                    </label>

                    <button type="submit"

                            id="vf-submit-button"

                            class="vf-button vf-primary vf-full">

                        <i data-lucide="send"></i>

                        Resubmit for Verification

                    </button>

                    <p class="vf-footer-note">

                        Only documents requiring replacement
                        will be updated. Previously approved
                        and valid documents will be retained.

                    </p>

                @else

                    <div class="vf-waiting">

                        <i data-lucide="clock-3"></i>

                        Waiting for administrator review

                    </div>

                @endif

            </form>

        </section>

        <footer class="vf-page-footer">

            FixMoto &copy; {{ date('Y') }}

            — Calapan City, Oriental Mindoro

        </footer>

    </main>


    <!-- FILE UPLOAD VALIDATION -->

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                window.lucide?.createIcons();

                const uploadInputs =
                    document.querySelectorAll(
                        '[data-vf-upload]'
                    );

                uploadInputs.forEach(input => {

                    input.addEventListener(
                        'change',
                        function () {

                            const label =
                                input.closest('.vf-upload');

                            const fileName =
                                label.querySelector(
                                    '.vf-file-name'
                                );

                            input.setCustomValidity('');

                            const file = input.files[0];

                            if (!file) {

                                fileName.textContent =
                                    'No file selected';

                                return;

                            }

                            if (
                                !/\.(pdf|jpg|jpeg|png)$/i
                                    .test(file.name)
                            ) {

                                input.value = '';

                                fileName.textContent =
                                    'Invalid file format';

                                input.setCustomValidity(
                                    'Please upload a PDF, JPG or PNG file.'
                                );

                                input.reportValidity();

                                return;

                            }

                            if (file.size > 5 * 1024 * 1024) {

                                input.value = '';

                                fileName.textContent =
                                    'File exceeds 5 MB';

                                input.setCustomValidity(
                                    'The file must not exceed 5 MB.'
                                );

                                input.reportValidity();

                                return;

                            }

                            fileName.textContent =
                                file.name;

                        }
                    );

                });

                const form =
                    document.getElementById(
                        'vf-resubmit-form'
                    );

                const button =
                    document.getElementById(
                        'vf-submit-button'
                    );

                form?.querySelectorAll('[data-vf-expiration-required]').forEach(input => {
                    const file = form.querySelector(`[name="${CSS.escape(input.name.replace(/_expiration$/, ''))}"]`);
                    const sync = () => { input.required = Boolean(file?.files?.length); };
                    file?.addEventListener('change', sync);
                    sync();
                });

                form?.addEventListener(
                    'submit',
                    function (event) {

                        const uploads = [...form.querySelectorAll('[data-vf-upload]')];
                        if (!uploads.some(input => input.files.length > 0)) {
                            event.preventDefault();
                            const first = uploads[0];
                            if (first) {
                                first.setCustomValidity('Choose at least one rejected document to re-upload.');
                                first.reportValidity();
                                first.addEventListener('change', () => first.setCustomValidity(''), {once: true});
                            }
                            return;
                        }

                        if (button) {

                            button.disabled = true;

                            button.textContent =
                                'Submitting Documents...';

                        }

                    }
                );

            }
        );

    </script>

</body>

</html>
