<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingBodyMetricPhotoUpload extends Model
{
    use HasUuids;

    protected $fillable = [
        'body_metric_id',
        'paths',
        'original_names',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'paths' => 'array',
            'original_names' => 'array',
            'attempts' => 'integer',
        ];
    }

    public function bodyMetric(): BelongsTo
    {
        return $this->belongsTo(BodyMetric::class);
    }
}
