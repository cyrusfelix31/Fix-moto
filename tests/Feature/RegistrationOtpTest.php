<?php

namespace Tests\Feature;

use App\Mail\RegistrationOtpMail;
use App\Models\MechanicProfile;
use App\Models\RegistrationOtp;
use App\Models\User;
use Database\Seeders\CalapanBarangaySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationOtpTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rider_must_verify_email_before_account_creation_and_can_resend_after_five_failed_attempts(): void
    {
        Mail::fake();
        $email = 'rider.'.Str::uuid().'@gmail.com';

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Rider Applicant',
            'email' => $email,
            'role' => 'rider',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('register'))
            ->assertSessionHas('otp_challenge');

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $challenge = RegistrationOtp::query()->where('email', $email)->firstOrFail();
        $initialMail = Mail::sent(RegistrationOtpMail::class)->first();

        $this->assertNotNull($initialMail);
        $this->assertTrue($initialMail->hasTo($email));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $initialMail->code);
        $this->assertSame(5, $initialMail->expirationMinutes);
        $this->assertTrue(Hash::check($initialMail->code, $challenge->otp_hash));

        $wrongCode = $initialMail->code === '000000' ? '111111' : '000000';
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('register'))
                ->post(route('register.verify-otp'), [
                    'challenge_id' => $challenge->id,
                    'otp' => $wrongCode,
                ])
                ->assertRedirect(route('register'))
                ->assertSessionHas('_old_input.otp_attempts_remaining', 5 - $attempt);
        }

        $challenge->refresh();
        $this->assertSame(5, $challenge->attempts);
        $this->assertTrue($challenge->expires_at->isPast());
        $this->assertFalse(Hash::check($initialMail->code, $challenge->otp_hash));
        $this->assertDatabaseMissing('users', ['email' => $email]);

        $this->travel(61)->seconds();
        $this->from(route('register'))
            ->post(route('register.resend-otp'), ['challenge_id' => $challenge->id])
            ->assertRedirect(route('register'))
            ->assertSessionHas('otp_challenge', $challenge->id);

        $challenge->refresh();
        $resendMail = Mail::sent(RegistrationOtpMail::class)->last();
        $this->assertSame(0, $challenge->attempts);
        $this->assertTrue($challenge->expires_at->isFuture());
        $this->assertNotNull($resendMail);
        $this->assertTrue($resendMail->hasTo($email));
        $this->assertNotSame($initialMail->code, $resendMail->code);
        $this->assertFalse(Hash::check($initialMail->code, $challenge->otp_hash));

        $this->from(route('register'))
            ->post(route('register.verify-otp'), [
                'challenge_id' => $challenge->id,
                'otp' => $resendMail->code,
            ])
            ->assertRedirect(route('rider.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => 'rider',
        ]);
        $this->assertNotNull(User::query()->where('email', $email)->firstOrFail()->email_verified_at);
    }

    public function test_mechanic_email_otp_creates_a_pending_application_only_after_verification(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->seed(CalapanBarangaySeeder::class);
        $email = 'mechanic.'.Str::uuid().'@gmail.com';

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Mechanic Owner',
            'email' => $email,
            'phone' => '09171234567',
            'role' => 'mechanic',
            'shop_name' => 'OTP Test Shop',
            'shop_phone' => '09181234567',
            'service_address' => 'Barangay San Vicente East, Calapan City, Oriental Mindoro',
            'latitude' => '13.4112',
            'longitude' => '121.1803',
            'business_permit_expiration' => today('Asia/Manila')->addYear()->toDateString(),
            'mayors_permit_expiration' => today('Asia/Manila')->addYear()->toDateString(),
            'valid_id' => UploadedFile::fake()->image('valid-id.jpg'),
            'business_permit' => UploadedFile::fake()->image('business-permit.jpg'),
            'mayors_permit' => UploadedFile::fake()->image('mayors-permit.jpg'),
            'bir_certificate' => UploadedFile::fake()->image('bir-certificate.jpg'),
        ])->assertRedirect(route('register'))
            ->assertSessionHas('otp_challenge');

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $challenge = RegistrationOtp::query()->where('email', $email)->firstOrFail();
        $mail = Mail::sent(RegistrationOtpMail::class)->first();

        $this->assertNotNull($mail);
        $this->assertTrue($mail->hasTo($email));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $mail->code);
        $this->assertTrue(Hash::check($mail->code, $challenge->otp_hash));

        $this->from(route('register'))
            ->post(route('register.verify-otp'), [
                'challenge_id' => $challenge->id,
                'otp' => $mail->code,
            ])
            ->assertRedirect(route('mechanic.verification.status'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('mechanic_profiles', [
            'user_id' => $user->id,
            'shop_name' => 'OTP Test Shop',
            'verification_status' => 'pending_verification',
        ]);
        $this->assertSame(4, MechanicProfile::query()->where('user_id', $user->id)->firstOrFail()->documents()->count());
    }
}
