<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most of the time the views for your application are stored in the
    | resources/views directory, however you may configure multiple
    | paths to store your views, and Laravel will consider all of them.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade views will be stored.
    | Typically, this is within the storage/framework/views directory.
    | However, you may change this if you want to store them elsewhere.
    |
    */

    'compiled' => realpath(storage_path('framework/views')) ?: storage_path('framework/views'),

];
