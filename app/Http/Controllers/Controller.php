<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Services\BuffApiResult;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function buffApiResponse(BuffApiResult $result): JsonResponse
    {
        $status = match ($result->status) {
            BuffApiStatus::Success => $result->httpStatus === 204 ? 204 : 200,
            BuffApiStatus::Unauthenticated => 401,
            BuffApiStatus::EmailNotVerified, BuffApiStatus::Forbidden => 403,
            BuffApiStatus::ValidationFailed => 422,
            BuffApiStatus::RateLimited => 429,
            default => $result->httpStatus ?? 503,
        };

        return response()->json([
            ...$result->data,
            'message' => $result->message ?? ($result->data['message'] ?? null),
            'code' => $result->code,
            'errors' => $result->errors,
        ], $status);
    }
}
