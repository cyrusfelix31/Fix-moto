<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Shop Verification - FIXMOTO</title>

    <link rel="stylesheet"
          href="{{ asset('css/shop-verification.css') }}">

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js">
    </script>

</head>

<body class="verification-page">

@php
    $rejected =
        $profile->verification_status === 'rejected';

    $needsDocuments = $profile->documents->isEmpty();
@endphp

<main class="verification-status-card">

    <div class="verification-brand">
        <span>F</span>
        <strong>FIXMOTO</strong>
    </div>

    <div class="verification-status-icon
        {{ $rejected ? 'rejected' : 'pending' }}">

        <i data-lucide="{{ $rejected
            ? 'x-circle'
            : 'clock' }}">
        </i>

    </div>

    <h1>

        {{ $rejected
            ? 'Application Rejected'
            : 'Pending for Verification' }}

    </h1>

    <p class="verification-description">

        @if($rejected)

            Your application requires corrections.
            Review the administrator's feedback
            and resubmit your documents.

        @elseif($needsDocuments)

            Please upload your required documents
            so the administrator can verify your shop.

        @else

            Your registration has been submitted successfully.
            Our administrator will review your information
            and business documents.

        @endif

    </p>

    @if(session('success'))
        <div class="verification-alert success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="verification-alert error">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif


    <div class="verification-summary">

        <div class="verification-summary-row">

            <span>Shop name</span>

            <strong>{{ $profile->shop_name }}</strong>

        </div>

        <div class="verification-summary-row">

            <span>Application status</span>

            <span class="verification-badge
                {{ $profile->verification_status }}">

                {{ ucfirst($profile->verification_status) }}

            </span>

        </div>

        <div class="verification-summary-row">

            <span>Documents</span>

            <strong>

                {{ $needsDocuments
                    ? 'Not yet submitted'
                    : 'Submitted' }}

            </strong>

        </div>

        <div class="verification-summary-row">

            <span>Account access</span>

            <strong>Restricted</strong>

        </div>

    </div>


    {{-- DOCUMENT STATUSES --}}

    <div class="verification-document-list">

        @foreach($profile->documents as $document)

            <div class="verification-summary-row">

                <div>

                    <strong>{{ $document->label }}</strong>

                    @if($document->rejection_reason)

                        <p class="rejection-feedback">

                            {{ $document->rejection_reason }}

                        </p>

                    @endif

                </div>

                <span class="verification-badge
                    {{ $document->status }}">

                    {{ ucfirst($document->status) }}

                </span>

            </div>

        @endforeach

    </div>


    @if($rejected)

        <div class="verification-alert error">

            <strong>Reason for rejection</strong>

            <p>

                {{ $latestVerification?->rejection_reason
                    ?? 'Please review the submitted documents.' }}

            </p>

        </div>

    @endif


    {{-- RESUBMIT DOCUMENTS --}}

    @if($rejected || $needsDocuments)

        <form method="POST"
              class="verification-resubmit"
              action="{{ route('mechanic.verification.resubmit') }}"
              enctype="multipart/form-data">

            @csrf

            <h2>Submit Business Documents</h2>

            @foreach(\App\Models\MechanicDocument::TYPES as $type => $label)

                <div class="resubmit-document">

                    <label for="file-{{ $type }}">

                        {{ $label }}

                    </label>

                    <input id="file-{{ $type }}"
                           type="file"
                           name="{{ $type }}"
                           accept=".pdf,.jpg,.jpeg,.png"
                           required>

                    @if(in_array($type, [
                        'valid_id',
                        'business_permit',
                        'mayors_permit'
                    ]))

                        <label>

                            Expiration date

                            @if($type === 'valid_id')
                                (if applicable)
                            @endif

                            <input type="date"
                                   name="{{ $type }}_expiration"
                                   min="{{ today()->toDateString() }}"
                                   value="{{ old($type.'_expiration') }}"
                                   @required($type !== 'valid_id')>

                        </label>

                    @endif

                </div>

            @endforeach

            <button type="submit"
                    class="verification-button primary">

                {{ $rejected
                    ? 'Resubmit Documents'
                    : 'Submit Documents' }}

            </button>

        </form>

    @else

        <a href="{{ route('mechanic.verification.status') }}"
           class="verification-button primary">

            Check Verification Status

        </a>

    @endif

    <form method="POST"
          action="{{ route('logout') }}">

        @csrf

        <button class="verification-logout"
                type="submit">

            Log out

        </button>

    </form>

</main>

<script>
    window.lucide?.createIcons();
</script>

</body>

</html>
