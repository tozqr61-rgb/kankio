<?php

return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [

        'reverb' => [
            'host'     => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port'     => env('REVERB_SERVER_PORT', 8080),
            'path'     => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options'  => [
                'tls' => [],
            ],
            'max_request_size'          => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'pulse_ingest_interval'     => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
            'scaling' => [
                'enabled' => false,
            ],
        ],

    ],

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key'             => env('REVERB_APP_KEY'),
                'secret'          => env('REVERB_APP_SECRET'),
                'app_id'          => env('REVERB_APP_ID'),
                'options'         => [
                    'host'   => env('REVERB_HOST', 'localhost'),
                    'port'   => env('REVERB_PORT', 8080),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => false,
                ],
                'allowed_origins'    => ['*'],
                'ping_interval'      => 60,
                'activity_timeout'   => 30,
                'max_connections'    => null,
                'max_message_size'   => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],

    ],

];
