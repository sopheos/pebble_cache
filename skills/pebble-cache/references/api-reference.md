# pebble-cache — API cheat sheet

Quick lookup by intent. This is not exhaustive. Read the source in `vendor/sopheos/pebble_cache/src/` for exact signatures and for edge cases not covered here.

## CacheInterface (`Pebble\Cache\CacheInterface`)

Extends `Psr\SimpleCache\CacheInterface` (psr/simple-cache ^3.0). Implemented by `MemCache` and `MicroCache`.

| Intent | Method |
| ------ | ------ |
| Read one value (`$default` on miss) | `get(string $key, mixed $default = null): mixed` |
| Write one value | `set(string $key, mixed $value, null\|int\|DateInterval $ttl = null): bool` |
| Remove one value | `delete(string $key): bool` |
| Remove everything | `clear(): bool` (**whole server** for `MemCache`) |
| Read several, as `[key => value]` | `getMultiple(iterable $keys, mixed $default = null): iterable` |
| Write several `[key => value]` | `setMultiple(iterable $values, null\|int\|DateInterval $ttl = null): bool` |
| Remove several | `deleteMultiple(iterable $keys): bool` |
| Existence test | `has(string $key): bool` |
| Add `$offset`, creating the key at `$offset` | `increment(string $key, null\|int\|DateInterval $ttl = null, int $offset = 1): bool` |
| Subtract `$offset` | `decrement(string $key, null\|int\|DateInterval $ttl = null, int $offset = 1): bool` |

Prefix helpers (from `PrefixTrait`, on both caches):

| Intent | Method |
| ------ | ------ |
| Prefix every key | `setPrefix(string $prefix): static` |
| Prefixed form of a key | `getKey(string $key): string` |

## MemCache (`Pebble\Cache\MemCache`)

| Intent | Method |
| ------ | ------ |
| Connect (throws `CacheException` if `getVersion()` fails) | `__construct(string $host = '127.0.0.1', int $port = 11211, array $options = [])` |
| Underlying client | `getStore(): Memcached` |

Default options, overridable through `$options` (`Memcached::OPT_*` => value): non-blocking I/O, no buffered writes, binary protocol, TCP_NODELAY, compression, PHP serializer, CRC hash, no ketama.

TTL conversion: `null` → 0 (no expiry), `DateInterval` → `Helper::dateInterval2Seconds()`, values above 30 days → `time() + $ttl`.

| Operation | `MemCache` | `MicroCache` |
| --------- | ---------- | ------------ |
| TTL | honoured, 0 = never | ignored |
| Stored value read as a miss | `false` | `null` |
| Missing key on `decrement()` | 18446744073709551615 (bug) | `-$offset` |
| Counter type | numeric string after `increment()` | `int` |
| Below zero | floors at 0 | negative |
| `getMultiple()` with a generator | `TypeError` (bug) | works |
| `has()` with a prefix | correct | wrong (bug) |
| `clear()` | flushes the server | empties the array |

## RateLimit (`Pebble\Cache\RateLimit`, final)

| Intent | Method |
| ------ | ------ |
| Build | `__construct(Psr\SimpleCache\CacheInterface $cache, string $name, int $max, int $period)` |
| Consume `$use` tokens (`false` = limited, nothing consumed) | `hit(int $use = 1): bool` |
| Stock stored at the last hit, floored at 0 | `stock(): int` |
| Forget the bucket | `purge()` |

Stored under the key `$name` as `[int $time, float $stock]`, with TTL `$period`. Refill rate is `$max / $period` tokens per second, capped at `$max`.

## SessionHandler (`Pebble\Cache\SessionHandler`)

`__construct(Psr\SimpleCache\CacheInterface $cache, int $expiration = 3600, string $prefix = 'sess_')`. Implements `SessionHandlerInterface`: `read()` returns `''` on a miss, `write()` sets with TTL `$expiration`, `destroy()` deletes, `open()`/`close()` return `true`, `gc()` returns 100.

## Helper (`Pebble\Cache\Helper`)

`Helper::dateInterval2Seconds(DateInterval $interval): int` returns `days * 86400 + h * 3600 + i * 60 + s`. `days` is only set on intervals produced by `diff()`. `y`, `m`, `d` and `invert` are ignored.

## CacheException (`Pebble\Cache\CacheException`)

Extends `Exception`. Thrown only by the `MemCache` constructor.
