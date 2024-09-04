<?php

namespace Pebble\Cache;

use DateInterval;

/**
 * MicroCache
 *
 * @author mathieu
 */
class MicroCache implements CacheInterface
{
    use PrefixTrait;

    private array $data = [];

    // -------------------------------------------------------------------------


    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->getKey($key);
        return $this->data[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return static
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $key = $this->getKey($key);
        $this->data[$key] = $value;

        return true;
    }

    /**
     * @param string $key
     * @return static
     */
    public function delete(string $key): bool
    {
        $key = $this->getKey($key);

        if ($this->has($key)) {
            unset($this->data[$key]);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->get($key, $default);
        }

        return $data;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function increment(string $key, null|int|DateInterval $ttl = null, int $offset = 1): bool
    {
        $key = $this->getKey($key);

        if (!isset($this->data[$key])) {
            $this->data[$key] = $offset;
        } else {
            $this->data[$key] += $offset;
        }

        return true;
    }

    public function decrement(string $key, null|int|DateInterval $ttl = null, int $offset = 1): bool
    {
        $key = $this->getKey($key);

        if (!isset($this->data[$key])) {
            $this->data[$key] = -1 * $offset;
        } else {
            $this->data[$key] -= $offset;
        }

        return true;
    }

    // -------------------------------------------------------------------------
}
