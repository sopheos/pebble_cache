<?php

namespace Pebble\Cache;

use SessionHandlerInterface;

/**
 * SessionHandler
 *
 * @author mathieu
 */
class SessionHandler implements SessionHandlerInterface
{
    private CacheInterface $cache;
    private int $expiration;
    private string $prefix;

    // -------------------------------------------------------------------------

    /**
     * @param CacheManager $cache
     * @param array $options
     */
    public function __construct(CacheInterface $cache, int $expiration = 3600, string $prefix = 'sess_')
    {
        $this->cache = $cache;
        $this->expiration = $expiration;
        $this->prefix = $prefix;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->cache->get($this->prefix . $id);

        return $data ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $this->cache->set($this->prefix . $id, $data, $this->expiration);

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->cache->delete($this->prefix . $id);

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 100;
    }
}
