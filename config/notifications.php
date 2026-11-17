<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queued Push Delivery
    |--------------------------------------------------------------------------
    |
    | When enabled, the outbound Expo push call is handed to a queue worker
    | instead of running inside the request that created the notification.
    | The in-app row is ALWAYS written synchronously (it is a cheap DB write);
    | only the network round-trip moves off-request.
    |
    | Defaults to false because it requires a running queue worker
    | (QUEUE_CONNECTION=database + `php artisan queue:work`). Leaving it false
    | keeps the current inline behaviour until a worker is deployed.
    |
    */

    'queue_push' => env('NOTIFICATIONS_QUEUE_PUSH', false),

];
