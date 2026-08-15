<?php

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\DailyGoal;
use App\Models\DailyLog;
use App\Models\HealthConnectIgnoredWorkout;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;

return [
    'api_url' => env('BUFF_API_URL'),

    'http' => [
        'connect_timeout' => 5,
        'timeout' => 20,
        'meal_analysis_timeout' => 75,
    ],

    'sync_models' => [
        DailyGoal::class => [
            'type' => 'daily_goals',
            'fields' => ['calories', 'protein_g', 'carbs_g', 'fat_g', 'macro_calories', 'height_cm', 'target_weight_kg', 'target_body_fat_percent'],
        ],
        DailyLog::class => [
            'type' => 'daily_logs',
            'fields' => ['date', 'burned_calories'],
        ],
        MealEntry::class => [
            'type' => 'meal_entries',
            'fields' => ['date', 'meal_type', 'source_type', 'food_product_id', 'name', 'portion_quantity', 'portion_unit', 'calories', 'protein_g', 'carbs_g', 'fat_g'],
        ],
        BodyMetric::class => [
            'type' => 'body_metrics',
            'fields' => ['date', 'weight_kg', 'body_fat_percent', 'notes'],
        ],
        WorkoutEntry::class => [
            'type' => 'workout_entries',
            'fields' => ['date', 'title', 'calories_burned', 'logged_at', 'source_type', 'external_id', 'external_source', 'external_source_package', 'started_at', 'ended_at', 'duration_seconds', 'imported_at'],
        ],
        HealthConnectIgnoredWorkout::class => [
            'type' => 'health_connect_ignored_workouts',
            'fields' => ['external_id', 'ignored_at'],
        ],
        AppPreference::class => [
            'type' => 'app_preferences',
            'fields' => ['weight_unit', 'height_unit', 'meal_reminders'],
        ],
    ],
];
