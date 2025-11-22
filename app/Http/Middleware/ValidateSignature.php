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
        if (!$request->hasHeader('X-Signature')) {
            return response()->json(
                ApiResponse::make(
                    false,
                    400,
                    'X-Signature header is required',
                    null
                )->toArray(),
                400
            );
        }

        return $next($request);
    }
}
