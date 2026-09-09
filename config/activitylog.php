<?php

$config = [
    /* Set to false to disable all activity persistence. */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /* Activities older than this many days can be removed with CleanActivityLogAction. */
    'clean_after_days' => 365,

    /* Used when activity() receives no log name. */
    'default_log_name' => 'default',

    /* The Model implementation used to persist activities. */
    'activity_model' => Laraigniter\Activitylog\Models\Activity::class,

    /* Attributes never persisted by LogsActivity, including model-specific logExcept values. */
    'default_except_attributes' => [
        'password',
        'remember_token',
    ],

    /* The model class represented by an authenticated Ion Auth user row. */
    'causer_model' => App\Models\User::class,

    /* Resolve the authenticated actor. Return an object with an id property or null. */
    'causer_resolver' => static function () {
        $ionAuth = app('ion_auth');

        return $ionAuth->logged_in() ? $ionAuth->user()->row() : null;
    },
];
