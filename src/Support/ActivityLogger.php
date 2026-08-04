<?php

namespace Laraigniter\Activitylog\Support;

use Closure;
use DateTimeInterface;
use Laraigniter\Activitylog\Models\Activity;

final class ActivityLogger
{
    private ActivityLogStatus $status;
    private CauserResolver $causerResolver;
    private ?string $logName = null;
    private ?string $eventName = null;
    private ?string $subjectType = null;
    private ?int $subjectId = null;
    private ?string $causerType = null;
    private ?int $causerId = null;
    private array $attributeChanges = [];
    private array $properties = [];
    private ?DateTimeInterface $createdAt = null;

    public function __construct(ActivityLogStatus $status, CauserResolver $causerResolver)
    {
        $this->status = $status;
        $this->causerResolver = $causerResolver;
        $this->logName = ActivitylogConfig::get('default_log_name', 'default');
    }

    /**
     * Associate the log with a Laraigniter model and its primary key.
     */
    public function performedOn(object $model, ?int $id = null): self
    {
        $this->subjectType = get_class($model);
        $this->subjectId = $id ?? $this->extractId($model);

        return $this;
    }

    public function on(object $model, ?int $id = null): self
    {
        return $this->performedOn($model, $id);
    }

    public function causedBy(object $causer, ?string $causerType = null): self
    {
        $this->causerType = $causerType ?? get_class($causer);
        $this->causerId = $this->extractId($causer);

        return $this;
    }

    public function by(object $causer, ?string $causerType = null): self
    {
        return $this->causedBy($causer, $causerType);
    }

    public function causedByAnonymous(): self
    {
        $this->causerType = null;
        $this->causerId = null;

        return $this;
    }

    public function byAnonymous(): self
    {
        return $this->causedByAnonymous();
    }

    public function event(string $event): self
    {
        $this->eventName = $event;

        return $this;
    }

    public function withChanges(array $changes): self
    {
        $this->attributeChanges = $this->trimChangeValues($changes);

        return $this;
    }

    /**
     * @param array<string|int, mixed> $changes
     * @return array<string|int, mixed>
     */
    private function trimChangeValues(array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (is_array($value)) {
                $changes[$key] = $this->trimChangeValues($value);

                continue;
            }

            if (is_string($value)) {
                $changes[$key] = trim($value);
            }
        }

        return $changes;
    }

    public function withProperties(array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    /** @param mixed $value */
    public function withProperty(string $key, $value): self
    {
        $this->properties[$key] = $value;

        return $this;
    }

    public function createdAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function useLog(?string $logName): self
    {
        $this->logName = $logName;

        return $this;
    }

    public function inLog(?string $logName): self
    {
        return $this->useLog($logName);
    }

    public function tap(callable $callback): self
    {
        $callback($this);

        return $this;
    }

    public function enableLogging(): self
    {
        $this->status->enable();

        return $this;
    }

    public function disableLogging(): self
    {
        $this->status->disable();

        return $this;
    }

    /** @return object|null */
    public function log(string $description)
    {
        if ($this->status->disabled()) {
            return null;
        }

        $causer = $this->causerResolver->resolve();
        if ($this->causerType === null && $causer !== null) {
            $this->causedBy($causer, $this->causerResolver->typeFor($causer));
        }

        $activityClass = ActivitylogConfig::get('activity_model', Activity::class);
        $activity = new $activityClass();
        $createdAt = $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        $activity->create([
            'log_name' => $this->logName,
            'description' => $description,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'event' => $this->eventName,
            'causer_type' => $this->causerType,
            'causer_id' => $this->causerId,
            'attribute_changes' => json_encode($this->attributeChanges, JSON_THROW_ON_ERROR),
            'properties' => json_encode($this->properties, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $activity;
    }

    public function withoutLogging(Closure $callback)
    {
        if ($this->status->disabled()) {
            return $callback();
        }

        $this->status->disable();

        try {
            return $callback();
        } finally {
            $this->status->enable();
        }
    }

    private function extractId(object $model): ?int
    {
        return isset($model->id) ? (int) $model->id : null;
    }
}


