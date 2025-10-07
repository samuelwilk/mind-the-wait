<?php

namespace App\Util;

use function count;

final class GtfsTimeUtils
{
    public static function timeToSeconds(?string $hhmmss): ?int
    {
        if (!$hhmmss) {
            return null;
        }

        $p = array_map('intval', explode(':', $hhmmss));

        return count($p) === 3 ? $p[0] * 3600 + $p[1] * 60 + $p[2] : null;
    }
}
