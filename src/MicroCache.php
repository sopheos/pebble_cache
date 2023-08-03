<?php

namespace Pebble\Cache;

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

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $key = $this->getKey($key);

        return $this->data[$key] ?? null;
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

        $this->data[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @return static
     */
    public function delete(string $key): static
    {
        $key = $this->getKey($key);

        if (isset($this->data[$key])) {
            unset($this->data[$key]);
        }

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

        if (!isset($this->data[$key])) {
            $this->data[$key] = $offset;
        } else {
            $this->data[$key] += $offset;
        }

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

        if (!isset($this->data[$key])) {
            $this->data[$key] = -1 * $offset;
        } else {
            $this->data[$key] -= $offset;
        }

        return $this;
    }

    // -------------------------------------------------------------------------
}
