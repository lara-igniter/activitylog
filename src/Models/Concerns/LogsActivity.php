<?php

namespace Laraigniter\Activitylog\Models\Concerns;

use Laraigniter\Activitylog\Enums\ActivityEvent;
use Laraigniter\Activitylog\Support\ActivitylogConfig;
use Laraigniter\Activitylog\Support\LogOptions;

trait LogsActivity
{
    public bool $enableLoggingModelsEvents = true;

    private ?LogOptions $activitylogOptions = null;
    /** @var array<string, mixed> */
    private array $activitylogOriginalAttributes = [];

    protected function initializeLogsActivity(): void
    {
        foreach ([ActivityEvent::CREATED, ActivityEvent::UPDATING, ActivityEvent::UPDATED, ActivityEvent::DELETED, ActivityEvent::RESTORED] as $event) {
            $this->{$event}[] = 'activitylog' . ucfirst($event);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function disableLogging(): self
    {
        $this->enableLoggingModelsEvents = false;

        return $this;
    }

    public function enableLogging(): self
    {
        $this->enableLoggingModelsEvents = true;

        return $this;
    }

    /** @param object $model */
    protected function activitylogCreated($model)
    {
        $this->recordActivity(ActivityEvent::CREATED, $model, $this->objectToArray($model));

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    protected function activitylogUpdating(array $attributes): array
    {
        $this->activitylogOriginalAttributes = $this->getOriginalAttributes();

        return $attributes;
    }

    /** @param object $model */
    protected function activitylogUpdated($model)
    {
        $this->recordActivity(
            ActivityEvent::UPDATED,
            $model,
            $this->objectToArray($model),
            $this->activitylogOriginalAttributes
        );
        $this->activitylogOriginalAttributes = [];

        return $model;
    }

    /** @param array<string, mixed> $models */
    protected function activitylogDeleted(array $models): array
    {
        foreach ($models as $model) {
            if (is_array($model) || is_object($model)) {
                $this->recordActivity(ActivityEvent::DELETED, $model, $this->objectToArray($model));
            }
        }

        return $models;
    }

    /** @param mixed $affected */
    protected function activitylogRestored($affected)
    {
        if (is_object($affected) || is_array($affected)) {
            $this->recordActivity(ActivityEvent::RESTORED, $affected, $this->objectToArray($affected));
        }

        return $affected;
    }

    /** @param object|array<string, mixed> $subject */
    private function recordActivity(string $event, $subject, array $attributes, array $oldAttributes = []): void
    {
        $options = $this->activitylogOptions = $this->getActivitylogOptions();

        if (! $this->enableLoggingModelsEvents || app('activitylog.status')->disabled()) {
            return;
        }

        $changes = $this->changesForLogging($attributes, $options);
        $oldChanges = $this->changesForLogging($oldAttributes, $options);

        if ($event === ActivityEvent::UPDATED && $options->logOnlyDirty) {
            [$changes, $oldChanges] = $this->onlyDirtyChanges($changes, $oldChanges);
        }

        if (! $options->logEmptyChanges && $changes === []) {
            return;
        }

        if ($options->dontLogIfAttributesChangedOnly !== []
            && array_diff(array_keys($changes), $options->dontLogIfAttributesChangedOnly) === []) {
            return;
        }

        $description = $options->descriptionForEvent
            ? ($options->descriptionForEvent)($event)
            : $event;

        if ($description === '') {
            return;
        }

        $activity = activity($options->logName ?? ActivitylogConfig::get('default_log_name', 'default'))
            ->event($event)
            ->performedOn($this, $this->subjectId($subject))
            ->withChanges(['attributes' => $changes]);

        if ($event === ActivityEvent::UPDATED && $oldChanges !== []) {
            $activity->withChanges([
                'attributes' => $changes,
                'old' => $oldChanges,
            ]);
        }

        $activity->log($description);
    }

    /** @param array<string, mixed> $attributes */
    private function changesForLogging(array $attributes, LogOptions $options): array
    {
        $allowed = $options->logAttributes;
        if ($options->logFillable) {
            $allowed = array_merge($allowed, $this->getFillable());
        }

        if ($allowed === []) {
            return [];
        }

        if (in_array('*', $allowed, true)) {
            $allowed = array_keys($attributes);
        }

        $excluded = array_merge(
            $options->logExceptAttributes,
            (array) ActivitylogConfig::get('default_except_attributes', [])
        );

        $changes = [];
        foreach (array_diff($allowed, $excluded) as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $changes[$attribute] = $attributes[$attribute];
            }
        }

        return $changes;
    }

    /**
     * Keep only fields whose persisted value differs from the value after update.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $oldAttributes
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function onlyDirtyChanges(array $attributes, array $oldAttributes): array
    {
        $dirtyAttributes = [];
        $dirtyOldAttributes = [];

        foreach ($attributes as $attribute => $value) {
            $oldValue = $oldAttributes[$attribute] ?? null;

            if (array_key_exists($attribute, $oldAttributes) && $this->valuesAreEquivalent($value, $oldValue)) {
                continue;
            }

            $dirtyAttributes[$attribute] = $value;
            $dirtyOldAttributes[$attribute] = $oldValue;
        }

        return [$dirtyAttributes, $dirtyOldAttributes];
    }

    /** @param mixed $first
     * @param mixed $second
     */
    private function valuesAreEquivalent($first, $second): bool
    {
        if ($first === $second) {
            return true;
        }

        return is_numeric($first) && is_numeric($second) && (string) $first === (string) $second;
    }

    /** @param object|array<string, mixed> $subject */
    private function subjectId($subject): ?int
    {
        $attributes = $this->objectToArray($subject);

        return isset($attributes[$this->getKeyName()]) ? (int) $attributes[$this->getKeyName()] : null;
    }

    /** @param object|array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function objectToArray($value): array
    {
        return is_object($value) ? get_object_vars($value) : $value;
    }
}

