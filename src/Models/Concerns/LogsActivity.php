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
        foreach ([ActivityEvent::CREATED, ActivityEvent::UPDATING, ActivityEvent::UPDATED, ActivityEvent::DELETED, ActivityEvent::FORCE_DELETED, ActivityEvent::RESTORED] as $event) {
            $modelEvent = $event === ActivityEvent::FORCE_DELETED ? 'forceDeleted' : $event;
            $method = 'activitylog' . str_replace('_', '', ucwords($event, '_'));

            $this->{$modelEvent}[] = $method;
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
                $subject = $this->softDeletedSubject($model);

                $this->recordActivity(ActivityEvent::DELETED, $subject, $this->objectToArray($subject));
            }
        }

        return $models;
    }

    /** @param array<int, object|array<string, mixed>> $models */
    protected function activitylogForceDeleted(array $models): array
    {
        foreach ($models as $model) {
            if (is_array($model) || is_object($model)) {
                $this->recordActivity(ActivityEvent::FORCE_DELETED, $model, $this->objectToArray($model));
            }
        }

        return $models;
    }

    /** @param mixed $models */
    protected function activitylogRestored($models)
    {
        if (is_object($models)) {
            $this->recordActivity(ActivityEvent::RESTORED, $models, $this->objectToArray($models));

            return $models;
        }

        if (! is_array($models)) {
            return $models;
        }

        if (array_key_exists($this->getKeyName(), $models)) {
            $this->recordActivity(ActivityEvent::RESTORED, $models, $models);

            return $models;
        }

        foreach ($models as $model) {
            if (is_array($model) || is_object($model)) {
                $this->recordActivity(ActivityEvent::RESTORED, $model, $this->objectToArray($model));
            }
        }

        return $models;
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

        $properties = $this->morphPropertiesForLogging($attributes, $options->morphs);

        if ($options->propertiesForEvent !== null) {
            $customProperties = ($options->propertiesForEvent)($event, $attributes);

            if (is_array($customProperties)) {
                $properties = array_merge($properties, $customProperties);
            }
        }

        if ($properties !== []) {
            $activity->withProperties($properties);
        }

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

    /**
     * MY_Model provides only the primary key to a soft-delete observer. Reload
     * that row with trashed records included so the activity contains the
     * attributes that were deleted.
     *
     * @param object|array<string, mixed> $subject
     * @return object|array<string, mixed>
     */
    private function softDeletedSubject($subject)
    {
        $attributes = $this->objectToArray($subject);
        $id = $attributes[$this->getKeyName()] ?? null;

        if (! is_numeric($id) || ! method_exists($this, 'withTrashed')) {
            return $subject;
        }

        $deletedSubject = $this->withTrashed()->find((int) $id);

        return is_object($deletedSubject) ? $deletedSubject : $subject;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string> $morphs
     * @return array<string, array<string, array{type: string, id: int}>>
     */
    private function morphPropertiesForLogging(array $attributes, array $morphs): array
    {
        $relations = [];

        foreach ($morphs as $morph) {
            $type = $attributes[$morph . '_type'] ?? null;
            $id = $attributes[$morph . '_id'] ?? null;

            if (! is_string($type) || $type === '' || ! is_numeric($id)) {
                continue;
            }

            $relations[$morph] = [
                'type' => $type,
                'id' => (int) $id,
            ];
        }

        return $relations === [] ? [] : ['polymorphic_relations' => $relations];
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

