<?php

namespace Laraigniter\Activitylog\Enums;

use Laraigniter\Enum\BaseEnum;
use Laraigniter\Enum\Concerns\HasLabel;
use Laraigniter\Enum\Traits\Labels;

final class ActivityEvent extends BaseEnum implements HasLabel
{
    use Labels;

    public const CREATED = 'created';

    public const UPDATING = 'updating';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const FORCE_DELETED = 'force_deleted';

    public const RESTORED = 'restored';

    public static function labels(): array
    {
        return [
            self::CREATED => self::CREATED,
            self::UPDATING => self::UPDATING,
            self::UPDATED => self::UPDATED,
            self::DELETED => self::DELETED,
            self::FORCE_DELETED => self::FORCE_DELETED,
            self::RESTORED => self::RESTORED,
        ];
    }

    public static function badgeClass(string $event): string
    {
        $badges = [
            self::CREATED => 'success',
            self::UPDATED => 'warning',
            self::DELETED => 'danger',
            self::FORCE_DELETED => 'danger',
            self::RESTORED => 'primary',
        ];

        return $badges[$event] ?? 'secondary';
    }
}

