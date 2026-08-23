<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Http\Requests\FollowUpMealAnalysisRequest;
use App\Models\MealEntry;
use App\Services\BuffApiClient;
use App\Services\BuffApiResult;
use App\Services\PhotoDataUrlNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class MealAnalysisController extends Controller
{
    public function store(Request $request, BuffApiClient $api, PhotoDataUrlNormalizer $normalizer): JsonResponse
    {
        $normalizer->normalize($request);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->response($api->analyzeMeal($validated['photos'], $validated['note'] ?? null));
    }

    public function destroy(string $analysis, BuffApiClient $api): JsonResponse
    {
        return $this->response($api->delete("meal-analyses/{$analysis}"));
    }

    public function followUp(FollowUpMealAnalysisRequest $request, string $analysis, BuffApiClient $api): JsonResponse
    {
        return $this->response($api->post("meal-analyses/{$analysis}/follow-up", $request->validated()));
    }

    public function photos(MealEntry $mealEntry, BuffApiClient $api): JsonResponse
    {
        return $this->response($api->get("meals/{$mealEntry->id}/photos"));
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
            'message' => $result->message,
            'code' => $result->code,
            'errors' => $result->errors,
        ], $status);
    }
}
