<?php

return [

    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 3.0),

    'prefetch_count' => (int) env('RABBITMQ_PREFETCH_COUNT', 1),
    'max_retries' => (int) env('RABBITMQ_MAX_RETRIES', 3),

    'default_exchange' => env('RABBITMQ_DEFAULT_EXCHANGE', ''),

    /*
    | Management HTTP API (used to sync dashboard-created queues into DB).
    */
    'management_url' => env('RABBITMQ_MANAGEMENT_URL', 'http://127.0.0.1:15672'),
    'management_user' => env('RABBITMQ_MANAGEMENT_USER', env('RABBITMQ_USER', 'guest')),
    'management_password' => env('RABBITMQ_MANAGEMENT_PASSWORD', env('RABBITMQ_PASSWORD', 'guest')),

];
