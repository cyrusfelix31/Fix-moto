<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('registration-otp-resend', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $challengeId = (string) $request->input('challenge_id', '');
                    $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

                    $response = redirect()
                        ->route('register')
                        ->withErrors([
                            'otp' => 'Too many resend requests. Please wait about one minute, then try again.',
                        ]);

                    if ($challengeId !== '') {
                        $response->with('otp_challenge', $challengeId)
                            ->with('otp_resend_seconds', $retryAfter)
                            ->withInput(['challenge_id' => $challengeId]);
                    }

                    return $response;
                });
        });
    }
}
