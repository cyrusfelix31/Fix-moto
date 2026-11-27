<?php

namespace App\Http\Controllers;

use App\Mail\MechanicAccountSetupMail;
use App\Models\User;
use App\Notifications\MechanicAvailabilityNotice;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class MechanicAccountSetupController extends Controller
{
    public function notice(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->role === 'mechanic' && $user->password_setup_required, 403);
        abort_if($user->is_suspended, 403);
        abort_unless($user->mechanicProfile?->verification_status === 'fully_verified', 403);

        return view('auth.mechanic-password-setup-notice');
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'mechanic' && $user->password_setup_required, 403);
        abort_if($user->is_suspended, 403);
        abort_unless($user->mechanicProfile?->verification_status === 'fully_verified', 403);

        try {
            $token = Password::broker()->createToken($user);
            Mail::to($user->email)->send(new MechanicAccountSetupMail($user, $token));
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['email' => 'We could not send the setup link right now. Please try again later.']);
        }

        return back()->with('success', 'A new password setup link has been sent to your email.');
    }

    public function show(Request $request, string $token): View
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
            ->where('role', 'mechanic')->firstOrFail();
        abort_if($user->is_suspended, 403);
        abort_unless($user->password_setup_required && $user->mechanicProfile?->verification_status === 'fully_verified', 403);
        $valid = Password::broker()->tokenExists($user, $token);

        return view('auth.mechanic-password-setup', compact('token', 'user', 'valid'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password): void {
            if ($user->role !== 'mechanic' || $user->is_suspended || !$user->password_setup_required
                || $user->mechanicProfile?->verification_status !== 'fully_verified') {
                throw ValidationException::withMessages(['email' => 'This setup link is no longer valid.']);
            }
            $user->forceFill(['password' => $password, 'password_setup_required' => false])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('mechanic.profile.setup')->with('success', 'Your password is ready. Add a profile photo or skip this step.');
    }

    public function photoSetup(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->role === 'mechanic', 403);
        abort_if($user->is_suspended, 403);
        $profile = $user->mechanicProfile;
        if (!$profile || $profile->verification_status !== 'fully_verified') {
            return redirect()->route('mechanic.verification.status');
        }
        abort_unless(!$user->password_setup_required && $user->profile_photo_setup_required, 403);

        return view('auth.mechanic-profile-setup', compact('user', 'profile'));
    }

    public function savePhotoSetup(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'mechanic', 403);
        abort_if($user->is_suspended, 403);
        abort_unless($user->mechanicProfile?->verification_status === 'fully_verified', 403);
        abort_unless(!$user->password_setup_required && $user->profile_photo_setup_required, 403);

        $data = $request->validate([
            'choice' => ['required', 'in:save,skip'],
            'profile_photo' => ['required_if:choice,save', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'profile_photo.required_if' => 'Choose a profile photo or select Skip for Now.',
            'profile_photo.image' => 'Upload a valid image file.',
            'profile_photo.mimes' => 'Use a JPG, PNG, or WEBP image.',
            'profile_photo.max' => 'The image must be 5 MB or smaller.',
        ]);

        if ($data['choice'] === 'save') {
            $oldPath = $user->avatar_path;
            $path = $request->file('profile_photo')->store('shop-profiles/owners', 'public');
            if (!$path) {
                return back()->withErrors(['profile_photo' => 'We could not save that photo. Please try another image.']);
            }
            $user->forceFill(['avatar_path' => $path])->save();
            if ($oldPath && str_starts_with($oldPath, 'shop-profiles/owners/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
            }
        }

        $user->forceFill(['profile_photo_setup_required' => false])->save();

        if (!$user->mechanicProfile?->hasAvailableMechanic()) {
            $user->notify(new MechanicAvailabilityNotice(
                'Your account is ready, but your shop will stay offline until you add at least one active mechanic who is available for service requests.'
            ));
        }

        return redirect()->route('mechanic.dashboard')->with('success', $data['choice'] === 'skip'
            ? 'Setup complete. You can add a profile photo later in your shop profile.'
            : 'Profile photo saved. Your FixMoto account is ready.');
    }
}
