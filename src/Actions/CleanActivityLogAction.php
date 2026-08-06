<?php

namespace Laraigniter\Activitylog\Actions;

use Laraigniter\Activitylog\Support\ActivitylogConfig;

final class CleanActivityLogAction
{
    /** @return int|array<int, int>|false */
    public function execute(?int $maxAgeInDays = null, ?string $logName = null, bool $deleteAll = false)
    {
        $activityClass = ActivitylogConfig::get('activity_model', \Laraigniter\Activitylog\Models\Activity::class);
        $activity = new $activityClass();

        if (! $deleteAll) {
            $maxAgeInDays = $maxAgeInDays ?? (int) ActivitylogConfig::get('clean_after_days', 365);
            $activity->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$maxAgeInDays} days")));
        } else {
            // CodeIgniter refuses DELETE statements without a WHERE clause. Every
            // persisted activity has a primary key, so this intentionally matches
            // all activity rows while retaining the query-builder safety guard.
            $activity->whereRaw($activity->getKeyName() . ' IS NOT NULL');
        }

        if ($logName !== null) {
            $activity->inLog($logName);
        }

        return $activity->delete();
    }
}
