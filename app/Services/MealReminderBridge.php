<?php

namespace App\Services;

use App\Models\AppPreference;
use App\Models\MealEntry;
use Closure;
use Native\Mobile\Facades\System;
use Throwable;

class MealReminderBridge
{
    public function __construct(private ?Closure $nativeCaller = null) {}

    public function refreshOnIos(): void
    {
        if (! $this->isIos()) {
            return;
        }

        $reminders = AppPreference::query()->find(AppPreference::ID)?->mealReminders();

        if ($reminders !== null && in_array(true, array_column($reminders, 'enabled'), true)) {
            $this->sync($reminders, requestPermission: false);
        }
    }

    public function sync(array $reminders, bool $requestPermission = true): array
    {
        if (! $this->nativeCaller && (
            ! NativeRuntime::available()
            || ! function_exists('nativephp_can')
            || ! nativephp_can('BackgroundTasks.RegisterMealReminders')
        )) {
            return ['status' => 'unsupported'];
        }

        try {
            $payload = ['reminders' => $reminders];

            if ($this->isIos()) {
                $enabled = array_keys(array_filter($reminders, fn (array $delivery): bool => $delivery['enabled']));
                $payload['logged_meals'] = $enabled === [] ? [] : MealEntry::query()
                    ->whereIn('meal_type', $enabled)
                    ->where('date', '>=', today()->subDay()->toDateString())
                    ->where('date', '<', today()->addDays(21)->toDateString())
                    ->select('date', 'meal_type')
                    ->distinct()
                    ->get()
                    ->map(fn (MealEntry $entry): string => $entry->date->toDateString().':'.$entry->meal_type)
                    ->all();
                $payload['request_permission'] = $requestPermission;
            }

            $payload = json_encode($payload, JSON_THROW_ON_ERROR);
            $result = $this->nativeCaller
                ? ($this->nativeCaller)('BackgroundTasks.RegisterMealReminders', $payload)
                : nativephp_call('BackgroundTasks.RegisterMealReminders', $payload);
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'error'];
        }

        if (! $result) {
            return ['status' => 'error'];
        }

        $decoded = json_decode($result, true);

        if (! is_array($decoded) || ! is_string($decoded['status'] ?? null)) {
            return ['status' => 'error'];
        }

        return $decoded;
    }

    private function isIos(): bool
    {
        if (! $this->nativeCaller && (! NativeRuntime::available() || ! function_exists('nativephp_can') || ! nativephp_can('BackgroundTasks.RegisterMealReminders'))) {
            return false;
        }

        try {
            return getenv('NATIVEPHP_PLATFORM') === 'ios' || System::isIos();
        } catch (Throwable) {
            return false;
        }
    }
}
