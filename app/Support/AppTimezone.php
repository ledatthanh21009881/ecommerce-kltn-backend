<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PHP date()/DateTime default timezone (CLI, FPM, cron, websocket).
 * Docker TZ alone does not set date.timezone in official php images.
 */
final class AppTimezone
{
    public const DEFAULT = 'Asia/Ho_Chi_Minh';

    public static function apply(?string $timezone = null): void
    {
        $tz = $timezone ?? ($_ENV['APP_TIMEZONE'] ?? self::DEFAULT);
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $tz = self::DEFAULT;
        }
        date_default_timezone_set($tz);
    }
}
