<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sendman Notification URL
    |--------------------------------------------------------------------------
    |
    | This URL will be used as the default webhook notification endpoint when
    | no explicit webhook URL is provided in API requests. This allows for
    | centralized notification handling while still allowing per-request
    | webhook URL overrides.
    |
    */

    'notification_url' => env('SENDMAN_NOTIFICATION_URL'),
];