<?php

use Laraigniter\Activitylog\Support\ActivityLogger;

if (! function_exists('activity')) {
    function activity(?string $logName = null): ActivityLogger
    {
        /** @var ActivityLogger $logger */
        $logger = clone app('activitylog');

        return $logName === null ? $logger : $logger->useLog($logName);
    }
}
