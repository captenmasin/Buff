<?php

namespace App\Http\Controllers;

use App\Http\Requests\FollowUpMealAnalysisRequest;
use App\Models\MealEntry;
use App\Services\BuffApiClient;
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

        return $this->buffApiResponse($api->analyzeMeal($validated['photos'], $validated['note'] ?? null));
    }

    public function destroy(string $analysis, BuffApiClient $api): JsonResponse
    {
        return $this->buffApiResponse($api->delete("meal-analyses/{$analysis}"));
    }

    public function followUp(FollowUpMealAnalysisRequest $request, string $analysis, BuffApiClient $api): JsonResponse
    {
        return $this->buffApiResponse($api->post("meal-analyses/{$analysis}/follow-up", $request->validated()));
    }

    public function photos(MealEntry $mealEntry, BuffApiClient $api): JsonResponse
    {
        return $this->buffApiResponse($api->get("meals/{$mealEntry->id}/photos"));
    }
}
