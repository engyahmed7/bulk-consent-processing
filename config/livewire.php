<?php

return [
    'temporary_file_upload' => [
        'disk' => 'minio',
        'rules' => 'file|max:'.(int) env('BULK_MAX_UPLOAD_KB', 51200),
    ],
];
