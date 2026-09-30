<?php

return [

    'api_key' => env('BULK_API_KEY'),

    'chunk_size' => (int) env('BULK_CHUNK_SIZE', 1000),

    'max_upload_kb' => (int) env('BULK_MAX_UPLOAD_KB', 51200),

    'input_disk' => env('BULK_INPUT_DISK', 'bulk'),

    'worm_disk' => env('BULK_WORM_DISK', 'minio'),

    'required_headers' => ['userid', 'phonenumber'],

];
