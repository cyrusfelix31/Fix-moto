<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DispatchAttempt;
use App\Models\MechanicProfile;
use App\Models\MechanicShopMember;
use App\Models\ServiceRequest;
use App\Services\BarangayLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MechanicController extends Controller
{
    private const ACTIVE_SERVICE_STATUSES = [
        'accepted',
        'on_the_way',
        'arrived',
        'diagnosing',
        'repairing',
    ];

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        $profile = $user->mechanicProfile()
            ->withCount('activeMembers')
            ->firstOrFail();

        return view('mechanic.dashboard', [
            'profile' => $profile,

            'offerCount' => DispatchAttempt::where('mechanic_id', $user->id)
                ->where('status', 'offered')
                ->where('expires_at', '>', now())
                ->whereHas('serviceRequest', fn ($query) =>
                    $query->where('dispatch_status', 'offered')
                )
                ->count(),

            'activeJobCount' => $user->assignedRequests()
                ->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES)
                ->count(),

            'teamMemberCount' => $profile->members()->count(),
        ]);
    }

    public function shopRequests(Request $request): View
    {
        $user = $request->user();

        $profile = $user->mechanicProfile()
            ->withCount('activeMembers')
            ->firstOrFail();

        return view('mechanic.shop-requests', [
            'profile' => $profile,

            'incomingOffers' => DispatchAttempt::query()
                ->where('mechanic_id', $user->id)
                ->where('status', 'offered')
                ->where('expires_at', '>', now())
                ->whereHas('serviceRequest', fn ($query) =>
                    $query->where('dispatch_status', 'offered')
                )
                ->with([
                    'serviceRequest.rider',
                    'serviceRequest.vehicle',
                ])
                ->latest()
                ->get(),

            'activeJobs' => $user->assignedRequests()
                ->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES)
                ->with([
                    'rider',
                    'vehicle',
                    'assignedMechanicMember',
                ])
                ->latest()
                ->get(),

            'availableMembers' => $profile->activeMembers()
                ->whereDoesntHave('serviceRequests', fn ($query) =>
                    $query->whereIn(
                        'service_status',
                        self::ACTIVE_SERVICE_STATUSES
                    )
                )
                ->orderBy('name')
                ->get(),

            'completedJobs' => $user->assignedRequests()
                ->where('service_status', 'completed')
                ->with([
                    'rider',
                    'vehicle',
                    'assignedMechanicMember',
                    'statusHistory',
                ])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function team(Request $request): View
    {
        $profile = $request->user()
            ->mechanicProfile()
            ->withCount('activeMembers')
            ->firstOrFail();

        return view('mechanic.team', [
            'profile' => $profile,

            'teamMembers' => $profile->members()
                ->withCount([
                    'serviceRequests as active_request_count' =>
                        fn ($query) => $query->whereIn(
                            'service_status',
                            self::ACTIVE_SERVICE_STATUSES
                        ),
                ])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function shopProfile(
        Request $request,
        BarangayLocator $locator
    ): View {
        $profile = $request->user()
            ->mechanicProfile()
            ->withCount('activeMembers')
            ->firstOrFail();

        return view('mechanic.shop-profile', [
            'profile' => $profile,
            'cityBoundary' => $locator->cityFeature(),
        ]);
    }

    public function toggleAvailability(
        Request $request
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        abort_if(
            !$profile->is_verified,
            403,
            'Your account must be verified first.'
        );

        if (
            !$profile->is_online &&
            !$profile->activeMembers()->exists()
        ) {
            return back()->withErrors([
                'team' => 'Add at least one active mechanic first.',
            ]);
        }

        DB::transaction(function () use ($profile, $request): void {
            $profile->update([
                'is_online' => !$profile->is_online,
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic.availability_updated',
                details: [
                    'is_online' => $profile->is_online,
                ]
            );
        });

        return back()->with(
            'success',
            'Availability updated.'
        );
    }

    public function storeTeamMember(
        Request $request
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $added = DB::transaction(function () use (
            $data,
            $profile,
            $request
        ): bool {
            MechanicProfile::whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($profile->members()->count() >= 50) {
                return false;
            }

            $member = $profile->members()->create($data);

            AuditLog::record(
                $request->user(),
                'mechanic_shop.member_added',
                details: [
                    'mechanic_shop_member_id' => $member->id,
                    'name' => $member->name,
                ]
            );

            return true;
        });

        if (!$added) {
            return back()->withErrors([
                'team' => 'A shop can register up to 50 mechanics.',
            ]);
        }

        return back()->with(
            'success',
            'Mechanic added to your shop.'
        );
    }

    public function updateShopProfile(
        Request $request,
        BarangayLocator $locator
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'service_address' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $locationMatch = $locator->locate(
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if (!$locationMatch['within_city']) {
            throw ValidationException::withMessages([
                'latitude' => 'Choose a shop location within Calapan City boundaries.',
            ]);
        }

        DB::transaction(function () use (
            $data,
            $profile,
            $request,
            $locationMatch
        ): void {
            $profile->update([
                ...$data,
                'barangay' => $locationMatch['barangay_name'],
                'barangay_id' => $locationMatch['barangay_id'],
                'barangay_match_status' => $locationMatch['status'],
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic_shop.profile_updated',
                details: [
                    'latitude' => $profile->latitude,
                    'longitude' => $profile->longitude,
                ]
            );
        });

        return back()->with(
            'success',
            'Shop profile and dispatch location updated.'
        );
    }

    public function toggleTeamMember(
        Request $request,
        MechanicShopMember $member
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        abort_if(
            $member->mechanic_profile_id !== $profile->id,
            404
        );

        $updated = DB::transaction(function () use (
            $member,
            $profile,
            $request
        ): bool {
            $member = MechanicShopMember::whereKey($member->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $member->is_active &&
                $member->serviceRequests()
                    ->whereIn(
                        'service_status',
                        self::ACTIVE_SERVICE_STATUSES
                    )
                    ->exists()
            ) {
                return false;
            }

            $member->update([
                'is_active' => !$member->is_active,
            ]);

            if (
                !$member->is_active &&
                !$profile->activeMembers()->exists()
            ) {
                $profile->update([
                    'is_online' => false,
                ]);
            }

            AuditLog::record(
                $request->user(),
                'mechanic_shop.member_status_updated',
                details: [
                    'mechanic_shop_member_id' => $member->id,
                    'is_active' => $member->is_active,
                ]
            );

            return true;
        });

        if (!$updated) {
            return back()->withErrors([
                'team' => 'A mechanic with an active request cannot be deactivated.',
            ]);
        }

        return back()->with(
            'success',
            'Mechanic roster updated.'
        );
    }

    public function acceptOffer(
        Request $request,
        DispatchAttempt $attempt
    ): RedirectResponse {
        abort_if(
            $attempt->mechanic_id !== $request->user()->id,
            403
        );

        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        abort_if(
            !$profile->is_verified ||
            !$profile->is_online ||
            !$profile->activeMembers()->exists(),
            409,
            'Your shop must be verified, online and have active mechanics.'
        );

        $accepted = DB::transaction(function () use (
            $attempt,
            $request
        ): bool {
            $attempt = DispatchAttempt::whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $attempt->mechanic_id !== $request->user()->id,
                403
            );

            if (
                $attempt->status !== 'offered' ||
                $attempt->expires_at->lte(now())
            ) {
                if ($attempt->status === 'offered') {
                    $attempt->update(['status' => 'expired']);
                }

                return false;
            }

            $serviceRequest = ServiceRequest::whereKey(
                $attempt->service_request_id
            )->lockForUpdate()->firstOrFail();

            abort_if(
                $serviceRequest->dispatch_status !== 'offered',
                409,
                'This request is no longer available.'
            );

            $attempt->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $serviceRequest->dispatchAttempts()
                ->where('status', 'offered')
                ->where('id', '!=', $attempt->id)
                ->update(['status' => 'cancelled']);

            $serviceRequest->update([
                'mechanic_id' => $request->user()->id,
                'dispatch_status' => 'assigned',
                'service_status' => 'accepted',
            ]);

            $serviceRequest->statusHistory()->create([
                'status' => 'accepted',
                'changed_by' => $request->user()->id,
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic.offer_accepted',
                details: [
                    'service_request_reference' =>
                        $serviceRequest->reference,
                ]
            );

            return true;
        });

        if (!$accepted) {
            return back()->withErrors([
                'offer' => 'This dispatch offer has expired.',
            ]);
        }

        return back()->with(
            'success',
            'Request accepted.'
        );
    }

    public function declineOffer(
        Request $request,
        DispatchAttempt $attempt
    ): RedirectResponse {
        abort_if(
            $attempt->mechanic_id !== $request->user()->id,
            403
        );

        $declined = DB::transaction(function () use (
            $attempt,
            $request
        ): bool {
            $attempt = DispatchAttempt::whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $attempt->mechanic_id !== $request->user()->id,
                403
            );

            if (
                $attempt->status !== 'offered' ||
                $attempt->expires_at->lte(now())
            ) {
                if ($attempt->status === 'offered') {
                    $attempt->update(['status' => 'expired']);
                }

                return false;
            }

            $serviceRequest = ServiceRequest::whereKey(
                $attempt->service_request_id
            )->lockForUpdate()->firstOrFail();

            if ($serviceRequest->dispatch_status !== 'offered') {
                return false;
            }

            $attempt->update([
                'status' => 'declined',
                'responded_at' => now(),
            ]);

            $this->offerNextMechanic(
                $attempt->service_request_id
            );

            AuditLog::record(
                $request->user(),
                'mechanic.offer_declined',
                details: [
                    'service_request_id' =>
                        $attempt->service_request_id,
                ]
            );

            return true;
        });

        if (!$declined) {
            return back()->withErrors([
                'offer' => 'This dispatch offer is no longer available.',
            ]);
        }

        return back()->with(
            'success',
            'Request declined.'
        );
    }

    public function assignMechanic(
        Request $request,
        ServiceRequest $serviceRequest
    ): RedirectResponse {
        $shopUser = $request->user();

        abort_if(
            $serviceRequest->mechanic_id !== $shopUser->id,
            404
        );

        $data = $request->validate([
            'mechanic_shop_member_id' => [
                'required',
                'integer',
                'exists:mechanic_shop_members,id',
            ],
        ]);

        $profile = $shopUser->mechanicProfile()
            ->firstOrFail();

        $assignedMemberName = DB::transaction(function () use (
            $data,
            $profile,
            $request,
            $serviceRequest,
            $shopUser
        ): string {
            $serviceRequest = ServiceRequest::whereKey(
                $serviceRequest->id
            )->lockForUpdate()->firstOrFail();

            abort_if(
                $serviceRequest->mechanic_id !== $shopUser->id ||
                $serviceRequest->dispatch_status !== 'assigned' ||
                $serviceRequest->service_status !== 'accepted',
                409,
                'Assign a mechanic before marking On the Way.'
            );

            $member = $profile->activeMembers()
                ->whereKey($data['mechanic_shop_member_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $busy = $member->serviceRequests()
                ->whereIn(
                    'service_status',
                    self::ACTIVE_SERVICE_STATUSES
                )
                ->where('service_requests.id', '!=', $serviceRequest->id)
                ->exists();

            abort_if(
                $busy,
                422,
                'This mechanic already has an active request.'
            );

            $serviceRequest->update([
                'mechanic_shop_member_id' => $member->id,
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic_shop.request_assigned',
                details: [
                    'service_request_reference' =>
                        $serviceRequest->reference,
                    'mechanic_shop_member_id' => $member->id,
                    'mechanic_name' => $member->name,
                ]
            );

            return $member->name;
        });

        return back()->with(
            'success',
            'Request assigned to '.$assignedMemberName.'.'
        );
    }

    // Mechanic can only mark On the Way
    public function updateStatus(
        Request $request,
        ServiceRequest $serviceRequest
    ): RedirectResponse {
        abort_if(
            $serviceRequest->mechanic_id !== $request->user()->id,
            403
        );

        $data = $request->validate([
            'status' => ['required', 'in:on_the_way'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use (
            $serviceRequest,
            $data,
            $request
        ): void {
            $serviceRequest = ServiceRequest::whereKey(
                $serviceRequest->id
            )->lockForUpdate()->firstOrFail();

            abort_if(
                $serviceRequest->mechanic_id !== $request->user()->id,
                403
            );

            abort_if(
                $serviceRequest->dispatch_status !== 'assigned',
                409,
                'This request is not assigned.'
            );

            abort_if(
                !$serviceRequest->mechanic_shop_member_id,
                422,
                'Assign a mechanic first.'
            );

            abort_if(
                $serviceRequest->service_status !== 'accepted',
                422,
                'Only the rider can confirm the remaining service progress.'
            );

            $serviceRequest->update([
                'service_status' => 'on_the_way',
            ]);

            $serviceRequest->statusHistory()->create([
                'status' => 'on_the_way',
                'note' => $data['note'] ?? null,
                'changed_by' => $request->user()->id,
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic.job_status_updated',
                details: [
                    'service_request_reference' =>
                        $serviceRequest->reference,
                    'status' => 'on_the_way',
                ]
            );
        });

        return back()->with(
            'success',
            'Mechanic is on the way. Waiting for rider confirmation.'
        );
    }

    private function offerNextMechanic(
        int $serviceRequestId
    ): void {
        $serviceRequest = ServiceRequest::findOrFail(
            $serviceRequestId
        );

        $excluded = $serviceRequest->dispatchAttempts()
            ->pluck('mechanic_id');

        $radius = (float) config(
            'fixmoto.dispatch_radius',
            25
        );

        $eligibleShop = MechanicProfile::query()
            ->where('is_verified', true)
            ->where('is_online', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('user_id', $excluded)
            ->whereHas('activeMembers')
            ->get()
            ->filter(fn (MechanicProfile $profile): bool =>
                $profile->verification_status === 'approved'
                && \App\Services\ShopServiceCatalog::supports(
                    $profile->services ?? [],
                    $serviceRequest->services
                        ?: [$serviceRequest->issue_category]
                )
            )
            ->map(fn (MechanicProfile $profile): array => [
                'profile' => $profile,
                'distance_km' => $profile->distanceTo(
                    (float) $serviceRequest->latitude,
                    (float) $serviceRequest->longitude
                ),
            ])
            ->filter(fn (array $shop): bool =>
                $shop['distance_km'] <= $radius
            )
            ->sortBy('distance_km')
            ->first();

        if ($eligibleShop) {
            $profile = $eligibleShop['profile'];

            DispatchAttempt::create([
                'service_request_id' => $serviceRequest->id,
                'mechanic_id' => $profile->user_id,
                'status' => 'offered',

                'expires_at' => now()->addSeconds(
                    (int) config('fixmoto.response_timeout', 1200)
                ),

                'distance_km' => $eligibleShop['distance_km'],

                'attempt_number' =>
                    $serviceRequest->dispatchAttempts()->count() + 1,
            ]);

            $serviceRequest->update([
                'dispatch_status' => 'offered',
            ]);
        } else {
            $serviceRequest->update([
                'dispatch_status' => 'no_mechanics',
            ]);
        }
    }
}
