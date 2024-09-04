<?php

namespace Pebble\Cache;

use DateInterval;
use Memcached;

/**
 * MemCache
 *
 * @author mathieu
 */
class MemCache implements CacheInterface
{
    use PrefixTrait;
    private ?Memcached $store = null;

    // -------------------------------------------------------------------------

    public function __construct(string $host = '127.0.0.1', int $port = 11211, array $options = [])
    {
        $options = $options + [
            Memcached::OPT_NO_BLOCK => true,
            Memcached::OPT_BUFFER_WRITES => false,
            Memcached::OPT_BINARY_PROTOCOL => true,
            Memcached::OPT_LIBKETAMA_COMPATIBLE => false,
            Memcached::OPT_TCP_NODELAY => true,
            Memcached::OPT_COMPRESSION => true,
            Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
            Memcached::OPT_HASH => Memcached::HASH_CRC
        ];

        $this->store = new \Memcached();
        foreach ($options as $option => $value) {
            $this->store->setOption($option, $value);
        }

        $this->store->addServer($host, $port);

        // Testing the connection
        if (!$this->store->getVersion()) {
            throw new CacheException('connection failed');
        }
    }

    public function __destruct()
    {
        $this->store->quit();
    }

    public function getStore(): Memcached
    {
        return $this->store;
    }

    // -------------------------------------------------------------------------

    public function has(string $key): bool
    {
        if ($this->get($key)) {
            return true;
        }

        return Memcached::RES_NOTFOUND !== $this->store->getResultCode();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->getKey($key);
        $value = $this->store->get($key);

        return $value !== false ? $value : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $key = $this->getKey($key);
        return $this->store->set($key, $value, $this->exp($ttl));
    }

    public function delete(string $key): bool
    {
        $key = $this->getKey($key);
        return $this->store->delete($key);
    }

    public function clear(): bool
    {
        return $this->store->flush();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $data = $this->store->getMulti($this->getKeys([...$keys])) ?: [];
        return $this->getValues($keys, $this->decode($data), $default);
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $this->store->setMulti(
            $this->encode([...$values]),
            $this->exp($ttl)
        );

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keys = $this->getKeys([...$keys]);
        $this->store->deleteMulti($keys);

        return true;
    }

    public function increment(string $key, null|int|DateInterval $ttl = null, int $offset = 1): bool
    {
        $key = $this->getKey($key);
        $exp = $this->exp($ttl);
        $this->store->increment($key, $offset, $offset, $exp);
        $this->store->touch($key, $exp);

        return true;
    }

    public function decrement(string $key, null|int|DateInterval $ttl = null, int $offset = 1): bool
    {
        $key = $this->getKey($key);
        $exp = $this->exp($ttl);
        $this->store->decrement($key, $offset, -1 * $offset, $exp);
        $this->store->touch($key, $exp);

        return true;
    }

    // -------------------------------------------------------------------------

    protected function exp(null|int|DateInterval $ttl = null): int
    {
        $now = time();

        if ($ttl === null) {
            $ttl = 0;
        } elseif ($ttl instanceof DateInterval) {
            $ttl = Helper::dateInterval2Seconds($ttl);
        }

        // Memcached use the UNIX time
        // when the expiration is greater than 30 days
        if ($ttl < $now && $ttl > 2592000) {
            $ttl = $now + $ttl;
        }

        return $ttl;
    }

    // -------------------------------------------------------------------------
}
