---
name: pebble-cache
description: How to correctly cache data, count, rate-limit and store PHP sessions using the sopheos/pebble_cache PHP library (namespace Pebble\Cache — classes MemCache, MicroCache, CacheInterface, RateLimit, SessionHandler, Helper, CacheException). Use this whenever the project's composer.json requires sopheos/pebble_cache, code imports from Pebble\Cache\*, or you're asked to add or change caching, a cache TTL, a counter, memcached access, login/API throttling or anti-spam, or session storage in a PHP project that has this library available — even if the request is phrased generically like "cache this query for a day", "limit login attempts" or "store sessions in memcached" without naming the library. Also check this before writing a raw Memcached call, a static-array memo, a hand-rolled attempts counter or a custom session_set_save_handler in such a project, since this library replaces those and has non-obvious and in places broken behavior (a TTL of 0 means never expire, a DateInterval in days becomes 0 seconds and so never expires, a stored false reads as a miss, clear() flushes the whole memcached server, MicroCache::has() ignores the prefix, MemCache::getMultiple() rejects generators, decrementing a missing memcached key wraps to 2^64-1) that hand-rolled code would miss.
---

# pebble-cache

`sopheos/pebble_cache` is a small PHP 8.1+ caching library built on PSR-16 (`psr/simple-cache` ^3.0). It ships two cache implementations of an extended PSR-16 interface (a Memcached one and an in-process array), a token-bucket rate limiter and a `SessionHandlerInterface`, both of which accept any PSR-16 cache. It does **not** validate keys, provide a file or APCu cache, tags, or stampede protection.

Namespace: `Pebble\Cache\*`. Source lives in `vendor/sopheos/pebble_cache/src/`. Read it directly when you need an exact method signature; this skill focuses on *how the pieces fit together* and the behavior that isn't obvious from the method names.

## Orientation

- `CacheInterface` extends `Psr\SimpleCache\CacheInterface` and adds `increment()` / `decrement()` (both return `bool`, read the value back with `get()`).
- `MemCache` talks to one Memcached server (`ext-memcached` required). The constructor checks the connection and throws `CacheException('connection failed')`.
- `MicroCache` stores values in a PHP array for the current request only. It ignores TTLs. Use it as a per-request memo and as a test double.
- Both caches share `setPrefix()` / `getKey()` from `PrefixTrait`.
- `RateLimit` is a token bucket keyed by a name you choose, stored in any PSR-16 cache.
- `SessionHandler` plugs a PSR-16 cache into `session_set_save_handler()`.
- `Helper::dateInterval2Seconds()` converts a `DateInterval` into seconds, badly for day/month/year intervals.

For a method cheat sheet, see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md`. Read it before debugging "the value never expires" or "the value is not found".

## Core recipes

### Shared cache

```php
use Pebble\Cache\CacheException;
use Pebble\Cache\MemCache;

try {
    $cache = (new MemCache('127.0.0.1', 11211))->setPrefix('myapp_');
} catch (CacheException) {
    $cache = new \Pebble\Cache\MicroCache();   // degrade to a per-request cache
}

$user = $cache->get('user_' . $id);
if ($user === null) {
    $user = $repository->find($id);
    $cache->set('user_' . $id, $user, 86400);   // seconds, NOT new DateInterval('P1D')
}
```

Pass TTLs as **integer seconds**. `new DateInterval('P1D')` is converted to 0, and 0 means "never expires". Never pass `0` meaning "expired".

Do not cache `false` in `MemCache`: it reads back as `$default`. Wrap it (`['found' => false]`) or store `0`.

### Per-request memo

```php
$memo = new \Pebble\Cache\MicroCache();
$memo->set('settings', $settings);
```

### Counters

```php
$cache->increment('hits_' . $page, 3600);        // creates it at 1 when missing
$hits = (int) $cache->get('hits_' . $page);      // MemCache returns a numeric string
```

`MemCache` counters are unsigned. `decrement()` stops at 0, and decrementing a **missing** key gives `18446744073709551615`. Create the counter with `set($key, 0)` first if you need to decrement it.

### Rate limiting

```php
use Pebble\Cache\RateLimit;

$limit = new RateLimit($cache, 'login_' . $ip, 5, 300);   // 5 tokens, refilled over 300 s
if (!$limit->hit()) {
    http_response_code(429);
    exit;
}
// after a successful login:
$limit->purge();
```

The name is used **as the raw cache key** (the cache prefix still applies), so make it unique per subject and per action. Never ask for `hit($n)` with `$n > $max`: the first such call is accepted.

### Sessions

```php
session_set_save_handler(new \Pebble\Cache\SessionHandler($cache, 3600, 'sess_'), true);
session_start();
```

`$expiration` is applied on every write, so it acts as an idle timeout.

## Behavior to keep in mind while writing code

- **TTL `null` and `0` mean "never expire"** in `MemCache`, unlike PSR-16. `MicroCache` ignores the TTL altogether.
- **(bug) A day/month/year `DateInterval` is converted to 0 seconds**, so it never expires. Use integer seconds.
- **A stored `false` (`MemCache`) or `null` (`MicroCache`) reads as `$default`**, while `has()` returns `true`.
- **`MemCache::clear()` flushes the whole Memcached server**, every prefix and every application on it. Use `deleteMultiple()` on known keys instead, and never call `clear()` in tests against a shared server.
- **(bug) `MicroCache::has()` ignores the prefix.** `get() !== null` is a safer existence check.
- **(bug) `MemCache::getMultiple()` only accepts arrays.** Pass `iterator_to_array($gen, false)`, not a generator.
- **(bug) Decrementing a missing `MemCache` key wraps to 2^64-1**, and counters are read back as strings.
- **Keys are not validated.** No PSR-16 `InvalidArgumentException` is ever thrown. Memcached itself rejects keys over 250 bytes or with spaces.
- **`setMultiple()`, `deleteMultiple()`, `increment()` and `decrement()` always return `true`** on `MemCache`. Do not rely on their result.
- **`RateLimit::stock()` does not include the refill** since the last `hit()`. Use it for display only, and decide with `hit()`.
- **`SessionHandler::read()` returns `''` for an unknown session**, and `gc()` does nothing (Memcached expiry does the job).

Read `references/gotchas.md` for the rest before assuming PSR-16 semantics.
