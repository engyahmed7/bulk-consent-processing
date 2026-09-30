<?php

return [

    'disk' => env('BULK_WORM_DISK', 'minio'),

    'lock_mode' => env('WORM_LOCK_MODE', 'GOVERNANCE'),

    'retention_days' => (int) env('WORM_RETENTION_DAYS', 365),

];
