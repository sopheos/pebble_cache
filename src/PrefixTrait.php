<?php

namespace Pebble\Cache;

trait PrefixTrait
{
    private string $prefix = '';
    private int $len = 0;

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;
        $this->len = mb_strlen($prefix);

        return $this;
    }

    public function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    public function getKeys(array $keys): array
    {
        return array_map([$this, 'getKey'], $keys);
    }

    public function encode(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            $out[$this->prefix . $key] = $value;
        }
        return $out;
    }

    public function decode(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            $out[mb_substr($key, $this->len)] = $value;
        }
        return $out;
    }

    public function getValues(array $keys, array $input, mixed $default = null): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $input[$key] ?? $default;
        }

        return $out;
    }
}
