<?php

namespace App\Models;

class HealthConnectIgnoredWorkout extends SyncedModel
{
    protected $attributes = [
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
    ];

    protected $fillable = [
        'source_type',
        'external_id',
        'ignored_at',
    ];

    protected function casts(): array
    {
        return [
            'ignored_at' => 'datetime',
        ];
    }
}
