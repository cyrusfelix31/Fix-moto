<?php

namespace App\Services;

use App\Mail\RegistrationOtpMail;
use App\Models\RegistrationOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RegistrationOtpService
{
    /** @return array{0: RegistrationOtp, 1: string} */
    public function issue(string $email, array $payload, string $purpose = 'registration'): array
    {
        $digitCount = max(4, min(9, (int) config('fixmoto.otp_length', 6)));
        $minimum = 10 ** ($digitCount - 1);
        $maximum = (10 ** $digitCount) - 1;
        $code = str_pad((string) random_int(0, $maximum), $digitCount, '0', STR_PAD_LEFT);
        $expiration = max(1, (int) config('fixmoto.otp_expiration_minutes', 5));

        $oldDocumentPaths = [];
        $challenge = DB::transaction(function () use ($email, $payload, $code, $expiration, $purpose, &$oldDocumentPaths): RegistrationOtp {
            $previous = RegistrationOtp::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->lockForUpdate()
                ->get();

            foreach ($previous as $item) {
                if ($purpose === 'registration') {
                    $oldDocumentPaths = array_merge(
                        $oldDocumentPaths,
                        array_column($item->payload['documents'] ?? [], 'document_path'),
                    );
                }
            }

            RegistrationOtp::query()
                ->whereIn('id', $previous->modelKeys())
                ->delete();

            return RegistrationOtp::query()->create([
                'id' => (string) Str::uuid(),
                'email' => $email,
                'purpose' => $purpose,
                'otp_hash' => Hash::make($code),
                'payload' => $payload,
                'attempts' => 0,
                'expires_at' => now()->addMinutes($expiration),
                'resend_available_at' => now()->addSeconds(max(1, (int) config('fixmoto.otp_resend_seconds', 60))),
            ]);
        });

        $this->deletePendingDocuments($oldDocumentPaths);

        return [$challenge, $code];
    }

    public function resend(RegistrationOtp $challenge): string
    {
        $digitCount = max(4, min(9, (int) config('fixmoto.otp_length', 6)));
        $maximum = (10 ** $digitCount) - 1;
        $code = str_pad((string) random_int(0, $maximum), $digitCount, '0', STR_PAD_LEFT);

        $challenge->update([
            'otp_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(max(1, (int) config('fixmoto.otp_expiration_minutes', 5))),
            'resend_available_at' => now()->addSeconds(max(1, (int) config('fixmoto.otp_resend_seconds', 60))),
        ]);

        return $code;
    }

    public function send(RegistrationOtp $challenge, string $code): void
    {
        $mailer = (string) config('mail.default', 'smtp');
        $mailerConfig = (array) config('mail.mailers.'.$mailer, []);
        $testing = app()->environment('testing');
        $unsafeMailer = $mailer === 'log'
            || ($mailer === 'array' && ! $testing)
            || ($mailerConfig['transport'] ?? null) === 'log'
            || (($mailerConfig['transport'] ?? null) === 'failover'
                && in_array('log', $mailerConfig['mailers'] ?? [], true));

        if ($unsafeMailer) {
            throw new RuntimeException('OTP email delivery requires a real mail transport; log and array mailers are disabled for OTP messages.');
        }

        if (! $testing && ($mailerConfig['host'] ?? null) === 'smtp.gmail.com') {
            $password = (string) ($mailerConfig['password'] ?? '');
            $username = (string) ($mailerConfig['username'] ?? '');
            if (! filter_var($username, FILTER_VALIDATE_EMAIL)
                || ! str_ends_with(strtolower($username), '@gmail.com')
                || strlen($password) !== 16) {
                Log::error('FixMoto Gmail SMTP configuration is incomplete or malformed.', [
                    'purpose' => $challenge->purpose,
                    'mailer' => $mailer,
                    'host' => 'smtp.gmail.com',
                    'port' => (int) ($mailerConfig['port'] ?? 0),
                    'username_configured' => $username !== '',
                    'app_password_format_valid' => strlen($password) === 16,
                ]);
                throw new RuntimeException('Gmail SMTP is missing a valid account and 16-character Google App Password.');
            }
        }

        try {
            Mail::to($challenge->email)->send(new RegistrationOtpMail(
                $code,
                5,
                $challenge->purpose,
            ));
        } catch (\Throwable $exception) {
            $text = strtolower($exception->getMessage());
            $failureType = str_contains($text, '535') || str_contains($text, 'auth')
                ? 'authentication_rejected'
                : (str_contains($text, 'connect') || str_contains($text, 'timeout')
                    ? 'connection_failed'
                    : 'transport_failed');

            Log::error('FixMoto OTP email delivery failed.', [
                'purpose' => $challenge->purpose,
                'mailer' => $mailer,
                'host' => (string) config('mail.mailers.smtp.host', ''),
                'port' => (int) config('mail.mailers.smtp.port', 0),
                'failure_type' => $failureType,
                'exception' => class_basename($exception),
            ]);

            throw $exception;
        }
    }

    public function deletePendingDocuments(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, 'mechanic-documents/')) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
