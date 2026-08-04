<?php

namespace Laraigniter\Activitylog\Support;

use Closure;

final class LogOptions
{
    public ?string $logName = null;
    public bool $logEmptyChanges = true;
    public bool $logFillable = false;
    public bool $logOnlyDirty = false;
    public array $logAttributes = [];
    public array $logExceptAttributes = [];
    public array $dontLogIfAttributesChangedOnly = [];
    public ?Closure $descriptionForEvent = null;

    public static function defaults(): self
    {
        return new self();
    }

    public function logAll(): self
    {
        return $this->logOnly(['*']);
    }

    public function logFillable(): self
    {
        $this->logFillable = true;

        return $this;
    }

    public function logOnlyDirty(): self
    {
        $this->logOnlyDirty = true;

        return $this;
    }

    public function logOnly(array $attributes): self
    {
        $this->logAttributes = $attributes;

        return $this;
    }

    public function logExcept(array $attributes): self
    {
        $this->logExceptAttributes = $attributes;

        return $this;
    }

    public function dontLogIfAttributesChangedOnly(array $attributes): self
    {
        $this->dontLogIfAttributesChangedOnly = $attributes;

        return $this;
    }

    public function dontLogEmptyChanges(): self
    {
        $this->logEmptyChanges = false;

        return $this;
    }

    public function useLogName(?string $logName): self
    {
        $this->logName = $logName;

        return $this;
    }

    public function setDescriptionForEvent(Closure $callback): self
    {
        $this->descriptionForEvent = $callback;

        return $this;
    }
}

