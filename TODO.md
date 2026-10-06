# TODO — pebble_cache

Problèmes restant à traiter, détectés lors de l'audit du 2026-10-06. Le code `src/` n'a **pas** été modifié. Chaque bug est figé par un test qui vérifie le comportement actuel : il faut l'adapter au moment de la correction.

## Bugs

- [ ] **`MicroCache::has()` ignore le préfixe.** `src/MicroCache.php:23`.
  - `has()` cherche la clé brute, sans `getKey()`. Avec `setPrefix('p_')`, `has('a')` renvoie `false` après `set('a', …)`, et `has('p_a')` renvoie `true`.
  - `delete()` (ligne 58) appelle `has()` avec la clé **déjà préfixée** : il fonctionne aujourd'hui précisément grâce à ce bug.
  - Correctif : préfixer dans `has()` et, dans le même temps, remplacer dans `delete()` l'appel à `has()` par `unset($this->data[$key])` direct (sinon la clé serait préfixée deux fois et `delete()` ne supprimerait plus rien).
  - Test : `tests/MicroCacheTest.php::testHasIgnoresThePrefix` (et `testDeleteWorksWithAPrefix` doit rester vert).
- [ ] **`Helper::dateInterval2Seconds()` ignore les jours, mois et années d'un intervalle construit à la main.** `src/Helper.php:12`.
  - `$interval->days` vaut `false` sauf pour un intervalle issu de `diff()`, et `y`/`m`/`d` ne sont jamais lus. `P1D`, `P1M` et `P1Y` donnent 0. Via `MemCache::exp()`, un TTL `new DateInterval('P1D')` devient 0, c'est-à-dire **jamais expiré**.
  - Correctif : `(new DateTime('@0'))->add($interval)->getTimestamp()` (gère aussi `invert`), ou utiliser `days` seulement s'il n'est pas `false`.
  - Test : `tests/HelperTest.php::testHandBuiltIntervalLosesDaysMonthsAndYears`, `tests/MemCacheTest.php::testDayIntervalTtlMeansNoExpiration`.
- [ ] **`MemCache::getMultiple()` lève une `TypeError` avec un générateur.** `src/MemCache.php:95`.
  - `[...$keys]` consomme l'itérable, puis l'itérable d'origine est passé à `getValues(array $keys, …)`. Tout `iterable` non tableau (générateur, `ArrayIterator`) échoue, alors que la signature PSR-16 l'accepte.
  - Correctif : `$keys = [...$keys];` en début de méthode.
  - Test : `tests/MemCacheTest.php::testGetMultipleWithAGeneratorThrowsTypeError`.
- [ ] **`MemCache::decrement()` d'une clé absente donne 18446744073709551615.** `src/MemCache.php:130`.
  - La valeur initiale `-1 * $offset` est convertie en entier non signé 64 bits par Memcached. `MicroCache` donne `-$offset`, et l'ancien README promettait `-$offset`.
  - Correctif : utiliser une valeur initiale de 0 (Memcached ne stocke pas d'entier négatif), et le documenter.
  - Test : `tests/MemCacheTest.php::testDecrementOfAMissingKeyWrapsAround`.
- [ ] **Le premier `RateLimit::hit()` est accepté même au-delà du maximum.** `src/RateLimit.php:44-46`.
  - Sans entrée en cache, `hit($use)` stocke `$max - $use` sans comparer. `hit(5)` avec `$max = 3` renvoie `true` et stocke -2.
  - Correctif : `if ($use > $this->max) return false;` avant le premier enregistrement, ou passer par le calcul commun avec un stock initial de `$max`.
  - Test : `tests/RateLimitTest.php::testFirstHitIsAcceptedEvenAboveTheMaximum`.

## Dette / qualité

- [ ] PSR-16 non respecté sur le TTL : `0` (et `null`) signifie « jamais » dans `MemCache`, alors que PSR-16 traite un TTL nul ou négatif comme expiré. `MicroCache` ignore tout TTL.
- [ ] PSR-16 non respecté sur les clés : aucune validation, aucune `Psr\SimpleCache\InvalidArgumentException`.
- [ ] `src/MemCache.php:72` : une valeur `false` stockée est renvoyée comme `$default` (limite de `Memcached::get()`, contournable avec `getResultCode()`).
- [ ] `src/MemCache.php:89` : `clear()` vide tout le serveur, y compris les clés des autres préfixes et applications.
- [ ] `src/MemCache.php` : `setMultiple()`, `deleteMultiple()`, `increment()` et `decrement()` renvoient toujours `true`, sans regarder le résultat de Memcached.
- [ ] `src/MemCache.php:48` : `__destruct()` appelle `quit()` et ferme la connexion, même persistante.
- [ ] `src/RateLimit.php:76` : `stock()` renvoie le stock du dernier `hit()`, sans la recharge écoulée depuis.
- [ ] `src/RateLimit.php` : utilise `Psr\SimpleCache\CacheInterface` sous le même nom court que `Pebble\Cache\CacheInterface`, ce qui prête à confusion ; docblocks `@param string $id` sans paramètre correspondant.
- [ ] `src/SessionHandler.php:61` : `gc()` renvoie toujours 100 au lieu du nombre de sessions supprimées.
- [ ] `src/MicroCache.php` : docblocks `@return static` et `@param int $expiration` hérités de l'ancienne API.
- [ ] `src/Helper.php` : `invert` est ignoré, un intervalle négatif donne une durée positive.
