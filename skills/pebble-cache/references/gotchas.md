# pebble-cache — gotchas

Things the method names don't tell you, grouped by class. Every item below is pinned by a test in `tests/`. Items marked **(bug)** are listed in the package's `TODO.md` and may be fixed in a later version. Check the test of the same name in `vendor/sopheos/pebble_cache/tests/` to see the current behavior. `MemCacheTest` needs `ext-memcached` and a running server, and is skipped otherwise.

## MicroCache

- **The TTL is ignored.** Even a negative TTL leaves the value readable for the rest of the request.
- **A stored `null` reads as `$default`, but `has()` returns `true`.** `get()` uses `??`.
- **(bug) `has()` ignores the prefix.** With `setPrefix('p_')`, `has('a')` is `false` after `set('a', 1)`, and `has('p_a')` is `true`. `get()`, `set()` and `delete()` do apply the prefix.
- **`delete()` and `deleteMultiple()` work with a prefix**, by accident: they pass the already-prefixed key to the buggy `has()`.
- **`getMultiple()` fills missing keys with `$default`** and accepts any iterable, generators included.
- **A missing counter starts at `$offset` (`increment`) or `-$offset` (`decrement`).** Values stay `int` and may go negative.
- **Changing the prefix hides existing keys.** `setPrefix('')` then `get('p_a')` reads what was stored as `a` under `p_`.

## MemCache

- **The constructor throws `CacheException('connection failed')`** when `getVersion()` fails, for example on an unreachable port.
- **TTL `null` and `0` mean "never expire"**, contrary to PSR-16. A TTL above 30 days is turned into an absolute UNIX timestamp.
- **(bug) `new DateInterval('P1D')` is converted to 0 second**, so the value never expires. See `Helper` below.
- **A stored `false` reads as `$default`, but `has()` returns `true`.** `Memcached::get()` returns `false` on a miss, and the wrapper cannot tell the two apart.
- **`delete()` of a missing key returns `false`.**
- **(bug) `getMultiple()` throws `TypeError` for a generator.** The original iterable is passed to `getValues(array $keys, …)`. Arrays work.
- **`increment()` turns the value into a numeric string.** `get()` returns `'6'`, not `6`.
- **`decrement()` floors at 0.** Memcached counters are unsigned.
- **(bug) `decrement()` of a missing key gives `18446744073709551615`.** The initial value `-$offset` is cast to an unsigned 64-bit integer.

## RateLimit

- **The name is the raw cache key.** `new RateLimit($cache, 'login', …)` writes `$cache->get('login')` as `[time, (float) stock]`. Include the subject (IP, user id) in the name.
- **A refused `hit()` consumes nothing.** `hit(4)` with 3 tokens left returns `false` and leaves 3.
- **The stock refills at `$max / $period` tokens per second**, capped at `$max`, computed at each `hit()`.
- **`stock()` does not include the refill.** After a long pause it can return 0 while the next `hit()` succeeds.
- **`purge()` deletes the entry**, so the next call sees a full bucket.
- **(bug) The first `hit()` is accepted even above the maximum.** `hit(5)` on a fresh bucket with `$max = 3` returns `true` and stores -2. `stock()` then shows 0.

## SessionHandler

- **Keys are `$prefix . $id`**, `sess_` by default.
- **`read()` returns `''` for an unknown or destroyed session**, never `false`.
- **`open()`, `close()` are no-ops and `gc()` always returns 100.** Expiry relies on the cache TTL (`$expiration`, refreshed at each write).

## Helper

- **Only `diff()` intervals count their days.** `days` is `false` on an interval built by hand.
- **(bug) `P1D`, `P1M` and `P1Y` give 0, and `P1DT1H` gives 3600.** `y`, `m` and `d` are never read.
- **`invert` is ignored.** A negative interval gives a positive duration.
