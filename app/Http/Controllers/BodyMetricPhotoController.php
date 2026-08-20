<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Models\BodyMetric;
use App\Services\BodyMetricPhotoUploader;
use App\Services\BuffApiClient;
use App\Services\BuffApiResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class BodyMetricPhotoController extends Controller
{
    public function store(Request $request, BodyMetric $bodyMetric, BodyMetricPhotoUploader $uploader): JsonResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ]);

        return $this->response($uploader->upload($bodyMetric, $validated['photos']));
    }

    public function index(BodyMetric $bodyMetric, BuffApiClient $api): JsonResponse
    {
        return $this->response($api->get("body-metrics/{$bodyMetric->id}/photos"));
    }

    public function destroy(BodyMetric $bodyMetric, string $photo, BuffApiClient $api): JsonResponse
    {
        return $this->response($api->delete("body-metrics/{$bodyMetric->id}/photos/{$photo}"));
    }

    private function response(BuffApiResult $result): JsonResponse
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
