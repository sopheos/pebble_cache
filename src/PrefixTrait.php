<?php

namespace Pebble\Cache;

trait PrefixTrait
{
    private string $prefix = '';

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
