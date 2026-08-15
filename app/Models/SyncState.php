<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SyncState extends Model
{
    protected $fillable = [
        'device_id',
        'account_id',
        'cursor',
        'last_attempted_at',
        'last_succeeded_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'cursor' => 'integer',
            'last_attempted_at' => 'datetime',
            'last_succeeded_at' => 'datetime',
        ];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    public static function current(?string $accountId = null): self
    {
        $state = self::query()->firstOrCreate([], [
            'device_id' => (string) Str::uuid(),
            'account_id' => $accountId,
        ]);

        if ($state->account_id === null && $accountId !== null) {
            $state->update(['account_id' => $accountId]);
        }

        return $state;
    }
}
