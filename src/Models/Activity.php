<?php

namespace Laraigniter\Activitylog\Models;

use App\Core\MY_Model;

class Activity extends MY_Model
{
    protected string $table = 'activity_log';

    protected array $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'attribute_changes',
        'properties',
        'created_at',
        'updated_at',
    ];

    protected array $casts = [
        'attribute_changes' => 'array',
        'properties' => 'array',
    ];

    public function scopeInLog(MY_Model $query, string $logName): MY_Model
    {
        return $query->where('log_name', $logName);
    }

    public function scopeCausedBy(MY_Model $query, object $causer): MY_Model
    {
        return $query
            ->where('causer_type', get_class($causer))
            ->where('causer_id', (int) $causer->id);
    }

    public function scopeForSubject(MY_Model $query, object $subject, ?int $subjectId = null): MY_Model
    {
        return $query
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subjectId ?? (int) $subject->id);
    }

    public function scopeForEvent(MY_Model $query, string $event): MY_Model
    {
        return $query->where('event', $event);
    }
}

