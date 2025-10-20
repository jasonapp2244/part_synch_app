<?php

return [

    /*
    |----------------------------------------------------------------------
    | Default Filesystem Disk
    |----------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |----------------------------------------------------------------------
    | Filesystem Disks
    |----------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),  // Directory where the files are stored
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),  // The public directory for storage
            'url' => env('APP_URL') . '/storage',  // URL for the storage
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |----------------------------------------------------------------------
    | Symbolic Links
    |----------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        // Create a symbolic link to make files accessible from /storage/assets
        // public_path('storage/') => storage_path('app/private/'),
        // public_path('storaget/asses')=> storage_path('app/private/assets'),
        // public_path('storage/app/public/assets') => storage_path('app/public/assets'),
        public_path('storage') => storage_path('app/public'),
        // my symblick link create
        // ERROR  The [C:\xampp\htdocs\part_synch_mob_app\public\storage] link already exists.  
    ],
];


