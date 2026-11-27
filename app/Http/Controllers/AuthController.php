<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicProfile;
use App\Models\MechanicDocument;
use App\Models\RegistrationOtp;
use App\Models\MechanicShopService;
use App\Models\User;
use App\Notifications\MechanicApplicationSubmitted;
use App\Rules\OwnerFullName;
use App\Services\BarangayLocator;
use App\Services\ShopDocumentService;
use App\Services\RegistrationOtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showMechanicAccess(): View
    {
        return view('auth.mechanic-access');
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
        ShopDocumentService $documentService,
        RegistrationOtpService $otpService
    ) {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'phone' => trim((string) $request->input('phone')),
            'shop_phone' => trim((string) $request->input('shop_phone')),
        ]);

        $phonePattern = '/^09[0-9]{9}$/';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', new OwnerFullName],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $value))])->exists()) {
                        $fail('This email address is already registered.');
                    }
                },
            ],
            'phone' => ['nullable', 'required_if:role,mechanic', 'string', 'regex:'.$phonePattern],
            'password' => ['exclude_unless:role,rider', 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['rider', 'mechanic'])],
            'shop_name' => ['exclude_unless:role,mechanic', 'required', 'string', 'max:255'],
            'shop_phone' => ['exclude_unless:role,mechanic', 'required', 'string', 'regex:'.$phonePattern],
            'service_address' => ['exclude_unless:role,mechanic', 'required', 'string', 'max:1000'],
            'latitude' => ['exclude_unless:role,mechanic', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['exclude_unless:role,mechanic', 'required', 'numeric', 'between:-180,180'],
            'services' => ['exclude_unless:role,mechanic', 'nullable', 'array'],
            ...$documentService->rules(true),
        ], [
            'email.email' => 'Invalid email address. Please include an @ symbol.',
            'email.unique' => 'This email address is already registered.',
            'name.required' => 'Please enter your first and last name.',
            'phone.regex' => 'Enter an 11-digit Philippine mobile number starting with 09.',
            'shop_phone.regex' => 'Enter an 11-digit Philippine mobile number starting with 09.',
        ]);

        $locationMatch = null;
        $documents = [];

        if ($data['role'] === 'mechanic') {
            $locationMatch = $locator->locate((float) $data['latitude'], (float) $data['longitude']);
            if (! $locationMatch['within_city'] || $locationMatch['status'] !== 'matched') {
                throw ValidationException::withMessages([
                    'latitude' => 'Choose a precise shop location inside a known Calapan City barangay.',
                ]);
            }

            $documents = $documentService->store($request);
        }

        $payload = $data;
        unset($payload['password_confirmation']);
        foreach (MechanicDocument::TYPES as $type => $label) {
            unset($payload[$type], $payload[$type.'_expiration']);
        }
        if ($data['role'] === 'rider') {
            $payload['password'] = Hash::make($data['password']);
        } else {
            unset($payload['password']);
        }
        if ($locationMatch) {
            $payload['_location_match'] = [
                'barangay_name' => $locationMatch['barangay_name'],
                'barangay_id' => $locationMatch['barangay_id'],
                'status' => $locationMatch['status'],
            ];
        }
        $payload['documents'] = $documents;

        $challenge = null;
        try {
            [$challenge, $code] = $otpService->issue($data['email'], $payload);
            $otpService->send($challenge, $code);
        } catch (Throwable $exception) {
            $documentService->delete(array_column($documents, 'document_path'));
            if ($challenge) {
                $challenge->delete();
            }
            return back()->withErrors([
                'email' => 'We could not send the verification code. Check the email delivery settings and try again.',
            ])->withInput($request->except(['password', 'password_confirmation']));
        }

        return back()
            ->with('otp_challenge', $challenge->id)
            ->with('otp_email', $challenge->email)
            ->with('otp_resend_seconds', (int) config('fixmoto.otp_resend_seconds', 60))
            ->with('success', 'A verification code has been sent to your email address.');
    }

    public function checkEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ], [
            'email.email' => 'Invalid email address. Please include an @ symbol.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $available = ! User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? null : 'This email address is already registered.',
        ]);
    }

    public function verifyRegistrationOtp(Request $request, RegistrationOtpService $otpService)
    {
        $digitCount = max(4, min(9, (int) config('fixmoto.otp_length', 6)));
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'otp' => ['required', 'digits:'.$digitCount],
        ]);

        $result = DB::transaction(function () use ($data, $otpService): array {
            $challenge = RegistrationOtp::query()->whereKey($data['challenge_id'])->lockForUpdate()->first();
            if (! $challenge || $challenge->purpose !== 'registration' || $challenge->verified_at) {
                return ['error' => 'This verification session is no longer valid. Start registration again.'];
            }
            if ($challenge->expires_at->isPast()) {
                return ['error' => 'This code has expired. Start registration again to receive a new code.', 'email' => $challenge->email];
            }
            if ($challenge->attempts >= (int) config('fixmoto.otp_max_attempts', 5)) {
                return ['error' => 'You have used all attempts for this code. Wait for the resend timer and request a new one.', 'email' => $challenge->email];
            }
            if (! Hash::check($data['otp'], $challenge->otp_hash)) {
                $challenge->increment('attempts');
                return ['error' => 'That code is incorrect. Please check it and try again.', 'email' => $challenge->email];
            }

            $payload = $challenge->payload;
            if (User::query()->whereRaw('LOWER(email) = ?', [$challenge->email])->exists()) {
                return ['error' => 'This email address is already registered.', 'email' => $challenge->email];
            }

            $challenge->update(['verified_at' => now()]);
            $user = User::create([
                'name' => $payload['name'],
                'email' => $challenge->email,
                'phone' => $payload['phone'],
                'password' => $payload['password'] ?? Hash::make(Str::random(64)),
                'role' => $payload['role'],
                'password_setup_required' => $payload['role'] === 'mechanic',
                'profile_photo_setup_required' => $payload['role'] === 'mechanic',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            if ($user->role === 'mechanic') {
                $locationMatch = $payload['_location_match'];
                $profile = MechanicProfile::create([
                    'user_id' => $user->id,
                    'shop_name' => $payload['shop_name'],
                    'shop_phone' => $payload['shop_phone'],
                    'service_address' => $payload['service_address'],
                    'barangay' => $locationMatch['barangay_name'],
                    'barangay_id' => $locationMatch['barangay_id'],
                    'barangay_match_status' => $locationMatch['status'],
                    'latitude' => $payload['latitude'],
                    'longitude' => $payload['longitude'],
                    'services' => $payload['services'] ?? [],
                    'verification_status' => 'pending_verification',
                    'is_verified' => false,
                    'is_online' => false,
                ]);

                $profile->shopServices()->createMany(array_map(
                    fn (string $name): array => ['name' => $name, 'is_enabled' => true],
                    MechanicShopService::DEFAULT_SERVICES,
                ));
                $profile->documents()->createMany($payload['documents']);
            }

            AuditLog::record($user, 'user.registered', details: [
                'role' => $user->role,
                'email_verified' => true,
            ]);

            return ['user' => $user];
        });

        if (isset($result['error'])) {
            $challenge = RegistrationOtp::query()->find($data['challenge_id']);
            return back()->withErrors(['otp' => $result['error']])->withInput([
                'challenge_id' => $data['challenge_id'],
                'otp_email' => $result['email'] ?? '',
                'otp_resend_remaining' => $challenge
                    ? max(0, $challenge->resend_available_at->getTimestamp() - now()->getTimestamp())
                    : 0,
            ]);
        }

        $user = $result['user'];
        if ($user->role === 'mechanic') {
            User::query()->where('role', 'admin')->get()->each(
                fn (User $admin) => $admin->notify(new MechanicApplicationSubmitted(
                    $user->id,
                    $user->mechanicProfile?->shop_name ?? $user->name,
                ))
            );
        }
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role === 'mechanic') {
            return redirect()->route('mechanic.verification.status')->with('success', 'Your registration has been submitted successfully.');
        }

        return redirect()->route('rider.dashboard')->with('success', 'Your email address has been verified.');
    }

    public function resendRegistrationOtp(Request $request, RegistrationOtpService $otpService)
    {
        $data = $request->validate(['challenge_id' => ['required', 'uuid']]);
        $challenge = RegistrationOtp::query()->find($data['challenge_id']);
        if (! $challenge || $challenge->purpose !== 'registration' || $challenge->verified_at || $challenge->expires_at->isPast()) {
            return back()->withErrors(['otp' => 'This verification session has expired. Start registration again.']);
        }

        if ($challenge->resend_available_at->isFuture()) {
            return back()->withErrors(['otp' => 'Please wait before requesting another code.'])
                ->withInput([
                    'challenge_id' => $challenge->id,
                    'otp_email' => $challenge->email,
                    'otp_resend_remaining' => max(0, $challenge->resend_available_at->getTimestamp() - now()->getTimestamp()),
                ]);
        }

        $code = DB::transaction(function () use ($data, $otpService): ?string {
            $locked = RegistrationOtp::query()->whereKey($data['challenge_id'])->lockForUpdate()->first();
            if (! $locked || $locked->verified_at || $locked->expires_at->isPast()) {
                return null;
            }
            if ($locked->resend_available_at->isFuture()) {
                return null;
            }
            return $otpService->resend($locked);
        });

        if (! $code) {
            return back()->withErrors(['otp' => 'Please wait before requesting another code.']);
        }

        try {
            $otpService->send($challenge, $code);
        } catch (Throwable $exception) {
            return back()->withErrors(['otp' => 'We could not resend the code. Check the email delivery settings and try again.'])
                ->withInput(['challenge_id' => $challenge->id, 'otp_email' => $challenge->email]);
        }

        return back()->with('otp_challenge', $challenge->id)
            ->with('otp_email', $challenge->email)
            ->with('otp_resend_seconds', (int) config('fixmoto.otp_resend_seconds', 60))
            ->with('success', 'A new verification code has been sent. The previous code no longer works.');
    }

    public function requestMechanicAccessCode(Request $request, RegistrationOtpService $otpService)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->where('role', 'mechanic')->first();
        [$challenge, $code] = $otpService->issue($email, ['user_id' => $user?->id], 'mechanic_access');
        if ($user) {
            try {
                $otpService->send($challenge, $code);
            } catch (Throwable $exception) {
                $challenge->delete();
                return back()->withErrors(['email' => 'We could not send a code right now. Please try again.'])->onlyInput('email');
            }
        }

        return back()->with('mechanic_access_challenge', $challenge->id)
            ->with('mechanic_access_email', $email)
            ->with('success', 'If this email belongs to a mechanic shop, an access code has been sent.');
    }

    public function verifyMechanicAccessCode(Request $request)
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'otp' => ['required', 'digits:' . max(4, min(9, (int) config('fixmoto.otp_length', 6)))],
        ]);
        $result = DB::transaction(function () use ($data): array {
            $challenge = RegistrationOtp::query()->whereKey($data['challenge_id'])->lockForUpdate()->first();
            if (!$challenge || $challenge->purpose !== 'mechanic_access' || $challenge->verified_at || $challenge->expires_at->isPast()) {
                return ['error' => 'This access code is no longer valid. Request a new one.'];
            }
            if ($challenge->attempts >= (int) config('fixmoto.otp_max_attempts', 5)) {
                return ['error' => 'Too many attempts. Request a new code when the timer allows.'];
            }
            if (!Hash::check($data['otp'], $challenge->otp_hash)) {
                $challenge->increment('attempts');
                return ['error' => 'That code is incorrect. Please check it and try again.'];
            }
            $user = User::query()->whereKey($challenge->payload['user_id'] ?? 0)->where('role', 'mechanic')->first();
            if (!$user || $user->is_suspended) {
                return ['error' => 'This access code is no longer valid. Request a new one.'];
            }
            $challenge->update(['verified_at' => now()]);
            return ['user' => $user];
        });
        if (isset($result['error'])) {
            $challenge = RegistrationOtp::query()->whereKey($data['challenge_id'])->first();
            $response = back()->withErrors(['otp' => $result['error']])->withInput(['challenge_id' => $data['challenge_id']]);
            return $challenge?->purpose === 'mechanic_access'
                ? $response->with('mechanic_access_challenge', $challenge->id)->with('mechanic_access_email', $challenge->email)
                : $response;
        }
        Auth::login($result['user']);
        $request->session()->regenerate();
        $profile = $result['user']->mechanicProfile;
        if ($profile?->is_verified && $profile->verification_status === 'fully_verified' && !$result['user']->password_setup_required) {
            return redirect()->route('mechanic.dashboard');
        }
        return redirect()->route('mechanic.verification.status');
    }

    public function resendMechanicAccessCode(Request $request, RegistrationOtpService $otpService)
    {
        $data = $request->validate(['challenge_id' => ['required', 'uuid']]);
        $challenge = RegistrationOtp::query()->whereKey($data['challenge_id'])->where('purpose', 'mechanic_access')->first();
        if (!$challenge || $challenge->verified_at || $challenge->expires_at->isPast()) {
            return back()->withErrors(['otp' => 'This access session has expired. Request a new code.']);
        }
        if ($challenge->resend_available_at->isFuture()) {
            return back()->withErrors(['otp' => 'Please wait before requesting another code.'])
                ->withInput(['challenge_id' => $challenge->id])
                ->with('mechanic_access_challenge', $challenge->id)
                ->with('mechanic_access_email', $challenge->email);
        }
        $code = DB::transaction(function () use ($challenge, $otpService): ?string {
            $locked = RegistrationOtp::query()->whereKey($challenge->id)->lockForUpdate()->first();
            if (!$locked || $locked->verified_at || $locked->expires_at->isPast() || $locked->resend_available_at->isFuture()) return null;
            return $otpService->resend($locked);
        });
        if ($code && !empty($challenge->payload['user_id'])) {
            try { $otpService->send($challenge, $code); }
            catch (Throwable $exception) {
                return back()->withErrors(['otp' => 'We could not resend the code. Try again.'])
                    ->with('mechanic_access_challenge', $challenge->id)
                    ->with('mechanic_access_email', $challenge->email);
            }
        }
        return back()->with('mechanic_access_challenge', $challenge->id)->with('mechanic_access_email', $challenge->email)->with('success', 'A new access code has been sent if the account exists.');
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
                || $profile->verification_status !== 'fully_verified'
            ) {

                return redirect()->route(
                    'mechanic.verification.status'
                );
            }
            if ($user->password_setup_required) {
                return redirect()->route('mechanic.password.setup.notice');
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
