<?php

use Pebble\Cache\CacheInterface;
use Pebble\Cache\MicroCache;
use PHPUnit\Framework\TestCase;

class MicroCacheTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Single items
    // -------------------------------------------------------------------------

    public function testImplementsCacheInterface()
    {
        self::assertInstanceOf(CacheInterface::class, new MicroCache());
    }

    public function testSetGetDelete()
    {
        $cache = new MicroCache();

        self::assertTrue($cache->set('a', ['x' => 1]));
        self::assertTrue($cache->has('a'));
        self::assertSame(['x' => 1], $cache->get('a'));

        self::assertTrue($cache->delete('a'));
        self::assertFalse($cache->has('a'));
        self::assertSame('default', $cache->get('a', 'default'));
    }

    public function testStoredNullReturnsDefaultButHasIsTrue()
    {
        $cache = new MicroCache();
        $cache->set('a', null);

        self::assertSame('default', $cache->get('a', 'default'));
        self::assertTrue($cache->has('a'));
    }

    public function testTtlIsIgnored()
    {
        $cache = new MicroCache();
        $cache->set('a', 1, -1);

        self::assertSame(1, $cache->get('a'));
    }

    public function testClearEmptiesEverything()
    {
        $cache = new MicroCache();
        $cache->set('a', 1);
        $cache->set('b', 2);

        self::assertTrue($cache->clear());
        self::assertNull($cache->get('a'));
        self::assertNull($cache->get('b'));
    }

    // -------------------------------------------------------------------------
    // Multiple items
    // -------------------------------------------------------------------------

    public function testMultipleOperations()
    {
        $cache = new MicroCache();
        $cache->setMultiple(['a' => 1, 'b' => 2]);

        self::assertSame(['a' => 1, 'b' => 2, 'c' => 0], $cache->getMultiple(['a', 'b', 'c'], 0));

        $cache->deleteMultiple(['a']);
        self::assertSame(['a' => null, 'b' => 2], $cache->getMultiple(['a', 'b']));
    }

    public function testGetMultipleAcceptsAGenerator()
    {
        $cache = new MicroCache();
        $cache->set('a', 1);

        $keys = (function () {
            yield 'a';
        })();

        self::assertSame(['a' => 1], $cache->getMultiple($keys));
    }

    // -------------------------------------------------------------------------
    // Counters
    // -------------------------------------------------------------------------

    public function testIncrementAndDecrement()
    {
        $cache = new MicroCache();

        $cache->increment('n');
        $cache->increment('n', null, 5);
        self::assertSame(6, $cache->get('n'));

        $cache->decrement('n', null, 10);
        self::assertSame(-4, $cache->get('n'));
    }

    public function testMissingCounterStartsAtOffset()
    {
        $cache = new MicroCache();

        $cache->increment('up', null, 3);
        $cache->decrement('down', null, 3);

        self::assertSame(3, $cache->get('up'));
        self::assertSame(-3, $cache->get('down'));
    }

    // -------------------------------------------------------------------------
    // Prefix
    // -------------------------------------------------------------------------

    public function testPrefixIsAppliedToStoredKeys()
    {
        $cache = (new MicroCache())->setPrefix('p_');
        $cache->set('a', 1);

        self::assertSame('p_a', $cache->getKey('a'));
        self::assertSame(1, $cache->get('a'));
        self::assertSame(['a' => 1], $cache->getMultiple(['a']));

        $cache->setPrefix('');
        self::assertSame(1, $cache->get('p_a'));
        self::assertNull($cache->get('a'));
    }

    public function testDeleteWorksWithAPrefix()
    {
        $cache = (new MicroCache())->setPrefix('p_');
        $cache->set('a', 1);
        $cache->set('b', 2);

        $cache->delete('a');
        $cache->deleteMultiple(['b']);

        self::assertNull($cache->get('a'));
        self::assertNull($cache->get('b'));
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testHasIgnoresThePrefix()
    {
        // BUG: has() looks up the raw key, without getKey(), so it misses
        // prefixed entries and finds the prefixed key itself.
        $cache = (new MicroCache())->setPrefix('p_');
        $cache->set('a', 1);

        self::assertFalse($cache->has('a'));
        self::assertTrue($cache->has('p_a'));
    }
}
