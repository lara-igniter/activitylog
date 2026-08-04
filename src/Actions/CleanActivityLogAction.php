<?php

namespace Laraigniter\Activitylog\Actions;

use Laraigniter\Activitylog\Support\ActivitylogConfig;

final class CleanActivityLogAction
{
    /** @return int|array<int, int>|false */
    public function execute(?int $maxAgeInDays = null, ?string $logName = null)
    {
        $maxAgeInDays = $maxAgeInDays ?? (int) ActivitylogConfig::get('clean_after_days', 365);
        $activityClass = ActivitylogConfig::get('activity_model', \Laraigniter\Activitylog\Models\Activity::class);
        $activity = new $activityClass();
        $activity->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$maxAgeInDays} days")));

        if ($logName !== null) {
            $activity->inLog($logName);
        }

        return $activity->delete();
    }
}

