<?php

namespace Pebble\Cache;

use DateInterval;

class Helper
{

    public static function dateInterval2Seconds(DateInterval $interval): int
    {
        return $interval->days * 86400
            + $interval->h * 3600
            + $interval->i * 60
            + $interval->s;
    }
}
