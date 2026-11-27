<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\DispatchAttempt;
use App\Models\DispatchSetting;
use App\Models\MechanicProfile;
use App\Models\MechanicVerification;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\SystemStatusIncident;
use App\Models\User;
use App\Services\BarangayLocator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $shops = MechanicProfile::query();
        $shopCount = (clone $shops)->count();
        $verifiedShopCount = (clone $shops)->where('is_verified', true)->count();
        $onlineShopCount = (clone $shops)
            ->where('is_verified', true)
            ->where('is_online', true)
            ->whereHas('availableMembers')
            ->count();
        $barangays = Barangay::query()->select('id')->withCount('mechanicProfiles')->get();
        $coveredBarangays = $barangays->where('mechanic_profiles_count', '>', 0)->count();
        $avgSeconds = DispatchAttempt::whereNotNull('responded_at')
            ->selectRaw('AVG(TIME_TO_SEC(TIMEDIFF(responded_at, created_at))) as avg_seconds')
            ->value('avg_seconds');
        $avgDispatch = $avgSeconds ? gmdate('i\m s\s', (int) round($avgSeconds)) : 'No data';
        $unassignedRequests = ServiceRequest::whereIn('dispatch_status', ['pending', 'offered', 'no_mechanics'])->get();
        $pendingVerifications = User::pendingVerification()->with('mechanicProfile.members')->latest()->get();
        $needsGeographyReview = MechanicProfile::query()->where('barangay_match_status', '!=', 'matched')->count();

        return view('admin.dashboard', [
            'shopCount' => $shopCount,
            'verifiedShopCount' => $verifiedShopCount,
            'onlineShopCount' => $onlineShopCount,
            'coveragePercent' => $shopCount ? round(($onlineShopCount / $shopCount) * 100) : 0,
            'avgDispatch' => $avgDispatch,
            'coveredBarangays' => $coveredBarangays,
            'totalBarangays' => $barangays->count(),
            'barangayCoveragePercent' => $barangays->count() ? round(($coveredBarangays / $barangays->count()) * 100, 1) : 0,
            'needsGeographyReview' => $needsGeographyReview,
            'pendingVerifications' => $pendingVerifications,
            'unassignedRequests' => $unassignedRequests,
            'activeRequests' => ServiceRequest::whereNotIn('dispatch_status', ['completed', 'cancelled', 'no_mechanics'])->count(),
            'completedRequests' => ServiceRequest::where('service_status', 'completed')->count(),
            'cancelledRequests' => ServiceRequest::where('dispatch_status', 'cancelled')->count(),
            'recordedPayments' => Payment::where('status', 'confirmed')->sum('amount'),
            'requests' => ServiceRequest::with(['rider', 'mechanic.mechanicProfile'])->latest()->limit(20)->get(),
        ]);
    }

    public function dashboardGeography(Request $request, BarangayLocator $locator): JsonResponse
    {
        $filters = $request->validate([
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
        ]);
        $selectedBarangayId = isset($filters['barangay_id']) ? (int) $filters['barangay_id'] : null;
        $barangays = Barangay::query()
            ->withCount([
                'mechanicProfiles',
                'mechanicProfiles as online_shops_count' => fn (Builder $query) => $query
                    ->where('is_verified', true)
                    ->where('is_online', true)
                    ->whereHas('availableMembers'),
            ])
            ->orderBy('name')
            ->get();
        $selectedBarangay = $selectedBarangayId
            ? $barangays->firstWhere('id', $selectedBarangayId)
            : null;
        $chartBarangays = $selectedBarangay
            ? $barangays->filter(fn (Barangay $barangay): bool => $barangay->is($selectedBarangay))
            : $barangays;
        $profilesQuery = MechanicProfile::query()
            ->with('mechanic:id,name')
            ->withCount('activeMembers')
            ->when($selectedBarangay, fn (Builder $query) => $query->where('barangay_id', $selectedBarangay->id));
        $profiles = $profilesQuery->get();
        $shopCount = MechanicProfile::query()->count();
        $coveredCount = $barangays->where('mechanic_profiles_count', '>', 0)->count();
        $needsReviewCount = MechanicProfile::query()
            ->where('barangay_match_status', '!=', 'matched')
            ->count();
        $features = $barangays->map(fn (Barangay $barangay): array => [
            'type' => 'Feature',
            'id' => $barangay->id,
            'properties' => [
                'barangay_id' => $barangay->id,
                'psgc_code' => $barangay->psgc_code,
                'name' => $barangay->name,
            ],
            'geometry' => $barangay->boundary_geojson,
        ])->values();
        $requests = ServiceRequest::query()
            ->whereIn('dispatch_status', ['pending', 'offered', 'assigned'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest()
            ->limit(100)
            ->get(['id', 'reference', 'issue_category', 'latitude', 'longitude']);

        if ($selectedBarangay) {
            $requests = $requests->filter(function (ServiceRequest $serviceRequest) use ($barangays, $locator, $selectedBarangay): bool {
                $match = $locator->locate((float) $serviceRequest->latitude, (float) $serviceRequest->longitude, $barangays);

                return $match['barangay_id'] === $selectedBarangay->id;
            })->values();
        }

        return response()->json([
            'summary' => [
                'registered_shops' => $shopCount,
                'covered_barangays' => $coveredCount,
                'total_barangays' => $barangays->count(),
                'coverage_percent' => $barangays->count() ? round(($coveredCount / $barangays->count()) * 100, 1) : 0,
                'online_shops' => $barangays->sum('online_shops_count'),
                'offline_shops' => max(0, $shopCount - $barangays->sum('online_shops_count')),
                'needs_review' => $needsReviewCount,
            ],
            'selected' => $selectedBarangay ? [
                'id' => $selectedBarangay->id,
                'name' => $selectedBarangay->name,
                'shop_count' => $selectedBarangay->mechanic_profiles_count,
                'online_count' => $selectedBarangay->online_shops_count,
                'offline_count' => $selectedBarangay->mechanic_profiles_count - $selectedBarangay->online_shops_count,
            ] : null,
            'barangays' => $barangays->map(fn (Barangay $barangay): array => [
                'id' => $barangay->id,
                'psgc_code' => $barangay->psgc_code,
                'name' => $barangay->name,
                'latitude' => (float) $barangay->latitude,
                'longitude' => (float) $barangay->longitude,
                'shop_count' => $barangay->mechanic_profiles_count,
                'online_count' => $barangay->online_shops_count,
                'offline_count' => $barangay->mechanic_profiles_count - $barangay->online_shops_count,
            ])->values(),
            'chart' => $chartBarangays->map(fn (Barangay $barangay): array => [
                'id' => $barangay->id,
                'name' => $barangay->name,
                'shop_count' => $barangay->mechanic_profiles_count,
                'online_count' => $barangay->online_shops_count,
                'offline_count' => $barangay->mechanic_profiles_count - $barangay->online_shops_count,
            ])->values(),
            'features' => [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
            'city_boundary' => $locator->cityFeature(),
            'shops' => $profiles->map(fn (MechanicProfile $profile): array => [
                'name' => $profile->shop_name ?: $profile->mechanic?->name ?: 'Mechanic shop',
                'latitude' => $profile->latitude ? (float) $profile->latitude : null,
                'longitude' => $profile->longitude ? (float) $profile->longitude : null,
                'barangay_id' => $profile->barangay_id,
                'match_status' => $profile->barangay_match_status,
                'is_online' => $profile->is_verified && $profile->is_online && $profile->active_members_count > 0,
                'active_mechanics' => $profile->active_members_count,
                'service_address' => $profile->service_address,
            ])->values(),
            'requests' => $requests->map(fn (ServiceRequest $serviceRequest): array => [
                'reference' => $serviceRequest->reference,
                'issue' => $serviceRequest->issue_category,
                'latitude' => (float) $serviceRequest->latitude,
                'longitude' => (float) $serviceRequest->longitude,
            ])->values(),
        ]);
    }

    public function verifyMechanic(Request $request, User $user)
    {
        abort_if($user->role !== 'mechanic', 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000'],
        ]);
        $profile = $user->mechanicProfile()->firstOrFail();
        DB::transaction(function () use ($data, $profile, $request, $user): void {
            $profile->update(['is_verified' => $data['decision'] === 'approve', 'is_online' => false]);
            $user->mechanicVerifications()->create(['document_path' => '', 'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'rejection_reason' => $data['reason'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            AuditLog::record($request->user(), 'mechanic.verification_updated', $user, [
                'decision' => $data['decision'],
            ]);
        });

        return back()->with('success', 'Mechanic shop verification updated.');
    }

    public function toggleSuspension(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403);
        DB::transaction(function () use ($request, $user): void {
            $user->is_suspended = ! $user->is_suspended;
            $user->save();

            AuditLog::record($request->user(), 'user.suspension_updated', $user, [
                'is_suspended' => $user->is_suspended,
            ]);
        });

        return back()->with('success', 'Account status updated.');
    }

    public function liveDispatch(): View
    {
        $mechanics = MechanicProfile::with('mechanic')->withCount('activeMembers')->whereNotNull('latitude')->whereNotNull('longitude')->get();
        $requests = ServiceRequest::with(['rider', 'mechanic.mechanicProfile'])->latest()->limit(100)->get();

        return view('admin.live-dispatch', compact('mechanics', 'requests'));
    }

    public function usersMechanics(Request $request): View
    {
        $search = (string) $request->query('search');
        $users = User::query()
            ->whereIn('role', ['rider', 'mechanic'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->with('mechanicProfile.members')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users-mechanics', compact('users', 'search'));
    }

    public function verification(): View
    {
        $pending = User::pendingVerification()->with('mechanicProfile.members')->latest()->get();
        $history = MechanicVerification::with(['mechanic.mechanicProfile', 'reviewedBy'])
            ->whereIn('status', ['approved', 'rejected'])
            ->latest('reviewed_at')
            ->get();

        return view('admin.verification', compact('pending', 'history'));
    }

    public function monitoring(): View
    {
        $requests = ServiceRequest::with(['rider', 'mechanic.mechanicProfile'])->latest()->limit(50)->get();
        $attempts = DispatchAttempt::with(['serviceRequest', 'mechanic.mechanicProfile'])->latest()->limit(50)->get();
        $activeStatus = SystemStatusIncident::whereNull('ended_at')->latest('started_at')->first();
        $currentStatus = $activeStatus?->status ?? 'operational';
        $uptimeSeconds = SystemStatusIncident::where('status', 'operational')->get()->sum(function (SystemStatusIncident $incident): int {
            $end = $incident->ended_at ?? now();

            return (int) $incident->started_at->diffInSeconds($end);
        });
        $downtimeSeconds = SystemStatusIncident::where('status', 'downtime')->get()->sum(function (SystemStatusIncident $incident): int {
            $end = $incident->ended_at ?? now();

            return (int) $incident->started_at->diffInSeconds($end);
        });
        $statusHistory = SystemStatusIncident::with('recorder')->latest('started_at')->limit(20)->get();

        return view('admin.monitoring', compact('requests', 'attempts', 'currentStatus', 'uptimeSeconds', 'downtimeSeconds', 'statusHistory'));
    }

    public function updateSystemStatus(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', 'in:operational,downtime'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $statusChanged = DB::transaction(function () use ($data, $request): bool {
            $activeStatus = SystemStatusIncident::whereNull('ended_at')->latest('started_at')->first();
            if ($activeStatus && $activeStatus->status === $data['status']) {
                return false;
            }

            if ($activeStatus) {
                $activeStatus->update([
                    'ended_at' => now(),
                    'duration_seconds' => (int) $activeStatus->started_at->diffInSeconds(now()),
                ]);
            }

            SystemStatusIncident::create([
                'status' => $data['status'],
                'started_at' => now(),
                'reason' => $data['reason'] ?: ($data['status'] === 'operational' ? 'Service restored' : 'Administrator reported downtime'),
                'recorded_by' => $request->user()->id,
            ]);
            AuditLog::record($request->user(), 'system.status_updated', details: [
                'status' => $data['status'],
            ]);

            return true;
        });

        if (! $statusChanged) {
            return back()->with('success', 'System status is already '.($data['status'] === 'operational' ? 'operational' : 'in downtime'));
        }

        return back()->with('success', 'System status updated.');
    }

    public function auditTrail(): View
    {
        $auditLogs = AuditLog::query()
            ->with(['admin', 'user'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(25);

        return view('admin.audit-trail', compact('auditLogs'));
    }

    public function settings(): View
    {
        $settings = DispatchSetting::orderBy('key')->get();

        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'dispatch_radius' => ['required', 'integer', 'between:1,100'],
            'response_timeout' => ['required', 'integer', 'between:10,600'],
            'dispatch_timeout' => ['required', 'integer', 'between:10,1440'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            foreach ($data as $key => $value) {
                DispatchSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'updated_by' => $request->user()->id]);
            }

            AuditLog::record($request->user(), 'dispatch.settings_updated', details: $data);
        });

        return back()->with('success', 'Dispatch settings updated.');
    }
}
