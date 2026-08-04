<?php

namespace Laraigniter\Activitylog\Support;

final class ActivitylogConfig
{
    /**
     * Read a value from the CodeIgniter config group loaded by the provider.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $config = app('config')->config['activitylog'] ?? [];

        return $config[$key] ?? $default;
    }
}

