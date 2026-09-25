<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingAnalyticsEvent extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'account_id',
        'name',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }
}
