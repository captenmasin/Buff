<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkoutEntry extends Model
{
    use HasUuids;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_HEALTH_CONNECT = 'health_connect';

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
}
