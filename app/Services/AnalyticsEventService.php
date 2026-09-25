<?php

namespace App\Services;

use App\Models\PendingAnalyticsEvent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

class AnalyticsEventService
{
    public function __construct(
        private readonly BuffApiClient $api,
        private readonly BuffCredentialStore $credentials,
    ) {}

    public function record(string $name): void
    {
        $accountId = $this->accountId();

        if ($accountId === null) {
            return;
        }

        try {
            PendingAnalyticsEvent::query()->create([
                'id' => (string) Str::uuid(),
                'account_id' => $accountId,
                'name' => $name,
                'occurred_at' => Date::now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        defer(fn () => app(BuffSyncService::class)->sync(), 'buff-sync');
    }

    public function flush(): void
    {
        $accountId = $this->accountId();

        if ($accountId === null) {
            return;
        }

        try {
            do {
                $events = PendingAnalyticsEvent::query()
                    ->where('account_id', $accountId)
                    ->oldest('occurred_at')
                    ->limit(50)
                    ->get();

                if ($events->isEmpty()) {
                    return;
                }

                $result = $this->api->post('analytics/events', [
                    'events' => $events->map(fn (PendingAnalyticsEvent $event): array => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'occurred_at' => $event->occurred_at->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                    ])->all(),
                ]);

                if (! $result->successful() || ($result->data['accepted'] ?? null) !== $events->count()) {
                    return;
                }

                PendingAnalyticsEvent::query()
                    ->where('account_id', $accountId)
                    ->whereIn('id', $events->modelKeys())
                    ->delete();
            } while ($events->count() === 50);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function accountId(): ?string
    {
        $accountId = $this->credentials->account()['id'] ?? null;

        return $this->credentials->token() !== null && is_string($accountId)
            ? $accountId
            : null;
    }
}
