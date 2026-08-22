<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rapid Actions
    |--------------------------------------------------------------------------
    |
    | Flags a log entry when the same user performs more than `threshold`
    | actions within a rolling `window_minutes` window.
    |
    */

    'rapid_actions' => [
        'threshold' => 10,
        'window_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Off Hours
    |--------------------------------------------------------------------------
    |
    | Flags a log entry whose `created_at` falls outside this working-hours
    | window, in the application's timezone. Format: "H:i".
    |
    */

    'off_hours' => [
        'start' => '06:00',
        'end' => '22:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Flip
    |--------------------------------------------------------------------------
    |
    | Flags an `emptied` log entry when the same user stored a pallet in the
    | same cell within this many minutes beforehand.
    |
    */

    'quick_flip' => [
        'window_minutes' => 2,
    ],

];
