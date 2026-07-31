<?php

/*
|--------------------------------------------------------------------------
| Marketplace
|--------------------------------------------------------------------------
|
| Knobs for the digital marketplace: where release files live, how
| downloads are protected, and the licensing defaults applied when an
| order is fulfilled.
|
*/

return [

    /*
    | Disk holding release archives. Must NOT be publicly readable —
    | files are only ever streamed through an authorizing controller.
    | Swap to 's3' in production.
    */
    'releases_disk' => env('MARKETPLACE_RELEASES_DISK', 'local'),

    /*
    | Disk holding screenshots. Publicly readable by design.
    */
    'images_disk' => env('MARKETPLACE_IMAGES_DISK', 'public'),

    'downloads' => [
        // Lifetime of a signed download URL. Short by design: the URL
        // is handed straight to the browser after authorization.
        'link_ttl_minutes' => 15,

        // Per-license, per-day cap. Guards against a leaked license key
        // being used to mirror the files.
        'daily_limit' => 20,
    ],

    'licenses' => [
        // How many installs one purchase may activate.
        'activation_limit' => 1,

        // Free updates window, in months, from the purchase date. After
        // it lapses the license still works, but newer versions are not
        // downloadable (the EDD model).
        'updates_months' => 12,
    ],

];
