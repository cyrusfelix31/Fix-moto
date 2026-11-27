<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DispatchAttempt;
use App\Models\MechanicProfile;
use App\Models\ServiceRequest;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RiderController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('rider.dashboard', [
            'vehicleCount' => $user->vehicles()->count(),

            'availableShopCount' => MechanicProfile::where('is_verified', true)
                ->where('is_online', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereHas('activeMembers')
                ->count(),

            'activeRequestCount' => $user->riderRequests()
                ->whereNotIn('dispatch_status', [
                    'completed',
                    'cancelled',
                    'no_mechanics',
                ])
                ->where('service_status', '!=', 'completed')
                ->count(),

            'completedRequestCount' => $user->riderRequests()
                ->where('service_status', 'completed')
                ->count(),

            'mechanicShops' => MechanicProfile::where('is_verified', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with('mechanic')
                ->withCount('activeMembers')
                ->get(),
        ]);
    }

    public function vehicles(Request $request): View
    {
        return view('rider.vehicles', [
            'vehicles' => $request->user()
                ->vehicles()
                ->latest()
                ->get(),

            'vehicleCategories' => config(
                'fixmoto.vehicle_categories'
            ),
        ]);
    }

    public function history(Request $request): View
    {
        return view('rider.history', [
            'requests' => $request->user()
                ->riderRequests()
                ->with('vehicle')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function requestHelp(Request $request): View
    {
        return view('rider.request-help', [
            'vehicles' => $request->user()
                ->vehicles()
                ->latest()
                ->get(),

            'vehicleCategories' => config(
                'fixmoto.vehicle_categories'
            ),

            'availableServices' => \App\Services\ShopServiceCatalog::riderOptions(),
        ]);
    }

    public function storeVehicle(Request $request)
    {
        $data = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],

            'year' => [
                'required',
                'integer',
                'between:1950,'.(date('Y') + 1),
            ],

            'plate_number' => [
                'nullable', 'string', 'max:30',
            ],

            'vehicle_type' => [
                'required', 'string', 'max:100',
            ],

            'vehicle_category' => [
                'required',
                Rule::in(
                    array_keys(
                        config('fixmoto.vehicle_categories')
                    )
                ),
            ],

            'notes' => [
                'nullable', 'string', 'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $data,
            $request
        ): void {
            $vehicle = $request->user()
                ->vehicles()
                ->create($data);

            AuditLog::record(
                $request->user(),
                'vehicle.created',
                details: [
                    'vehicle_id' => $vehicle->id,
                ]
            );
        });

        return redirect()
            ->route('rider.vehicles.index')
            ->with('success', 'Vehicle added.');
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => [
                'required',
                'integer',
                'exists:vehicles,id',
            ],

            'services' => [
                'required_without:issue_category',
                'array',
                'min:1',
                'max:10',
            ],

            'services.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(\App\Services\ShopServiceCatalog::riderOptions()),
            ],

            'issue_category' => [
                'required_without:services',
                'nullable',
                'string',
                'max:100',
                Rule::in(\App\Services\ShopServiceCatalog::riderOptions()),
            ],

            'notes' => [
                'nullable', 'string', 'max:2000',
            ],

            'problem_description' => [
                'nullable', 'string', 'max:2000',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:13.1,13.7',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:121.1,121.5',
            ],

            'landmark' => [
                'nullable', 'string', 'max:255',
            ],
        ]);

        $vehicle = Vehicle::whereKey($data['vehicle_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $selectedServices = array_values(
            $data['services'] ?? [$data['issue_category']]
        );

        $notes = $data['notes']
            ?? $data['problem_description']
            ?? '';

        $serviceRequest = DB::transaction(function () use (
            $request,
            $vehicle,
            $data,
            $selectedServices,
            $notes
        ) {
            $serviceRequest = ServiceRequest::create([
                'reference' => 'FM-'
                    .now()->format('Ymd')
                    .'-'
                    .strtoupper(bin2hex(random_bytes(3))),

                'rider_id' => $request->user()->id,
                'vehicle_id' => $vehicle->id,
                'issue_category' => $selectedServices[0],
                'services' => $selectedServices,
                'problem_description' => $notes,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'landmark' => $data['landmark'] ?? null,
                'dispatch_status' => 'pending',

                'expires_at' => now()->addMinutes(
                    (int) config('fixmoto.dispatch_timeout', 120)
                ),
            ]);

            $nearestShop = MechanicProfile::query()
                ->where('is_verified', true)
                ->where('is_online', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereHas('activeMembers')
                ->with('mechanic')
                ->get()
                ->filter(fn (MechanicProfile $profile): bool =>
                    $profile->verification_status === 'approved'
                    && \App\Services\ShopServiceCatalog::supports(
                        $profile->services ?? [],
                        $selectedServices
                    )
                )
                ->map(fn (MechanicProfile $profile): array => [
                    'profile' => $profile,

                    'distance_km' => $profile->distanceTo(
                        (float) $data['latitude'],
                        (float) $data['longitude']
                    ),
                ])
                ->filter(fn (array $shop): bool =>
                    $shop['distance_km'] <=
                    (float) config('fixmoto.dispatch_radius', 25)
                )
                ->sortBy('distance_km')
                ->first();

            if ($nearestShop) {
                $shopProfile = $nearestShop['profile'];

                DispatchAttempt::create([
                    'service_request_id' => $serviceRequest->id,
                    'mechanic_id' => $shopProfile->user_id,
                    'status' => 'offered',

                    'expires_at' => now()->addSeconds(
                        (int) config('fixmoto.response_timeout', 1200)
                    ),

                    'distance_km' => $nearestShop['distance_km'],
                    'attempt_number' => 1,
                ]);

                $serviceRequest->update([
                    'dispatch_status' => 'offered',
                ]);
            } else {
                $serviceRequest->update([
                    'dispatch_status' => 'no_mechanics',
                ]);
            }

            AuditLog::record(
                $request->user(),
                'service_request.created',
                details: [
                    'reference' => $serviceRequest->reference,
                    'dispatch_status' =>
                        $serviceRequest->dispatch_status,
                ]
            );

            return $serviceRequest;
        });

        return redirect()
            ->route('rider.requests.show', $serviceRequest)
            ->with(
                $serviceRequest->dispatch_status === 'no_mechanics'
                    ? 'warning'
                    : 'success',

                $serviceRequest->dispatch_status === 'no_mechanics'
                    ? 'No verified mechanic shop is available yet.'
                    : 'Your request was sent to the nearest available mechanic shop.'
            );
    }

    public function showRequest(
        Request $request,
        ServiceRequest $serviceRequest
    ) {
        abort_if(
            $serviceRequest->rider_id !== $request->user()->id,
            403
        );

        $serviceRequest->load([
            'vehicle',
            'mechanic.mechanicProfile',
            'assignedMechanicMember',
            'dispatchAttempts',
            'statusHistory',
            'quotations.items',
            'payments',
        ]);

        return view(
            'rider.request',
            compact('serviceRequest')
        );
    }

    // Only the rider can confirm arrival,
    // repairing and completion.
    public function updateStatus(
        Request $request,
        ServiceRequest $serviceRequest
    ) {
        abort_if(
            $serviceRequest->rider_id !== $request->user()->id,
            403
        );

        $data = $request->validate([
            'status' => [
                'required',
                'in:arrived,repairing,completed',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $request,
            $serviceRequest,
            $data
        ): void {
            $serviceRequest = ServiceRequest::whereKey(
                $serviceRequest->id
            )->lockForUpdate()->firstOrFail();

            abort_if(
                $serviceRequest->rider_id !== $request->user()->id,
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
                'No mechanic has been assigned.'
            );

            $allowed = [
                'on_the_way' => 'arrived',
                'arrived' => 'repairing',
                'diagnosing' => 'repairing',
                'repairing' => 'completed',
            ];

            abort_if(
                ($allowed[$serviceRequest->service_status] ?? null)
                    !== $data['status'],
                422,
                'Invalid service status transition.'
            );

            $serviceRequest->update([
                'service_status' => $data['status'],
            ]);

            $serviceRequest->statusHistory()->create([
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'changed_by' => $request->user()->id,
            ]);

            AuditLog::record(
                $request->user(),
                'rider.service_status_confirmed',
                details: [
                    'service_request_reference' =>
                        $serviceRequest->reference,
                    'status' => $data['status'],
                ]
            );
        });

        $messages = [
            'arrived' => 'Mechanic arrival confirmed.',
            'repairing' => 'Repair started successfully.',
            'completed' => 'Service completed successfully.',
        ];

        return redirect()
            ->route('rider.requests.show', $serviceRequest)
            ->with(
                'success',
                $messages[$data['status']]
            );
    }
}
