<?php

namespace App\Services;

use Closure;
use Throwable;

class DeviceNotificationBridge
{
    public function __construct(private ?Closure $nativeCaller = null) {}

    /** @return array{status: string} */
    public function send(string $title, string $body, ?string $url = null): array
    {
        if (! $this->nativeCaller && (
            ! function_exists('nativephp_call')
            || ! function_exists('nativephp_can')
            || ! nativephp_can('BackgroundTasks.SendNotification')
        )) {
            return ['status' => 'unsupported'];
        }

        try {
            $parameters = array_filter([
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ], fn (mixed $value): bool => $value !== null);
            $payload = json_encode($parameters, JSON_THROW_ON_ERROR);
            $result = $this->nativeCaller
                ? ($this->nativeCaller)('BackgroundTasks.SendNotification', $payload)
                : nativephp_call('BackgroundTasks.SendNotification', $payload);
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
