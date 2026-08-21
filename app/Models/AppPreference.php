<?php

namespace App\Models;

class AppPreference extends SyncedModel
{
    public const ID = '00000000-0000-0000-0000-000000000001';

    public const WEIGHT_UNITS = ['kg', 'lb'];

    public const HEIGHT_UNITS = ['cm', 'in'];

    public const EAT_BACK = ['all', 'half', 'none'];

    private const DEFAULT_MEAL_REMINDERS = [
        'breakfast' => ['enabled' => false, 'time' => '08:00'],
        'lunch' => ['enabled' => false, 'time' => '12:00'],
        'dinner' => ['enabled' => false, 'time' => '18:00'],
    ];

    protected $fillable = [
        'id',
        'weight_unit',
        'height_unit',
        'meal_reminders',
        'eat_back',
    ];

    protected $attributes = [
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
        'eat_back' => 'all',
    ];

    protected function casts(): array
    {
        return [
            'meal_reminders' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => self::ID]);
    }

    public function mealReminders(): array
    {
        $storedReminders = is_array($this->meal_reminders) ? $this->meal_reminders : [];
        $reminders = [];

        foreach (self::DEFAULT_MEAL_REMINDERS as $meal => $defaults) {
            $stored = is_array($storedReminders[$meal] ?? null) ? $storedReminders[$meal] : [];
            $enabled = $stored['enabled'] ?? null;
            $time = $stored['time'] ?? null;

            $reminders[$meal] = [
                'enabled' => is_bool($enabled) ? $enabled : $defaults['enabled'],
                'time' => is_string($time) && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time) === 1
                    ? $time
                    : $defaults['time'],
            ];
        }

        return $reminders;
    }

    public function eatBack(): string
    {
        return in_array($this->eat_back, self::EAT_BACK, true) ? $this->eat_back : 'all';
    }
}
