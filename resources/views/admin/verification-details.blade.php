@extends('admin.layouts.admin')
@section('title', 'Review Shop Documents - FIXMOTO')
@section('page_title', 'Review Shop Application')

@section('content')

<link rel="stylesheet"
      href="{{ asset('css/shop-verification.css') }}">

<a href="{{ route('admin.verification') }}"
   class="verification-back">

    <i data-lucide="arrow-left"></i>

    Back to Verification

</a>

<section class="shop-verification-panel">

    <div class="verification-review-heading">

        <div>

            <h2>{{ $profile->shop_name }}</h2>

            <p>

                SHOP-{{ str_pad(
                    $profile->id,
                    4,
                    '0',
                    STR_PAD_LEFT
                ) }}

            </p>

        </div>

        <span class="verification-badge
            {{ $profile->verification_status }}">

            {{ ucfirst($profile->verification_status) }}

        </span>

    </div>

    @if($errors->any())

        <div class="verification-alert error">

            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach

        </div>

    @endif


    {{-- OWNER INFORMATION --}}

    <div class="verification-info-grid">

        <div class="verification-info-card">

            <h3>
                <i data-lucide="user-round"></i>
                Owner's Information
            </h3>

            <dl>

                <dt>Owner's name</dt>
                <dd>{{ $user->name }}</dd>

                <dt>Contact number</dt>
                <dd>{{ $user->phone ?? 'Not provided' }}</dd>

                <dt>Email address</dt>
                <dd>{{ $user->email }}</dd>

            </dl>

        </div>


        {{-- SHOP INFORMATION --}}

        <div class="verification-info-card">

            <h3>
                <i data-lucide="store"></i>
                Shop Information
            </h3>

            <dl>

                <dt>Shop name</dt>
                <dd>{{ $profile->shop_name }}</dd>

                <dt>Shop address</dt>
                <dd>{{ $profile->service_address }}</dd>

                <dt>Shop contact number</dt>
                <dd>{{ $profile->shop_phone ?? 'Not provided' }}</dd>

                <dt>Registered mechanics</dt>
                <dd>{{ $profile->members->count() }}</dd>

            </dl>

        </div>

    </div>


    {{-- BUSINESS DOCUMENTS --}}

    <div class="verification-document-heading">

        <h3>
            <i data-lucide="file-check"></i>
            Document Verification
        </h3>

        <p>Review each uploaded document before approving the shop.</p>

    </div>

    @foreach(\App\Models\MechanicDocument::TYPES as $type => $label)

        @php

            $document = $profile->documents
                ->firstWhere('document_type', $type);

        @endphp

        <article class="verification-document-card">

            <div class="verification-document-header">

                <span class="document-file-icon">

                    <i data-lucide="file-text"></i>

                </span>

                <div class="verification-document-info">

                    <h4>{{ $label }}</h4>

                    @if($document)

                        <p>{{ $document->original_filename }}</p>

                        <small>

                            Expiration date:

                            {{ $document->expiration_date
                                ?->format('M d, Y')
                                ?? 'Not applicable' }}

                        </small>

                    @else

                        <p>No document uploaded.</p>

                    @endif

                </div>

                <span class="verification-badge
                    {{ $document?->status ?? 'pending' }}">

                    {{ $document
                        ? ucfirst($document->status)
                        : 'Missing' }}

                </span>

            </div>

            @if($document)

                @if($document->isExpired())

                    <div class="verification-alert error">

                        This document has expired.

                    </div>

                @endif

                @if($document->rejection_reason)

                    <p class="rejection-feedback">

                        Reason: {{ $document->rejection_reason }}

                    </p>

                @endif

                <div class="verification-document-actions">

                    <a href="{{ route(
                        'admin.documents.view',
                        $document
                    ) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="verification-button outline">

                        <i data-lucide="eye"></i>

                        View File

                    </a>


                    @if($profile->verification_status === 'pending')

                        <div class="document-decision-buttons">

                            <button type="button"
                                    class="document-approve-button"
                                    title="Approve document"
                                    aria-label="Approve {{ $label }}"
                                    data-review-url="{{ route(
                                        'admin.documents.review',
                                        $document
                                    ) }}"
                                    data-decision="approve"
                                    data-review-label="{{ $label }}"
                                    @disabled($document->isExpired())>

                                <i data-lucide="check"></i>

                            </button>

                            <button type="button"
                                    class="document-reject-button"
                                    title="Reject document"
                                    aria-label="Reject {{ $label }}"
                                    data-review-url="{{ route(
                                        'admin.documents.review',
                                        $document
                                    ) }}"
                                    data-decision="reject"
                                    data-review-label="{{ $label }}">

                                <i data-lucide="x"></i>

                            </button>

                        </div>

                    @endif

                </div>

            @endif

        </article>

    @endforeach


    {{-- FINAL APPLICATION DECISION --}}

    @if($profile->verification_status === 'pending')

        <div class="verification-final-actions">

            <button type="button"
                    class="verification-button danger"
                    data-review-url="{{ route(
                        'admin.mechanics.verify',
                        $user
                    ) }}"
                    data-decision="reject"
                    data-review-label="{{ $profile->shop_name }}">

                Reject Application

            </button>

            <button type="button"
                    class="verification-button primary"
                    data-review-url="{{ route(
                        'admin.mechanics.verify',
                        $user
                    ) }}"
                    data-decision="approve"
                    data-review-label="{{ $profile->shop_name }}"
                    @disabled(!$profile->allDocumentsApproved())>

                Approve Application

            </button>

        </div>

        @unless($profile->allDocumentsApproved())

            <p class="verification-hint">

                All required documents must be approved
                and valid before approving this application.

            </p>

        @endunless

    @endif

</section>

@include('admin.verification-modal')

@endsection
