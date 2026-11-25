<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicProfile;
use App\Models\User;
use App\Services\BarangayLocator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister(BarangayLocator $locator): View
    {
        return view('auth.register', [
            'cityBoundary' => $locator->cityFeature()
        ]);
    }

    public function register(Request $request, BarangayLocator $locator)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', 'in:rider,mechanic'],

            // Mechanic shop registration only
            'shop_name' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:255'
            ],

            'service_address' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:1000'
            ],

            'latitude' => [
                'exclude_unless:role,mechanic',
                'required',
                'numeric',
                'between:-90,90'
            ],

            'longitude' => [
                'exclude_unless:role,mechanic',
                'required',
                'numeric',
                'between:-180,180'
            ],

            'services' => [
                'exclude_unless:role,mechanic',
                'nullable',
                'array'
            ],

            'mechanics' => [
                'exclude_unless:role,mechanic',
                'required',
                'array',
                'min:1',
                'max:50'
            ],

            'mechanics.*.name' => [
                'exclude_unless:role,mechanic',
                'required',
                'string',
                'max:255'
            ],
        ]);

        // Validate GPS location for mechanic shops only
        $locationMatch = null;

        if ($data['role'] === 'mechanic') {
            $locationMatch = $locator->locate(
                (float) $data['latitude'],
                (float) $data['longitude']
            );

            if (!$locationMatch['within_city']) {
                throw ValidationException::withMessages([
                    'latitude' => 'Choose a shop location within Calapan City boundaries.',
                ]);
            }
        }

        // Create account
        $user = DB::transaction(function () use ($data, $locationMatch): User {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);

            // Create mechanic profile only for mechanic accounts
            if ($user->role === 'mechanic') {

                $profile = MechanicProfile::create([
                    'user_id' => $user->id,
                    'shop_name' => $data['shop_name'],
                    'service_address' => $data['service_address'],
                    'barangay' => $locationMatch['barangay_name'],
                    'barangay_id' => $locationMatch['barangay_id'],
                    'barangay_match_status' => $locationMatch['status'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'services' => $data['services'] ?? [],
                    'is_verified' => false,
                    'is_online' => false,
                ]);

                $profile->members()->createMany(
                    $data['mechanics']
                );
            }

            AuditLog::record(
                $user,
                'user.registered',
                details: ['role' => $user->role]
            );

            return $user;
        });

        // Automatically login after registration
        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route(
            $user->role . '.dashboard'
        );
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {
            return back()
                ->withErrors([
                    'email' => 'The provided credentials are incorrect.'
                ])
                ->onlyInput('email');
        }

        $user = $request->user();

        if ($user->is_suspended) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This account is suspended.'
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->update([
                'last_login_at' => now()
            ]);

            AuditLog::record(
                $user,
                'user.logged_in'
            );
        });

        $request->session()->regenerate();

        return redirect()->route(
            $user->role . '.dashboard'
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
