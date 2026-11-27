@extends('admin.layouts.admin')
@section('title', 'Shop Verification - FIXMOTO')
@section('page_title', 'Mechanic Shop Verification')

@section('content')

<link rel="stylesheet"
      href="{{ asset('css/shop-verification.css') }}">

<section class="shop-verification-panel">

    <div class="verification-heading">

        <h2>Mechanic Shop Verification</h2>

        <p>Review and manage mechanic shop applications.</p>

    </div>

    @if($errors->any())

        <div class="verification-alert error">

            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach

        </div>

    @endif


    {{-- SUMMARY CARDS --}}

    <div class="verification-stat-grid">

        <div class="verification-stat">

            <div class="stat-icon pending">
                <i data-lucide="clock"></i>
            </div>

            <span>In review</span>

            <strong>{{ $counts['pending'] }}</strong>

        </div>

        <div class="verification-stat">

            <div class="stat-icon approved">
                <i data-lucide="check-circle"></i>
            </div>

            <span>Approved</span>

            <strong>{{ $counts['approved'] }}</strong>

        </div>

        <div class="verification-stat">

            <div class="stat-icon rejected">
                <i data-lucide="x-circle"></i>
            </div>

            <span>Rejected</span>

            <strong>{{ $counts['rejected'] }}</strong>

        </div>

    </div>


    {{-- VERIFICATION TABLE --}}

    <div class="verification-table-wrapper">

        <table class="verification-table">

            <thead>

                <tr>
                    <th>Shop Name</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

                @forelse($shops as $shop)

                    @php
                        $profile = $shop->mechanicProfile;
                        $status = $profile->verification_status;
                        $statusLabels = [
                            'pending_verification' => 'Pending Verification',
                            'under_review' => 'Under Review',
                            'partially_verified' => 'Partially Verified — Resubmission Required',
                            'fully_verified' => 'Fully Verified',
                            'rejected' => 'Rejected',
                        ];
                    @endphp

                    <tr>

                        <td>

                            <strong>

                                {{ $profile->shop_name }}

                            </strong>

                            <small>

                                SHOP-{{ str_pad(
                                    $profile->id,
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}

                            </small>

                        </td>

                        <td>

                            <span class="verification-badge {{ in_array($status, ['fully_verified', 'approved'], true) ? 'approved' : (in_array($status, ['rejected'], true) ? 'rejected' : 'pending') }}">

                                {{ $statusLabels[$status] ?? ucfirst($status) }}

                            </span>

                        </td>

                        <td>

                            {{ $shop->created_at->format('M d, Y') }}

                        </td>

                        <td>

                            <details class="verification-action-menu">

                                <summary aria-label="Application actions">

                                    <i data-lucide="ellipsis-vertical"></i>

                                </summary>

                                <div class="verification-dropdown">

                                    <a href="{{ route(
                                        'admin.verification.show',
                                        $shop
                                    ) }}">

                                        <i data-lucide="eye"></i>

                                        View

                                    </a>

                                    @if(in_array($status, ['pending_verification', 'under_review', 'partially_verified'], true))

                                        <button type="button"
                                                class="reject-action"
                                                data-review-url="{{ route(
                                                    'admin.mechanics.verify',
                                                    $shop
                                                ) }}"
                                                data-decision="reject"
                                                data-review-label="{{ $profile->shop_name }}">

                                            <i data-lucide="x-circle"></i>

                                            Reject

                                        </button>

                                    @endif

                                </div>

                            </details>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="4"
                            class="verification-empty">

                            No mechanic shop applications found.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>

@include('admin.verification-modal')

@endsection
