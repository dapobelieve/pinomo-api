<?php

return [
    'storage' => [
        'driver' => env('KYC_STORAGE_DRIVER', 's3'),
        'disk' => env('KYC_STORAGE_DISK', 'kyc'),
        'path' => env('KYC_STORAGE_PATH', 'documents'),
    ],
    'queue' => [
        'connection' => env('KYC_QUEUE_CONNECTION', 'database'),
        'queue' => env('KYC_QUEUE_NAME', 'kyc-processing'),
    ],
];