<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Services\BuffApiResult;
use App\Services\BuffSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function store(BuffSyncService $sync): JsonResponse
    {
        return $this->response($sync->sync());
    }

    public function resume(BuffSyncService $sync): JsonResponse
    {
        return $this->response($sync->resume());
    }

    private function response(BuffApiResult $result): JsonResponse
    {
        $status = match ($result->status) {
            BuffApiStatus::Success => 200,
            BuffApiStatus::Unauthenticated => 401,
            BuffApiStatus::EmailNotVerified, BuffApiStatus::Forbidden => 403,
            BuffApiStatus::ValidationFailed => 422,
            BuffApiStatus::RateLimited => 429,
            default => 503,
        };

        return response()->json([
            ...$result->data,
            'status' => $result->status->name,
            'message' => $result->message,
            'code' => $result->code,
        ], $status);
    }
}
