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
        $pending = $uploader->pendingPhotosFor($bodyMetric);

        if ($pending === []) {
            return $this->buffApiResponse($result);
        }

        $photos = collect([...$pending, ...(is_array($cloudPhotos) ? $cloudPhotos : [])])
            ->filter(fn (mixed $photo): bool => is_array($photo))
            ->unique(fn (array $photo): string => (string) (($photo['pose'] ?? null) ?: ($photo['id'] ?? json_encode($photo))))
            ->sortBy(fn (array $photo): int => BodyMetricPhotoPose::sortKey($photo['pose'] ?? null))
            ->take(3)
            ->values()
            ->all();

        return response()->json([
            'photos' => $photos,
            'pending' => true,
        ]);
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
