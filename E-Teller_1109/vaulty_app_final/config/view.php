<?php
/* Tujuan file: Konfigurasi ini mengatur perilaku komponen view pada aplikasi Laravel. */


return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    */

    'compiled' => storage_path('framework/views'),

    /*
    |--------------------------------------------------------------------------
    | View Cache Options
    |--------------------------------------------------------------------------
    */

    'cache' => true,

    'check_cache_timestamps' => true,

    'compiled_extension' => 'php',

    'relative_hash' => false,
];
