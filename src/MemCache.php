<?php

namespace Pebble\Cache;

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

        $this->options = $options + [
            Memcached::OPT_NO_BLOCK => true,
            Memcached::OPT_BUFFER_WRITES => false,
            Memcached::OPT_BINARY_PROTOCOL => true,
            Memcached::OPT_LIBKETAMA_COMPATIBLE => false,
            Memcached::OPT_TCP_NODELAY => true,
            Memcached::OPT_COMPRESSION => true,
            Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
            Memcached::OPT_HASH => Memcached::HASH_CRC
        ];
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

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $key = $this->getKey($key);
        $value = $this->store->get($key);

        return $value !== false ? $value : null;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return static
     */
    public function set(string $key, $value, int $expiration = 0): static
    {
        $key = $this->getKey($key);
        $this->store->set($key, $value, $this->exp($expiration));

        return $this;
    }

    /**
     * @param string $key
     * @return static
     */
    public function delete(string $key): static
    {
        $key = $this->getKey($key);
        $this->store->delete($key);

        return $this;
    }

    /**
     * @param string $key
     * @param int $expiration
     * @param int $offset
     * @return static
     */
    public function increment(string $key, int $expiration = 0, int $offset = 1): static
    {
        $key = $this->getKey($key);
        $exp = $this->exp($expiration);
        $this->store->increment($key, $offset, $offset, $exp);
        $this->store->touch($key, $exp);

        return $this;
    }

    /**
     * @param string $key
     * @param int $expiration
     * @param int $offset
     * @return static
     */
    public function decrement(string $key, int $expiration = 0, int $offset = 1): static
    {
        $key = $this->getKey($key);
        $exp = $this->exp($expiration);
        $this->store->decrement($key, $offset, -1 * $offset, $exp);
        $this->store->touch($key, $exp);

        return $this;
    }

    // -------------------------------------------------------------------------

    /**
     * @param int $exp
     * @return int
     */
    protected function exp($exp)
    {
        $now = time();

        // Memcached use the UNIX time
        // when the expiration is greater than 30 days
        if ($exp < $now && $exp > 2592000) {
            $exp = $now + $exp;
        }

        return $exp;
    }

    // -------------------------------------------------------------------------
}
