<?php

use App\Models\BodyProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('shares only the advertising age band', function (?int $age, string $expected): void {
    if ($age !== null) {
        BodyProfile::query()->create([
            'id' => BodyProfile::ID,
            'age' => $age,
        ]);
    }

    $this->get('/account/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buff.ad_audience', $expected)
            ->missing('buff.age'));
})->with([
    'missing age is teen-safe' => [null, 'teen'],
    'age 17 is teen-safe' => [17, 'teen'],
    'age 18 is adult' => [18, 'adult'],
]);
