<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicProfile;
use App\Models\MechanicShopService;
use App\Models\User;
use App\Services\BarangayLocator;
use App\Services\ShopDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister(
        BarangayLocator $locator
    ): View {
        return view('auth.register', [
            'cityBoundary' => $locator->cityFeature(),
        ]);
    }

    public function register(
        Request $request,
        BarangayLocator $locator,
        ShopDocumentService $documentService
    ) {

        $data = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'required_if:role,mechanic',
                'string',
                'max:32',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
            ],

            'role' => [
                'required',
                'in:rider,mechanic',
            ],

            'shop_name' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:255',
            ],

            'shop_phone' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:32',
            ],

            'service_address' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:1000',
            ],

            'latitude' => [
                'exclude_unless:role,mechanic',
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'exclude_unless:role,mechanic',
                'required',
                'numeric',
                'between:-180,180',
            ],

            'services' => [
                'exclude_unless:role,mechanic',
                'nullable',
                'array',
            ],

            'mechanics' => [
                'exclude_unless:role,mechanic',
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'mechanics.*.name' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:255',
            ],

            ...$documentService->rules(true),

        ]);

        $locationMatch = null;
        $documents = [];

        if ($data['role'] === 'mechanic') {

            $locationMatch = $locator->locate(
                (float) $data['latitude'],
                (float) $data['longitude']
            );

            if (! $locationMatch['within_city']) {

                throw ValidationException::withMessages([
                    'latitude' => 'Choose a shop location within Calapan City boundaries.',
                ]);
            }

            $documents = $documentService->store(
                $request
            );
        }

        try {

            $user = DB::transaction(
                function () use (
                    $data,
                    $locationMatch,
                    $documents
                ): User {

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'password' => Hash::make(
                            $data['password']
                        ),
                        'role' => $data['role'],
                    ]);

                    if ($user->role === 'mechanic') {

                        $profile = MechanicProfile::create([

                            'user_id' => $user->id,

                            'shop_name' => $data['shop_name'],

                            'shop_phone' => $data['shop_phone'],

                            'service_address' => $data['service_address'],

                            'barangay' => $locationMatch['barangay_name'],

                            'barangay_id' => $locationMatch['barangay_id'],

                            'barangay_match_status' => $locationMatch['status'],

                            'latitude' => $data['latitude'],

                            'longitude' => $data['longitude'],

                            'services' => $data['services'] ?? [],

                            'verification_status' => 'pending',

                            'is_verified' => false,

                            'is_online' => false,

                        ]);

                        $members = array_map(
                            fn ($mechanic) => [
                                'name' => $mechanic['name'],
                            ],
                            $data['mechanics']
                        );

                        $profile->members()->createMany(
                            $members
                        );

                        $profile->shopServices()->createMany(array_map(
                            fn (string $name): array => ['name' => $name, 'is_enabled' => true],
                            MechanicShopService::DEFAULT_SERVICES
                        ));

                        $profile->documents()->createMany(
                            $documents
                        );
                    }

                    AuditLog::record(
                        $user,
                        'user.registered',
                        details: [
                            'role' => $user->role,
                        ]
                    );

                    return $user;
                }
            );

        } catch (Throwable $exception) {

            $documentService->delete(
                array_column(
                    $documents,
                    'document_path'
                )
            );

            throw $exception;
        }

        Auth::login($user);

        $request->session()->regenerate();

        if ($user->role === 'mechanic') {

            return redirect()
                ->route('mechanic.verification.status')
                ->with(
                    'success',
                    'Your registration has been submitted successfully.'
                );
        }

        return redirect()->route('rider.dashboard');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {

            return back()
                ->withErrors([
                    'email' => 'The provided credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        $user = $request->user();

        if ($user->is_suspended) {

            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This account is suspended.',
            ]);
        }

        DB::transaction(function () use ($user): void {

            $user->update([
                'last_login_at' => now(),
            ]);

            AuditLog::record(
                $user,
                'user.logged_in'
            );

        });

        $request->session()->regenerate();

        if ($user->role === 'mechanic') {

            $profile = $user->mechanicProfile;

            if (
                ! $profile
                || ! $profile->is_verified
                || $profile->verification_status !== 'approved'
            ) {

                return redirect()->route(
                    'mechanic.verification.status'
                );
            }
        }

        return redirect()->route(
            $user->role.'.dashboard'
        );
    }

    public function logout(Request $request)
    {
        AuditLog::record(
            $request->user(),
            'user.logged_out'
        );

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
