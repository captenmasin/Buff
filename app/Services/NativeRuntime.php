<?php

namespace App\Services;

use ReflectionFunction;

class NativeRuntime
{
    public static function available(): bool
    {
        return function_exists('nativephp_call') && (
            (new ReflectionFunction('nativephp_call'))->isInternal() || getenv('JUMP_BRIDGE_PORT') !== false
        );
    }
}
