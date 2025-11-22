<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ApiResponse;

class ValidateSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip signature validation in local environment
        if (app()->environment('local')) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');

        if (!$signature) {
            return response()->json(
                ApiResponse::make(
                    false,
                    401,
                    'X-Signature header is required',
                    null
                )->toArray(),
                401
            );
        }

        // Verify signature using HMAC
        $secret = config('app.api_secret');
        if (!$secret) {
            logger()->error('API secret not configured');
            return response()->json(
                ApiResponse::make(
                    false,
                    500,
                    'Server configuration error',
                    null
                )->toArray(),
                500
            );
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(
                ApiResponse::make(
                    false,
                    401,
                    'Invalid signature',
                    null
                )->toArray(),
                401
            );
        }

        return $next($request);
    }
}
