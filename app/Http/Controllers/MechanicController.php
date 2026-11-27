<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DispatchAttempt;
use App\Models\MechanicProfile;
use App\Models\MechanicShopMember;
use App\Models\MechanicShopService;
use App\Models\MechanicLocation;
use App\Models\ServiceRequest;
use App\Notifications\MechanicAvailabilityNotice;
use App\Services\BarangayLocator;
use App\Services\ShopServiceMatcher;
use App\Services\GpsDistance;
use Carbon\CarbonImmutable;
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
                ->whereHas('serviceRequest', fn ($query) => $query->where('dispatch_status', 'offered')
                )
                ->count(),

            'activeJobCount' => $user->assignedRequests()
                ->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES)
                ->count(),

            'teamMemberCount' => $profile->members()->count(),
            'availableMemberCount' => $profile->availableMechanicCount(),
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
                ->whereHas('serviceRequest', fn ($query) => $query->where('dispatch_status', 'offered')
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

            'availableMembers' => $profile->availableMembers()
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
                    'serviceRequests as active_request_count' => fn ($query) => $query->whereIn(
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
            'owner' => $request->user(),
            'teamMembers' => $profile->members()
                ->withCount(['serviceRequests as active_request_count' => fn ($query) => $query->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES),
                ])->orderBy('name')->get(),
            'shopServices' => $profile->shopServices()->orderBy('name')->get(),
            'cityBoundary' => $locator->cityFeature(),
        ]);
    }

    public function toggleAvailability(
        Request $request
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        $updated = DB::transaction(function () use ($profile, $request): ?bool {
            $lockedProfile = MechanicProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            abort_if(!$lockedProfile->is_verified || $lockedProfile->verification_status !== 'fully_verified', 403, 'Your account must be verified first.');
            $goingOnline = !$lockedProfile->is_online;
            if ($goingOnline && !$lockedProfile->hasAvailableMechanic()) {
                return false;
            }
            $lockedProfile->update(['is_online' => $goingOnline]);
            AuditLog::record(
                $request->user(),
                'mechanic.availability_updated',
                details: ['is_online' => $lockedProfile->is_online]
            );

            return true;
        });

        if (!$updated) {
            $this->notifyNoActiveMechanics($request);
            return back()->withErrors([
                'team' => 'Your shop needs at least one active, available mechanic before it can go online.',
            ]);
        }

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
            'phone' => ['nullable', 'string', 'max:32'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'years_experience' => ['nullable', 'integer', 'between:0,60'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('shop-profiles/mechanics', 'public');
            unset($data['avatar']);
        }

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

        if (! $added) {
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
            'shop_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'service_address' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('shop-profiles/logos', 'public');
            unset($data['logo']);
        }

        $locationMatch = $locator->locate(
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if (! $locationMatch['within_city'] || $locationMatch['status'] !== 'matched') {
            throw ValidationException::withMessages([
                'latitude' => 'Choose a precise shop location inside a known Calapan City barangay.',
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

    public function updateOwnerProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Owner information updated.');
    }

    public function updateProfilePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'shop_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('owner_photo')) {
            $owner = $request->user();
            $oldPath = $owner->avatar_path;
            $path = $request->file('owner_photo')->store('shop-profiles/owners', 'public');
            if (!$path) {
                return back()->withErrors(['owner_photo' => 'The profile photo could not be saved.']);
            }
            $owner->update(['avatar_path' => $path]);
            if ($oldPath && str_starts_with($oldPath, 'shop-profiles/owners/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
            }
        }

        if ($request->hasFile('shop_logo')) {
            $request->user()->mechanicProfile()->firstOrFail()->update([
                'logo_path' => $request->file('shop_logo')->store('shop-profiles/logos', 'public'),
            ]);
        }

        return back()->with('success', 'Profile photo updated.');
    }

    public function updateTeamMember(Request $request, MechanicShopMember $member): RedirectResponse
    {
        $profile = $request->user()->mechanicProfile()->firstOrFail();
        abort_if($member->mechanic_profile_id !== $profile->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'years_experience' => ['nullable', 'integer', 'between:0,60'],
            'is_active' => ['required', 'boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('shop-profiles/mechanics', 'public');
        }
        unset($data['avatar']);

        $updated = DB::transaction(function () use ($profile, $member, $data): bool {
            MechanicProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $member = $profile->members()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            if (! (bool) $data['is_active'] && $member->serviceRequests()->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES)->exists()) {
                return false;
            }
            $member->update($data);
            if (!$profile->hasAvailableMechanic()) {
                $profile->update(['is_online' => false]);
            }

            return true;
        });

        if (! $updated) {
            return back()->withErrors(['team' => 'This mechanic has an active request and cannot be made unavailable.']);
        }

        if (!$profile->hasAvailableMechanic()) {
            $this->notifyNoActiveMechanics($request);
        }

        return back()->with('success', 'Mechanic information updated.');
    }

    public function removeTeamMember(Request $request, MechanicShopMember $member): RedirectResponse
    {
        $profile = $request->user()->mechanicProfile()->firstOrFail();
        abort_if($member->mechanic_profile_id !== $profile->id, 404);

        $removed = DB::transaction(function () use ($member, $profile): bool {
            MechanicProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $member = $profile->members()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($member->serviceRequests()->whereIn('service_status', self::ACTIVE_SERVICE_STATUSES)->exists()) {
                return false;
            }

            $member->delete();

            if (!$profile->hasAvailableMechanic()) {
                $profile->update(['is_online' => false]);
            }

            return true;
        });

        if (! $removed) {
            return back()->withErrors(['team' => 'A mechanic assigned to an active request cannot be removed.']);
        }

        if (!$profile->hasAvailableMechanic()) {
            $this->notifyNoActiveMechanics($request);
        }

        return back()->with('success', 'Mechanic removed from your shop.');
    }

    public function storeShopService(Request $request): RedirectResponse
    {
        $profile = $request->user()->mechanicProfile()->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'distinct', 'unique:mechanic_shop_services,name,NULL,id,mechanic_profile_id,'.$profile->id],
        ]);
        $profile->shopServices()->create($data);

        return back()->with('success', 'Service added to your shop.');
    }

    public function updateShopService(Request $request, MechanicShopService $service): RedirectResponse
    {
        $profile = $request->user()->mechanicProfile()->firstOrFail();
        abort_if($service->mechanic_profile_id !== $profile->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:mechanic_shop_services,name,'.$service->id.',id,mechanic_profile_id,'.$profile->id],
        ]);
        $service->update($data);

        return back()->with('success', 'Service name updated.');
    }

    public function toggleShopService(Request $request, MechanicShopService $service): RedirectResponse
    {
        $profile = $request->user()->mechanicProfile()->firstOrFail();
        abort_if($service->mechanic_profile_id !== $profile->id, 404);
        $service->update(['is_enabled' => ! $service->is_enabled]);

        return back()->with('success', 'Service availability updated.');
    }

    public function removeShopService(Request $request, MechanicShopService $service): RedirectResponse
    {
        $profile = $request->user()->mechanicProfile()->firstOrFail();
        abort_if($service->mechanic_profile_id !== $profile->id, 404);
        $service->delete();

        return back()->with('success', 'Service removed from your shop.');
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
            MechanicProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
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
                'is_active' => ! $member->is_active,
            ]);

            if (!$profile->hasAvailableMechanic()) {
                $profile->update(['is_online' => false]);
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

        if (! $updated) {
            return back()->withErrors([
                'team' => 'A mechanic with an active request cannot be deactivated.',
            ]);
        }

        if (!$profile->hasAvailableMechanic()) {
            $this->notifyNoActiveMechanics($request);
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

        $result = DB::transaction(function () use (
            $attempt,
            $request,
            $profile
        ): string {
            $lockedProfile = MechanicProfile::query()
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (!$lockedProfile->is_verified || $lockedProfile->verification_status !== 'fully_verified' || !$lockedProfile->is_online) {
                return 'unavailable';
            }
            if (!$lockedProfile->hasAvailableMechanic()) {
                return 'unavailable';
            }

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

                return 'expired';
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

            if (!$lockedProfile->hasAvailableMechanic()) {
                $lockedProfile->update(['is_online' => false]);
            }

            AuditLog::record(
                $request->user(),
                'mechanic.offer_accepted',
                details: [
                    'service_request_reference' => $serviceRequest->reference,
                ]
            );

            return 'accepted';
        });

        if ($result === 'unavailable') {
            if (!$profile->hasAvailableMechanic()) {
                $this->notifyNoActiveMechanics($request);
                return back()->withErrors(['offer' => 'No mechanics are currently available. Add or activate a mechanic before accepting requests.']);
            }
            return back()->withErrors(['offer' => 'Your shop must be online and verified before accepting requests.']);
        }

        if ($result !== 'accepted') {
            return back()->withErrors([
                'offer' => 'This dispatch offer has expired.',
            ]);
        }

        if (!$profile->hasAvailableMechanic()) {
            $this->notifyNoActiveMechanics($request);
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
                    'service_request_id' => $attempt->service_request_id,
                ]
            );

            return true;
        });

        if (! $declined) {
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
            $lockedProfile = MechanicProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
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
                    'service_request_reference' => $serviceRequest->reference,
                    'mechanic_shop_member_id' => $member->id,
                    'mechanic_name' => $member->name,
                ]
            );

            return $member->name;
        });

        if (!$profile->hasAvailableMechanic()) {
            $profile->update(['is_online' => false]);
            $this->notifyNoActiveMechanics($request);
        }

        return back()->with(
            'success',
            'Request assigned to '.$assignedMemberName.'.'
        );
    }

    // The mechanic marks On the Way and confirms arrival with fresh GPS.
    public function updateStatus(
        Request $request,
        ServiceRequest $serviceRequest
    ): RedirectResponse {
        abort_if(
            $serviceRequest->mechanic_id !== $request->user()->id,
            403
        );

        $data = $request->validate([
            'status' => ['required', 'in:on_the_way,arrived'],
            'note' => ['nullable', 'string', 'max:1000'],
            'gps_latitude' => ['required_if:status,arrived', 'numeric', 'between:-90,90'],
            'gps_longitude' => ['required_if:status,arrived', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['required_if:status,arrived', 'numeric', 'min:0', 'max:10000'],
            'gps_captured_at' => ['required_if:status,arrived', 'integer', 'min:1'],
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
                ! $serviceRequest->mechanic_shop_member_id,
                422,
                'Assign a mechanic first.'
            );

            if ($data['status'] === 'on_the_way') {
                abort_if(
                    $serviceRequest->service_status !== 'accepted',
                    422,
                    'The mechanic can only mark On the Way after accepting the request.'
                );
            } else {
                abort_if(
                    $serviceRequest->service_status !== 'on_the_way',
                    422,
                    'Confirm arrival only after marking On the Way.'
                );

                $capturedAt = CarbonImmutable::createFromTimestampMs((int) $data['gps_captured_at']);
                $ageMilliseconds = now()->getTimestampMs() - $capturedAt->getTimestampMs();
                $maxAge = max(10, (int) config('fixmoto.gps_max_age_seconds', 120)) * 1000;
                abort_if(
                    $ageMilliseconds < -10_000 || $ageMilliseconds > $maxAge,
                    422,
                    'GPS reading is stale or has an invalid timestamp. Try again.'
                );

                $maxAccuracy = (float) config('fixmoto.gps_max_accuracy_meters', 35);
                abort_if(
                    (float) $data['gps_accuracy'] > $maxAccuracy,
                    422,
                    'GPS reading is not accurate enough. Move to an open area and try again.'
                );

                $distance = GpsDistance::meters(
                    (float) $data['gps_latitude'],
                    (float) $data['gps_longitude'],
                    (float) $serviceRequest->latitude,
                    (float) $serviceRequest->longitude,
                );
                $radius = (float) config('fixmoto.gps_arrival_radius_meters', 50);
                abort_if(
                    $distance > $radius,
                    422,
                    'You are '.(int) round($distance).' m from the rider. Arrival can be confirmed within '.(int) round($radius).' m.'
                );

                MechanicLocation::query()->create([
                    'mechanic_id' => $request->user()->id,
                    'service_request_id' => $serviceRequest->id,
                    'latitude' => $data['gps_latitude'],
                    'longitude' => $data['gps_longitude'],
                    'accuracy_meters' => $data['gps_accuracy'],
                    'reported_at' => $capturedAt,
                ]);
            }

            $serviceRequest->update(['service_status' => $data['status']]);

            $serviceRequest->statusHistory()->create([
                'status' => $data['status'],
                'note' => $data['note'] ?? ($data['status'] === 'arrived' ? 'GPS arrival confirmed.' : null),
                'changed_by' => $request->user()->id,
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic.job_status_updated',
                details: [
                    'service_request_reference' => $serviceRequest->reference,
                    'status' => $data['status'],
                ]
            );
        });

        return back()->with('success', $data['status'] === 'arrived'
            ? 'Your arrival was confirmed with GPS.'
            : 'Mechanic is on the way.');
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
            ->whereHas('availableMembers')
            ->get()
            ->filter(fn (MechanicProfile $profile): bool =>
                $profile->verification_status === 'fully_verified'
                && $profile->hasAvailableMechanic()
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
            ->filter(fn (array $shop): bool => $shop['distance_km'] <= $radius
                && ShopServiceMatcher::isQualified(
                    $shop['profile'], $serviceRequest->services
                        ?? [$serviceRequest->issue_category]
                )
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

                'attempt_number' => $serviceRequest->dispatchAttempts()->count() + 1,
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
    private function notifyNoActiveMechanics(Request $request): void
    {
        $request->user()->notify(new MechanicAvailabilityNotice(
            'Your shop has no available mechanics right now. Add or activate a mechanic, or wait for an assigned mechanic to finish their current request, to receive and accept more service requests.'
        ));
    }
}
