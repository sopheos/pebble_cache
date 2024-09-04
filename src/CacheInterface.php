<?php

namespace Pebble\Cache;

use Psr\SimpleCache\CacheInterface as PsrCacheInterface;

interface CacheInterface extends PsrCacheInterface
{
    public function increment(string $key, null|int|\DateInterval $ttl = null, int $offset = 1): bool;

    public function decrement(string $key, null|int|\DateInterval $ttl = null, int $offset = 1): bool;
}
