<?php

namespace App\Services;

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Models\DailyLog;
use App\Models\HealthConnectIgnoredWorkout;
use App\Models\HealthConnectSyncState;
use App\Models\MealEntry;
use App\Models\PendingMealAnalysisConfirmation;
use App\Models\Recipe;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use App\Models\WorkoutEntry;
use Illuminate\Support\Facades\DB;

class LocalAccountData
{
    public function __construct(
        private readonly MealReminderBridge $mealReminders,
        private readonly BodyMetricPhotoUploader $bodyMetricPhotos,
    ) {}

    public function wipe(): void
    {
        $this->mealReminders->sync((new AppPreference)->mealReminders());
        $this->bodyMetricPhotos->discardAll();

        DB::transaction(function (): void {
            MealEntry::query()->delete();
            Recipe::query()->delete();
            WorkoutEntry::query()->delete();
            BodyMetric::query()->delete();
            DailyLog::query()->delete();
            DailyGoal::query()->delete();
            BodyProfile::query()->delete();
            HealthConnectIgnoredWorkout::query()->delete();
            AppPreference::query()->delete();
            HealthConnectSyncState::query()->delete();
            PendingMealAnalysisConfirmation::query()->delete();
            SyncOutbox::query()->delete();
            SyncState::query()->delete();
        });
    }
}
