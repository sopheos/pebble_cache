<?php

namespace Pebble\Cache;

final class RateLimit
{
    private CacheInterface $cache;
    private string $name;
    private int $max;
    private int $period;

    /**
     * @param CacheInterface $cache Storage
     * @param string $name name of the rate limit
     * @param integer $max initial stock
     * @param integer $period seconds
     */
    public function __construct(CacheInterface $cache, string $name, int $max, int $period)
    {
        $this->cache = $cache;
        $this->name = $name;
        $this->max = $max;
        $this->period = $period;
    }

    /**
     * Rate limit
     * https://en.wikipedia.org/wiki/Token_bucket
     *
     * @param string $id
     * @param integer $use
     * @return boolean
     */
    public function hit(int $use = 1): bool
    {
        $time = time();

        $key = $this->name;
        $prev = $this->get($key);

        // First hit
        if (!$prev) {
            $this->set($key, $time, ($this->max - $use));
            return true;
        }

        $prev_time = $prev[0];
        $prev_stock = (float) $prev[1];

        $rate = $this->max / $this->period;
        $elapsed_time = $time  - $prev_time;

        // Get remaining stock from the previous hit
        $stock = $prev_stock + $elapsed_time * $rate;
        if ($stock > $this->max) $stock = $this->max;

        // The rate limit is reached
        if ($stock < $use) {
            $this->set($key, $time, $stock);
            return false;
        }

        // stock decrease
        $this->set($key, $time, ($stock - $use));
        return true;
    }

    /**
     * Get remnant hits
     *
     * @param string $id
     * @return integer
     */
    public function stock(): int
    {
        $prev = $this->get($this->name);
        return !$prev ? $this->max : max(0, (int) $prev[1]);
    }

    /**
     * Purge all
     *
     * @param string $id
     * @return void
     */
    public function purge()
    {
        $this->cache->delete($this->name);
    }

    private function set(string $key, int $time, float $stock)
    {
        $this->cache->set($key, [$time, $stock], $this->period);
    }

    private function get(string $key)
    {
        return $this->cache->get($key) ?: [];
    }
}
