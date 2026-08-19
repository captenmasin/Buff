<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthConnectSyncState extends Model
{
    public const SOURCE_TYPE = 'health_connect';

    public const APPLE_HEALTH_SOURCE_TYPE = 'apple_health';

    protected $primaryKey = 'source_type';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'source_type',
        'last_synced_at',
        'last_successful_sync_at',
        'last_status',
        'last_error',
        'synced_records',
        'deleted_records',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'synced_records' => 'integer',
            'deleted_records' => 'integer',
        ];
    }

    public static function healthConnect(): self
    {
        return self::query()->firstOrCreate([
            'source_type' => self::SOURCE_TYPE,
        ]);
    }

    public static function appleHealth(): self
    {
        return self::query()->firstOrCreate([
            'source_type' => self::APPLE_HEALTH_SOURCE_TYPE,
        ]);
    }
}
