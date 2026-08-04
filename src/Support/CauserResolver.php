<?php

namespace Laraigniter\Activitylog\Support;

use Closure;
use InvalidArgumentException;

final class CauserResolver
{
    private ?Closure $resolverOverride = null;

    /** @var object|null */
    private $causerOverride;

    /** @return object|null */
    public function resolve()
    {
        if ($this->causerOverride !== null) {
            return $this->causerOverride;
        }

        $resolver = $this->resolverOverride ?? ActivitylogConfig::get('causer_resolver');

        if ($resolver === null) {
            return null;
        }

        $causer = $resolver();

        if ($causer !== null && ! is_object($causer)) {
            throw new InvalidArgumentException('The activity log causer resolver must return an object or null.');
        }

        return $causer;
    }

    public function typeFor(object $causer): string
    {
        if ($causer instanceof \stdClass) {
            $causerModel = ActivitylogConfig::get('causer_model');

            if (is_string($causerModel) && class_exists($causerModel)) {
                return $causerModel;
            }
        }

        return get_class($causer);
    }

    public function resolveUsing(Closure $resolver): self
    {
        $this->resolverOverride = $resolver;

        return $this;
    }

    /** @param object|null $causer */
    public function setCauser($causer): self
    {
        $this->causerOverride = $causer;

        return $this;
    }

    /** @param object|null $causer */
    public function withCauser($causer, Closure $callback)
    {
        $previousCauser = $this->causerOverride;
        $this->causerOverride = $causer;

        try {
            return $callback();
        } finally {
            $this->causerOverride = $previousCauser;
        }
    }
}

