<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMechanicVerified
{
    public function handle(
        Request $request,
        Closure $next
    ): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->role === 'mechanic',
            403
        );

        abort_if(
            $user->is_suspended,
            403
        );

        $profile = $user->mechanicProfile;

        if (

            !$profile

            || !$profile->is_verified

            || $profile->verification_status !== 'approved'

        ) {

            if ($request->expectsJson()) {

                return response()->json([

                    'message' =>
                        'Your mechanic shop is not verified.',

                ], 403);
            }

            return redirect()->route(
                'mechanic.verification.status'
            );
        }

        return $next($request);
    }
}
