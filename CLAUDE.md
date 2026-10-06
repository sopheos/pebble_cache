# CLAUDE.md — pebble_cache

Ce fichier guide Claude Code quand il **maintient** cette librairie. Pour l'**utiliser** depuis un projet, voir le skill [`skills/pebble-cache/`](skills/pebble-cache/SKILL.md).

## Rôle

`sopheos/pebble_cache`, namespace `Pebble\Cache\`, PHP >= 8.1, dépend de `psr/simple-cache` ^3.0. `ext-memcached` est suggérée (seul `MemCache` en a besoin). La lib fournit :
- deux implémentations PSR-16 avec compteurs `increment()`/`decrement()` : Memcached et un tableau en mémoire ;
- un limiteur de débit à seau de jetons ;
- un `SessionHandlerInterface` adossé à un cache.

Pas de cache fichier, pas de tags, pas de validation des clés.

## Commandes

```bash
composer install
vendor/bin/phpunit            # toute la suite
vendor/bin/phpunit --filter MicroCacheTest
MEMCACHED_HOST=10.0.0.5 MEMCACHED_PORT=11211 vendor/bin/phpunit --filter MemCacheTest
```

## Carte de `src/`

| Fichier | Rôle |
|---|---|
| `CacheInterface.php` | Étend le `CacheInterface` PSR-16, ajoute `increment()` et `decrement()` |
| `PrefixTrait.php` | Préfixe de clé (`setPrefix()`, `getKey()`, `getKeys()`) et (dé)préfixage des tableaux pour les opérations multiples |
| `MemCache.php` | Cache Memcached. Options par défaut, test de connexion (`getVersion()`), conversion du TTL dans `exp()` |
| `MicroCache.php` | Cache dans un tableau, durée de vie du script, TTL ignoré |
| `RateLimit.php` | Seau de jetons stocké sous `[time, stock]` dans un cache PSR-16 |
| `SessionHandler.php` | `SessionHandlerInterface` sur un cache PSR-16, préfixe `sess_` |
| `Helper.php` | `dateInterval2Seconds()` |
| `CacheException.php` | Échec de connexion Memcached |

## Tests

- PHPUnit 9.5. Les classes de test n'ont pas de namespace. Les méthodes s'appellent `testPhraseEnCamelCase`, les assertions passent par `self::assertSame`, et des bannières `// ----` séparent les sections.
- `MicroCache`, `RateLimit`, `SessionHandler` et `Helper` sont testés sans service externe (`RateLimit` et `SessionHandler` sur un `MicroCache`).
- `MemCacheTest` est sauté sans `ext-memcached` ou sans serveur joignable (`MEMCACHED_HOST`/`MEMCACHED_PORT`, défaut `127.0.0.1:11211`). Il utilise un préfixe unique, supprime ses clés dans `tearDown()` et **n'appelle jamais `clear()`**, qui viderait tout le serveur.
- La conversion du TTL (`MemCache::exp()`, protégée) est testée par réflexion.

## Conventions du code

Respecter le style existant, sans le « moderniser » au passage :
- pas de `declare(strict_types=1)` ;
- docblocks hérités de l'ancienne API (`@return static`, `@param int $expiration`) parfois faux : ne pas les recopier ;
- `if` d'une ligne sans accolades tolérés ;
- séparateurs `// ------` entre les blocs de méthodes.

Une modification de comportement doit être répercutée dans `skills/pebble-cache/` (SKILL.md, `references/api-reference.md`, `references/gotchas.md`) et dans le `README.md`.

## Bugs connus

Ils sont listés dans [`TODO.md`](TODO.md). Chacun est **figé par un test** annoté `// BUG:` qui vérifie le comportement *actuel*, dans la section « Known bugs » du fichier de test de la classe concernée.

Pour corriger un bug :
1. Corriger `src/`.
2. Réécrire le test `// BUG:` pour qu'il vérifie le comportement attendu.
3. Mettre à jour l'entrée « (bug) » de `skills/pebble-cache/references/gotchas.md` et le SKILL.md.
4. Retirer l'entrée de `TODO.md` (il ne liste que ce qui reste à faire).
