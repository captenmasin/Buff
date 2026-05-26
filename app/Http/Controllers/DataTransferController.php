<?php

namespace App\Http\Controllers;

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\DailyGoal;
use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DataTransferController extends Controller
{
    private const EXPORT_VERSION = 1;

    /**
     * @var array<class-string<Model>, array<int, string>>
     */
    private const IMPORTABLE_MODELS = [
        AppPreference::class => ['id', 'weight_unit', 'height_unit', 'created_at', 'updated_at'],
        DailyGoal::class => ['id', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'macro_calories', 'height_cm', 'target_weight_kg', 'target_body_fat_percent', 'created_at', 'updated_at'],
        FoodProduct::class => ['id', 'barcode', 'name', 'brand', 'image_url', 'serving_label', 'serving_quantity', 'serving_unit', 'package_label', 'package_quantity', 'package_unit', 'nutrition_unit', 'calories_per_100', 'protein_per_100', 'carbs_per_100', 'fat_per_100', 'raw_payload', 'fetched_at', 'created_at', 'updated_at'],
        MealEntry::class => ['id', 'date', 'meal_type', 'source_type', 'food_product_id', 'name', 'portion_quantity', 'portion_unit', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'created_at', 'updated_at'],
        BodyMetric::class => ['id', 'date', 'weight_kg', 'body_fat_percent', 'notes', 'created_at', 'updated_at'],
        WorkoutEntry::class => ['id', 'date', 'title', 'calories_burned', 'logged_at', 'source_type', 'external_id', 'external_source', 'external_source_package', 'started_at', 'ended_at', 'duration_seconds', 'imported_at', 'created_at', 'updated_at'],
    ];

    /**
     * @var array<class-string<Model>, string>
     */
    private const EXPORT_KEYS = [
        AppPreference::class => 'preferences',
        DailyGoal::class => 'daily_goals',
        FoodProduct::class => 'food_products',
        MealEntry::class => 'meal_entries',
        BodyMetric::class => 'body_metrics',
        WorkoutEntry::class => 'workout_entries',
    ];

    public function export(): Response
    {
        $payload = [
            'version' => self::EXPORT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'data' => collect(self::EXPORT_KEYS)
                ->mapWithKeys(fn (string $key, string $model): array => [
                    $key => $model::query()->oldest('id')->get()->toArray(),
                ])
                ->all(),
        ];

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="buff-export-'.now()->format('Y-m-d').'.json"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'export' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:5120'],
        ]);

        $payload = json_decode($validated['export']->get(), true);

        if (! is_array($payload) || ($payload['version'] ?? null) !== self::EXPORT_VERSION || ! is_array($payload['data'] ?? null)) {
            throw ValidationException::withMessages([
                'export' => 'Choose a valid Buff export file.',
            ]);
        }

        DB::transaction(function () use ($payload): void {
            foreach (self::IMPORTABLE_MODELS as $model => $columns) {
                $key = self::EXPORT_KEYS[$model];
                $rows = $payload['data'][$key] ?? [];

                if (! is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    /** @var Model $instance */
                    $instance = new $model;
                    $attributes = Arr::only($row, $columns);
                    $primaryKey = $instance->getKeyName();

                    if (! array_key_exists($primaryKey, $attributes)) {
                        continue;
                    }

                    Model::unguarded(fn (): Model => $model::query()->updateOrCreate(
                        [$primaryKey => $attributes[$primaryKey]],
                        Arr::except($attributes, [$primaryKey])
                    ));
                }
            }
        });

        return back()->with('message', 'Data imported.');
    }
}
