<?php

it('ships a high-resolution PNG splash screen', function (string $filename): void {
    $imageSize = getimagesize(public_path($filename));

    expect($imageSize)->not->toBeFalse()
        ->and(array_slice($imageSize, 0, 3))->toBe([2160, 3840, IMAGETYPE_PNG]);
})->with([
    'light mode' => 'splash.png',
    'dark mode' => 'splash-dark.png',
]);
