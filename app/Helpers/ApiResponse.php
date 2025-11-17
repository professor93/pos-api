<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Create a standardized API response
     *
     * @param bool $ok Whether the request was successful
     * @param int $code HTTP status code
     * @param string $message Response message
     * @param mixed $result Optional result data
     * @param mixed $meta Optional metadata
     * @return JsonResponse
     */
    public static function make(
        bool $ok,
        int $code,
        string $message,
        mixed $result = null,
        mixed $meta = null
    ): JsonResponse {
        $response = [
            'ok' => $ok,
            'code' => $code,
            'message' => $message,
        ];

        if ($result !== null) {
            $response['result'] = $result;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }
}
