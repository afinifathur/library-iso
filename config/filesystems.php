<?php

return [

    // Disk default (tidak masalah tetap 'local' karena kita panggil disk 'documents' secara eksplisit)
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        // Disk local umum (private)
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        // Disk khusus dokumen (private) - dipakai oleh DocumentController
        'documents' => [
            'driver'     => 'local',
            'root'       => storage_path('app/documents'),
            'visibility' => 'private',
            'throw'      => false,
        ],

        // Disk public untuk file statis (bisa diakses via /storage)
        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        // Contoh S3 (opsional)
        's3' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url'    => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'  => false,
        ],
    ],

    // Symlink hanya untuk disk public
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
