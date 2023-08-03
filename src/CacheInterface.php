<?php

namespace Pebble\Cache;

interface CacheInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return static
     */
    public function set(string $key, $value, int $expiration = 0): static;

    /**
     * @param string $key
     * @return static
     */
    public function delete(string $key): static;

    /**
     * @param string $key
     * @param int $expiration
     * @param int $offset
     * @return static
     */
    public function increment(string $key, int $expiration = 0, int $offset = 1): static;

    /**
     * @param string $key
     * @param int $expiration
     * @param int $offset
     * @return static
     */
    public function decrement(string $key, int $expiration = 0, int $offset = 1): static;
}
