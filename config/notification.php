<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limit Per User (notifications/hour)
    |--------------------------------------------------------------------------
    */
    'rate_limit_per_hour' => env('NOTIFICATIONS_RATE_LIMIT_PER_HOUR', 10),

    /*
    |--------------------------------------------------------------------------
    | Redis Channel Name for Pub/Sub
    |--------------------------------------------------------------------------
    */
    'redis_channel' => env('NOTIFICATIONS_REDIS_CHANNEL', 'notifications'),
];
