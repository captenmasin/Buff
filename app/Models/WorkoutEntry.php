<?php

namespace App\Models;

class WorkoutEntry extends SyncedModel
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_HEALTH_CONNECT = 'health_connect';

    public const SOURCE_APPLE_HEALTH = 'apple_health';

    protected $fillable = [
        'date',
        'title',
        'calories_burned',
        'logged_at',
        'source_type',
        'external_id',
        'external_source',
        'external_source_package',
        'started_at',
        'ended_at',
        'duration_seconds',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calories_burned' => 'integer',
            'logged_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function isHealthConnect(): bool
    {
        return $this->source_type === self::SOURCE_HEALTH_CONNECT;
    }

    public function isAppleHealth(): bool
    {
        return $this->source_type === self::SOURCE_APPLE_HEALTH;
    }

    public function isImportedHealth(): bool
    {
        return $this->isHealthConnect() || $this->isAppleHealth();
    }
}
