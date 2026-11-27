<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicProfile;
use App\Models\MechanicShopMember;
use App\Services\BarangayLocator;
use App\Services\ShopServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ShopProfileController extends Controller
{
    private const ACTIVE_STATUSES = [
        'accepted',
        'on_the_way',
        'arrived',
        'diagnosing',
        'repairing',
    ];

    /*
    |--------------------------------------------------------------------------
    | DISPLAY SHOP PROFILE
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request,
        BarangayLocator $locator
    ): View {
        $owner = $request->user();

        $profile = $owner->mechanicProfile()
            ->withCount('activeMembers')
            ->firstOrFail();

        $teamMembers = $profile->members()
            ->withCount([
                'serviceRequests as active_request_count' =>
                    fn ($query) => $query->whereIn(
                        'service_status',
                        self::ACTIVE_STATUSES
                    ),
            ])
            ->orderBy('name')
            ->get();

        return view('mechanic.shop-profile', [
            'owner' => $owner,
            'profile' => $profile,
            'teamMembers' => $teamMembers,
            'shopServices' => $profile->shopServices()
                ->orderBy('name')
                ->get(),
            'cityBoundary' => $locator->cityFeature(),
            'defaultServices' => config('fixmoto.services', []),
            'serviceLabels' => ShopServiceCatalog::LABELS,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE OWNER INFORMATION
    |--------------------------------------------------------------------------
    */

    public function updateOwner(
        Request $request
    ): RedirectResponse {
        $owner = $request->user();

        $owner->mechanicProfile()->firstOrFail();

        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($owner->id),
            ],

            'phone' => [
                'required',
                'string',
                'max:32',
            ],

            'profile_picture' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        unset($data['profile_picture']);

        $this->withPhoto(
            $request->file('profile_picture'),
            $owner->profile_picture,

            function (?string $path) use (
                $owner,
                $data
            ): void {
                DB::transaction(function () use (
                    $owner,
                    $data,
                    $path
                ): void {
                    if ($owner->email !== $data['email']) {
                        $owner->email_verified_at = null;
                    }

                    $owner->fill($data);

                    if ($path !== null) {
                        $owner->profile_picture = $path;
                    }

                    $owner->save();

                    AuditLog::record(
                        $owner,
                        'mechanic_shop.owner_updated'
                    );
                });
            }
        );

        return back()->with(
            'success',
            'Owner information updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADD MECHANIC
    |--------------------------------------------------------------------------
    */

    public function storeMember(
        Request $request
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        $data = $request->validate(
            $this->memberRules()
        );

        unset($data['profile_picture']);

        $this->withPhoto(
            $request->file('profile_picture'),
            null,

            function (?string $path) use (
                $profile,
                $data,
                $request
            ): void {
                DB::transaction(function () use (
                    $profile,
                    $data,
                    $path,
                    $request
                ): void {
                    $lockedProfile = MechanicProfile::query()
                        ->whereKey($profile->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($lockedProfile->members()->count() >= 50) {
                        throw ValidationException::withMessages([
                            'team' => 'Maximum of 50 mechanics per shop.',
                        ]);
                    }

                    $member = $lockedProfile->members()->create([
                        ...$data,
                        'profile_picture' => $path,
                        'is_active' => true,
                    ]);

                    AuditLog::record(
                        $request->user(),
                        'mechanic_shop.member_added',
                        details: [
                            'mechanic_shop_member_id' => $member->id,
                            'name' => $member->name,
                        ]
                    );
                });
            }
        );

        return back()->with(
            'success',
            'Mechanic added successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT MECHANIC INFORMATION
    |--------------------------------------------------------------------------
    */

    public function updateMember(
        Request $request,
        MechanicShopMember $member
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        abort_unless(
            (int) $member->mechanic_profile_id === (int) $profile->id,
            404
        );

        $data = $request->validate(
            $this->memberRules()
        );

        unset($data['profile_picture']);

        $this->withPhoto(
            $request->file('profile_picture'),
            $member->profile_picture,

            function (?string $path) use (
                $member,
                $data,
                $request
            ): void {
                DB::transaction(function () use (
                    $member,
                    $data,
                    $path,
                    $request
                ): void {
                    if ($path !== null) {
                        $data['profile_picture'] = $path;
                    }

                    $member->update($data);

                    AuditLog::record(
                        $request->user(),
                        'mechanic_shop.member_updated',
                        details: [
                            'mechanic_shop_member_id' => $member->id,
                        ]
                    );
                });
            }
        );

        return back()->with(
            'success',
            'Mechanic information updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE SHOP SERVICES AND LOCATION
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        BarangayLocator $locator
    ): RedirectResponse {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        $data = $request->validate([
            'shop_phone' => [
                'nullable',
                'string',
                'max:32',
            ],

            'service_address' => [
                'required',
                'string',
                'max:1000',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'services' => [
                'nullable',
                'array',
                'max:50',
            ],

            'services.*' => [
                'required',
                'string',
                'max:100',
                'distinct',
            ],
        ]);

        $match = $locator->locate(
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if (!$match['within_city']) {
            throw ValidationException::withMessages([
                'latitude' => 'Shop location must be within Calapan City.',
            ]);
        }

        $services = ShopServiceCatalog::normalize(
            $data['services'] ?? []
        );

        DB::transaction(function () use (
            $profile,
            $data,
            $services,
            $match,
            $request
        ): void {
            $profile->update([
                'shop_phone' => $data['shop_phone'] ?? null,
                'service_address' => $data['service_address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'barangay' => $match['barangay_name'],
                'barangay_id' => $match['barangay_id'],
                'barangay_match_status' => $match['status'],
                'services' => $services,
            ]);

            AuditLog::record(
                $request->user(),
                'mechanic_shop.profile_updated',
                details: [
                    'services' => $services,
                    'latitude' => $profile->latitude,
                    'longitude' => $profile->longitude,
                ]
            );
        });

        return back()->with(
            'success',
            'Shop profile saved successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SECURE OWNER PROFILE PICTURE
    |--------------------------------------------------------------------------
    */

    public function ownerPhoto(Request $request)
    {
        return $this->photoResponse(
            $request->user()->profile_picture
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SECURE MECHANIC PROFILE PICTURE
    |--------------------------------------------------------------------------
    */

    public function memberPhoto(
        Request $request,
        MechanicShopMember $member
    ) {
        $profile = $request->user()
            ->mechanicProfile()
            ->firstOrFail();

        abort_unless(
            (int) $member->mechanic_profile_id === (int) $profile->id,
            404
        );

        return $this->photoResponse(
            $member->profile_picture
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION RULES
    |--------------------------------------------------------------------------
    */

    private function memberRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:32',
            ],

            'specialization' => [
                'nullable',
                'string',
                'max:255',
            ],

            'years_of_experience' => [
                'nullable',
                'integer',
                'between:0,80',
            ],

            'profile_picture' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE STORAGE
    |--------------------------------------------------------------------------
    */

    private function withPhoto(
        ?UploadedFile $file,
        ?string $oldPath,
        callable $save
    ): void {
        $path = null;

        if ($file !== null) {
            $path = $file->store(
                'shop-profile-photos',
                'local'
            );

            if (!$path) {
                throw new RuntimeException(
                    'Unable to save profile picture.'
                );
            }
        }

        try {
            $save($path);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        if (
            $path &&
            $oldPath &&
            str_starts_with($oldPath, 'shop-profile-photos/')
        ) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    private function photoResponse(?string $path)
    {
        abort_unless(
            $path &&
            str_starts_with($path, 'shop-profile-photos/') &&
            Storage::disk('local')->exists($path),
            404
        );

        return Storage::disk('local')->response(
            $path,
            null,
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
