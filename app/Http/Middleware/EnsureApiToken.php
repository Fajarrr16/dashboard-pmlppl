<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    /**
     * Validate the Bearer token sent by Google Apps Script.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.kartu_kendali.token');
        $providedToken = $request->bearerToken();

        if (
            ! is_string($token)
            || $token === ''
            || ! is_string($providedToken)
            || ! hash_equals($token, $providedToken)
        ) {
            return response()->json([
                'message' => 'Unauthorized. Token API tidak valid.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
