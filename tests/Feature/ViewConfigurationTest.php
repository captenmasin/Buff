<?php

it('uses a stable compiled view path for copied native builds', function (): void {
    $compiledViewPath = config('view.compiled');

    expect($compiledViewPath)->toBe(storage_path('framework/views'))
        ->and($compiledViewPath)->not->toBeFalse()
        ->and($compiledViewPath)->not->toBe('');
});
