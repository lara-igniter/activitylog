<?php

namespace Laraigniter\Activitylog\Models;

use Elegant\Database\Model\Model;

class Activity extends Model
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

    public function scopeInLog(Model $query, string $logName): Model
    {
        return $query->where('log_name', $logName);
    }

    public function scopeCausedBy(Model $query, object $causer): Model
    {
        return $query
            ->where('causer_type', get_class($causer))
            ->where('causer_id', (int) $causer->id);
    }

    public function scopeForSubject(Model $query, object $subject, ?int $subjectId = null): Model
    {
        return $query
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subjectId ?? (int) $subject->id);
    }

    public function scopeForEvent(Model $query, string $event): Model
    {
        return $query->where('event', $event);
    }
}

