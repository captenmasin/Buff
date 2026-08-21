<?php

namespace App\Models;

use App\ActivityLevel;
use App\Sex;
use Illuminate\Validation\Rule;

class BodyProfile extends SyncedModel
{
    public const ID = '00000000-0000-0000-0000-000000000002';

    protected $fillable = [
        'id',
        'height_cm',
        'age',
        'sex',
        'activity_level',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:2',
            'age' => 'integer',
            'sex' => Sex::class,
            'activity_level' => ActivityLevel::class,
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => self::ID]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $present = false): array
    {
        $presence = $present ? ['present'] : [];

        return [
            'height_cm' => [...$presence, 'nullable', 'numeric', 'min:50', 'max:260'],
            'age' => [...$presence, 'nullable', 'integer', 'min:13', 'max:120'],
            'sex' => [...$presence, 'nullable', Rule::enum(Sex::class)],
            'activity_level' => [...$presence, 'nullable', Rule::enum(ActivityLevel::class)],
        ];
    }

    /**
     * @return array{height_cm: float|null, age: int|null, sex: string|null, activity_level: string|null}
     */
    public function toPayload(): array
    {
        return [
            'height_cm' => $this->height_cm !== null ? (float) $this->height_cm : null,
            'age' => $this->age,
            'sex' => $this->sex?->value,
            'activity_level' => $this->activity_level?->value,
        ];
    }
}
