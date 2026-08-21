<?php

namespace App;

enum BodyMetricPhotoPose: string
{
    case Front = 'front';
    case Side = 'side';
    case Back = 'back';

    public function sortOrder(): int
    {
        return match ($this) {
            self::Front => 0,
            self::Side => 1,
            self::Back => 2,
        };
    }

    public static function sortKey(?string $pose): int
    {
        return self::tryFrom((string) $pose)?->sortOrder() ?? 99;
    }
}
