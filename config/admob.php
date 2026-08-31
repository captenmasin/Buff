<?php

return [
    'enabled' => env('ADMOB_ENABLED', false),
    'test_mode' => env('ADMOB_TEST_MODE', true),

    'consent' => [
        'ump_enabled' => env('ADMOB_UMP_ENABLED', true),
        'att_enabled' => env('ADMOB_ATT_ENABLED', true),
        'ump_debug_geography' => env('ADMOB_UMP_DEBUG_GEOGRAPHY', 'DISABLED'),
    ],

    'slots' => [
        'banner' => [
            'app_shell' => [
                'android' => env('ADMOB_BANNER_APP_SHELL_ANDROID'),
                'ios' => env('ADMOB_BANNER_APP_SHELL_IOS'),
            ],
        ],
    ],

    'banner' => [
        'offset' => [
            'bottom' => (int) env('ADMOB_BANNER_OFFSET_BOTTOM', 0),
        ],
        'safe_area' => true,
    ],
];
