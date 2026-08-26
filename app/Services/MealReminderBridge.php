<?php

namespace App\Services;

use Closure;
use Throwable;

class MealReminderBridge
{
    public function __construct(private ?Closure $nativeCaller = null) {}

    public function sync(array $reminders): array
    {
        if (! $this->nativeCaller && (
            ! function_exists('nativephp_call')
            || ! function_exists('nativephp_can')
            || ! nativephp_can('BackgroundTasks.RegisterMealReminders')
        )) {
            return ['status' => 'unsupported'];
        }

        try {
            $payload = json_encode(['reminders' => $reminders], JSON_THROW_ON_ERROR);
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
}
