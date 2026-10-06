<?php

use Pebble\Cache\CacheException;
use Pebble\Cache\MemCache;
use PHPUnit\Framework\TestCase;

/**
 * Needs ext-memcached and a server on MEMCACHED_HOST:MEMCACHED_PORT
 * (default 127.0.0.1:11211). Skipped otherwise.
 *
 * clear() is never called: it flushes the whole server.
 */
class MemCacheTest extends TestCase
{
    private ?MemCache $cache = null;
    private array $keys = [];

    protected function setUp(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('ext-memcached is not loaded');
        }

        try {
            $this->cache = new MemCache(
                getenv('MEMCACHED_HOST') ?: '127.0.0.1',
                (int) (getenv('MEMCACHED_PORT') ?: 11211)
            );
        } catch (CacheException $ex) {
            self::markTestSkipped('no memcached server available');
        }

        $this->cache->setPrefix('pebble_cache_test_' . uniqid() . '_');
    }

    protected function tearDown(): void
    {
        if ($this->cache) {
            $this->cache->deleteMultiple($this->keys);
        }
    }

    private function cache(string ...$keys): MemCache
    {
        $this->keys = array_merge($this->keys, $keys);
        return $this->cache;
    }

    private function exp(mixed $ttl): int
    {
        $method = new ReflectionMethod(MemCache::class, 'exp');
        $method->setAccessible(true);

        return $method->invoke($this->cache, $ttl);
    }

    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------

    public function testUnreachableServerThrowsCacheException()
    {
        $this->expectException(CacheException::class);
        new MemCache('127.0.0.1', 1);
    }

    // -------------------------------------------------------------------------
    // Single items
    // -------------------------------------------------------------------------

    public function testSetGetHasDelete()
    {
        $cache = $this->cache('a');

        self::assertTrue($cache->set('a', ['x' => 1]));
        self::assertTrue($cache->has('a'));
        self::assertSame(['x' => 1], $cache->get('a'));

        self::assertTrue($cache->delete('a'));
        self::assertFalse($cache->has('a'));
        self::assertSame('default', $cache->get('a', 'default'));
    }

    public function testDeleteMissingKeyReturnsFalse()
    {
        self::assertFalse($this->cache()->delete('missing'));
    }

    public function testStoredFalseReturnsDefaultButHasIsTrue()
    {
        $cache = $this->cache('f');
        $cache->set('f', false);

        self::assertSame('default', $cache->get('f', 'default'));
        self::assertTrue($cache->has('f'));
    }

    // -------------------------------------------------------------------------
    // Multiple items
    // -------------------------------------------------------------------------

    public function testMultipleOperations()
    {
        $cache = $this->cache('a', 'b');
        $cache->setMultiple(['a' => 1, 'b' => 2]);

        self::assertSame(['a' => 1, 'b' => 2, 'c' => 0], $cache->getMultiple(['a', 'b', 'c'], 0));

        $cache->deleteMultiple(['a']);
        self::assertSame(['a' => null, 'b' => 2], $cache->getMultiple(['a', 'b']));
    }

    // -------------------------------------------------------------------------
    // Counters
    // -------------------------------------------------------------------------

    public function testIncrementReturnsNumericStrings()
    {
        $cache = $this->cache('n');
        $cache->increment('n');
        $cache->increment('n', null, 5);

        self::assertSame('6', $cache->get('n'));
    }

    public function testDecrementStopsAtZero()
    {
        $cache = $this->cache('d');
        $cache->set('d', 2);
        $cache->decrement('d', null, 5);

        self::assertSame(0, $cache->get('d'));
    }

    // -------------------------------------------------------------------------
    // Expiration
    // -------------------------------------------------------------------------

    public function testTtlConversion()
    {
        self::assertSame(0, $this->exp(null));
        self::assertSame(0, $this->exp(0));
        self::assertSame(3600, $this->exp(new DateInterval('PT1H')));

        // Above 30 days, memcached expects a UNIX timestamp.
        $now = time();
        self::assertEqualsWithDelta($now + 31 * 86400, $this->exp(31 * 86400), 2);
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testGetMultipleWithAGeneratorThrowsTypeError()
    {
        // BUG: [...$keys] consumes the iterable, then the original iterable is
        // passed to getValues(array $keys), which rejects a Generator.
        $keys = (function () {
            yield 'a';
        })();

        $this->expectException(TypeError::class);
        $this->cache()->getMultiple($keys);
    }

    public function testDecrementOfAMissingKeyWrapsAround()
    {
        // BUG: the initial value -$offset is cast to an unsigned 64-bit integer
        // by memcached, instead of giving -$offset like MicroCache.
        $cache = $this->cache('m');
        $cache->decrement('m');

        self::assertSame('18446744073709551615', (string) $cache->get('m'));
    }

    public function testDayIntervalTtlMeansNoExpiration()
    {
        // BUG: Helper::dateInterval2Seconds() gives 0 for P1D, and 0 means
        // "never expires" for memcached.
        self::assertSame(0, $this->exp(new DateInterval('P1D')));
    }
}
