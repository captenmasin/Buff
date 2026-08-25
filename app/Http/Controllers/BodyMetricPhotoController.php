<?php

namespace App\Http\Controllers;

use App\BodyMetricPhotoPose;
use App\Models\BodyMetric;
use App\Services\BodyMetricPhotoUploader;
use App\Services\BuffApiClient;
use App\Services\PhotoDataUrlNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BodyMetricPhotoController extends Controller
{
    public function store(Request $request, BodyMetric $bodyMetric, BodyMetricPhotoUploader $uploader, PhotoDataUrlNormalizer $normalizer): JsonResponse
    {
        $normalizer->normalize($request);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
            'poses' => ['required', 'array', 'min:1', 'max:3'],
            'poses.*' => ['required', 'distinct', Rule::enum(BodyMetricPhotoPose::class)],
        ]);

        if (count($validated['photos']) !== count($validated['poses'])) {
            throw ValidationException::withMessages([
                'poses' => ['Each photo must have a pose.'],
            ]);
        }

        return $this->buffApiResponse($uploader->upload($bodyMetric, $validated['photos'], $validated['poses']));
    }

    public function index(BodyMetric $bodyMetric, BuffApiClient $api, BodyMetricPhotoUploader $uploader): JsonResponse
    {
        $result = $api->get("body-metrics/{$bodyMetric->id}/photos");
        $cloudPhotos = $result->successful() ? ($result->data['photos'] ?? []) : [];

        if (is_array($cloudPhotos) && $cloudPhotos !== []) {
            return $this->buffApiResponse($result);
        }

        $pending = $uploader->pendingPhotosFor($bodyMetric);

        if ($pending !== []) {
            return response()->json([
                'photos' => $pending,
                'pending' => true,
            ]);
        }

        return $this->buffApiResponse($result);
    }

    public function pending(BodyMetric $bodyMetric, string $pending, int $index, BodyMetricPhotoUploader $uploader): StreamedResponse
    {
        return $uploader->pendingPhotoResponse($bodyMetric, $pending, $index);
    }

    public function destroy(BodyMetric $bodyMetric, string $photo, BuffApiClient $api): JsonResponse
    {
        return $this->buffApiResponse($api->delete("body-metrics/{$bodyMetric->id}/photos/{$photo}"));
    }
}
