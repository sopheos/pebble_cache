<?php

use Pebble\Cache\MicroCache;
use Pebble\Cache\RateLimit;
use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Token bucket
    // -------------------------------------------------------------------------

    public function testFreshLimitHasFullStock()
    {
        $limit = new RateLimit(new MicroCache(), 'login', 3, 60);

        self::assertSame(3, $limit->stock());
    }

    public function testHitsConsumeTheStockUntilRefused()
    {
        $limit = new RateLimit(new MicroCache(), 'login', 3, 60);

        self::assertTrue($limit->hit());
        self::assertTrue($limit->hit());
        self::assertTrue($limit->hit());
        self::assertFalse($limit->hit());
        self::assertSame(0, $limit->stock());
    }

    public function testHitCanConsumeSeveralTokens()
    {
        $limit = new RateLimit(new MicroCache(), 'login', 5, 60);

        self::assertTrue($limit->hit(2));
        self::assertSame(3, $limit->stock());
        self::assertFalse($limit->hit(4));
        self::assertSame(3, $limit->stock());
    }

    public function testStockRefillsWithElapsedTime()
    {
        $cache = new MicroCache();
        $limit = new RateLimit($cache, 'login', 3, 60);
        $cache->set('login', [time() - 40, 0.0]);

        // 40 s at 3 tokens / 60 s = 2 tokens, one is consumed.
        self::assertTrue($limit->hit());
        self::assertSame(1, $limit->stock());
    }

    public function testStockDoesNotIncludeTheRefill()
    {
        $cache = new MicroCache();
        $limit = new RateLimit($cache, 'login', 3, 60);
        $cache->set('login', [time() - 60, 0.0]);

        self::assertSame(0, $limit->stock());
        self::assertTrue($limit->hit());
        self::assertSame(2, $limit->stock());
    }

    public function testNameIsTheRawCacheKey()
    {
        $cache = new MicroCache();
        $limit = new RateLimit($cache, 'login', 3, 60);
        $limit->hit();

        self::assertTrue($cache->has('login'));
        self::assertSame(2.0, $cache->get('login')[1]);
    }

    public function testPurgeResetsTheStock()
    {
        $limit = new RateLimit(new MicroCache(), 'login', 1, 60);
        $limit->hit();
        $limit->purge();

        self::assertSame(1, $limit->stock());
        self::assertTrue($limit->hit());
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testFirstHitIsAcceptedEvenAboveTheMaximum()
    {
        // BUG: the first hit stores max - use without checking it, so it is
        // accepted even when $use > $max, and the stored stock goes negative.
        $cache = new MicroCache();
        $limit = new RateLimit($cache, 'login', 3, 60);

        self::assertTrue($limit->hit(5));
        self::assertSame(-2.0, $cache->get('login')[1]);
        self::assertSame(0, $limit->stock());
    }
}
