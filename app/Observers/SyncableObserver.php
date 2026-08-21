<?php

namespace App\Observers;

use App\Models\SyncedModel;
use App\Models\SyncOutbox;
use App\Services\BuffSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class SyncableObserver
{
    public function saved(SyncedModel $model): void
    {
        $definition = config('buff.sync_models')[$model::class] ?? null;

        if (! is_array($definition)) {
            return;
        }

        $payload = collect($definition['fields'])
            ->mapWithKeys(fn (string $field): array => [$field => $this->value($model, $field)])
            ->all();

        $this->store(
            $definition['type'],
            (string) $model->getKey(),
            $payload,
            $model->updated_at instanceof Carbon ? $model->updated_at : Date::now(),
            false,
        );
    }

    public function deleted(SyncedModel $model): void
    {
        $definition = config('buff.sync_models')[$model::class] ?? null;

        if (! is_array($definition)) {
            return;
        }

        $deletedAt = Date::now();

        if ($model->updated_at instanceof Carbon && ! $deletedAt->greaterThan($model->updated_at)) {
            $deletedAt = $model->updated_at->copy()->addMicrosecond();
        }

        $this->store($definition['type'], (string) $model->getKey(), null, $deletedAt, true);
    }

    private function value(SyncedModel $model, string $field): mixed
    {
        $value = $model->getAttribute($field);
        $cast = $model->getCasts()[$field] ?? null;

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Carbon && is_string($cast) && str_starts_with($cast, 'date') && ! str_starts_with($cast, 'datetime')) {
            return $value->toDateString();
        }

        if ($value instanceof Carbon) {
            return $this->timestamp($value);
        }

        return $value;
    }

    /** @param array<string, mixed>|null $payload */
    private function store(string $type, string $id, ?array $payload, Carbon $updatedAt, bool $deleted): void
    {
        SyncOutbox::query()->updateOrCreate(
            ['record_type' => $type, 'record_id' => $id],
            [
                'payload' => $payload,
                'client_updated_at' => $this->timestamp($updatedAt),
                'is_deleted' => $deleted,
            ],
        );

        defer(fn () => app(BuffSyncService::class)->sync(), 'buff-sync');
    }

    private function timestamp(Carbon $date): string
    {
        return $date->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
