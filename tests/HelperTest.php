<?php

use Pebble\Cache\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // dateInterval2Seconds
    // -------------------------------------------------------------------------

    public function testTimePartsAreConverted()
    {
        self::assertSame(3600, Helper::dateInterval2Seconds(new DateInterval('PT1H')));
        self::assertSame(3723, Helper::dateInterval2Seconds(new DateInterval('PT1H2M3S')));
    }

    public function testIntervalFromDiffCountsDays()
    {
        $interval = (new DateTime('2024-01-01'))->diff(new DateTime('2024-01-03 00:00:05'));

        self::assertSame(172805, Helper::dateInterval2Seconds($interval));
    }

    public function testInvertFlagIsIgnored()
    {
        $interval = new DateInterval('PT1H');
        $interval->invert = 1;

        self::assertSame(3600, Helper::dateInterval2Seconds($interval));
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testHandBuiltIntervalLosesDaysMonthsAndYears()
    {
        // BUG: $interval->days is false unless the interval comes from diff(),
        // and y/m/d are never read, so P1D, P1M and P1Y all give 0 second.
        self::assertSame(0, Helper::dateInterval2Seconds(new DateInterval('P1D')));
        self::assertSame(0, Helper::dateInterval2Seconds(new DateInterval('P1M')));
        self::assertSame(0, Helper::dateInterval2Seconds(new DateInterval('P1Y')));
        self::assertSame(3600, Helper::dateInterval2Seconds(new DateInterval('P1DT1H')));
    }
}
