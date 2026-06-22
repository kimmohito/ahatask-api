<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most applications have one or more view paths where view templates are
    | stored. These paths are searched in order for templates.
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
    | This option determines where all compiled Blade templates are stored.
    | A concrete path avoids runtime fallback to the system temp directory.
    |
    */

    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];
