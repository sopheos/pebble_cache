# Pebble/Cache

Système de mise en cache compatible PSR-16 (`psr/simple-cache` ^3.0) pour PHP 8.1+.

La lib fournit deux caches (Memcached et en mémoire), un limiteur de débit à seau de jetons et un gestionnaire de sessions PHP adossé à un cache.

## Installation

```bash
composer require sopheos/pebble_cache
```

`MemCache` nécessite l'extension `ext-memcached`. Les autres classes s'en passent.

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/pebble-cache/`](skills/pebble-cache/). Il documente les patterns d'usage et les pièges de la librairie : TTL `0` qui signifie « jamais » et non « expiré », `DateInterval` en jours converti en 0 seconde, `false` stocké lu comme absent, `clear()` qui vide tout le serveur Memcached, `has()` de `MicroCache` qui ignore le préfixe, etc.

Dans un projet qui dépend de `sopheos/pebble_cache`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement. Le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md` :

```bash
cp -r vendor/sopheos/pebble_cache/skills/pebble-cache .claude/skills/pebble-cache
```

Pour la maintenance de la lib elle-même, voir [`CLAUDE.md`](CLAUDE.md). Les bugs connus sont listés dans [`TODO.md`](TODO.md).

## CacheInterface

`\Pebble\Cache\CacheInterface` étend `Psr\SimpleCache\CacheInterface` et ajoute deux compteurs.

Méthodes PSR-16 :

* `get(string $key, mixed $default = null): mixed` Récupère une donnée, ou `$default`.
* `set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool` Ajoute une donnée.
* `delete(string $key): bool` Supprime une donnée.
* `clear(): bool` Vide le cache.
* `getMultiple(iterable $keys, mixed $default = null): iterable` Récupère un ensemble de données, sous la forme `[clé => valeur]`.
* `setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool` Ajoute un ensemble de données `[clé => valeur]`.
* `deleteMultiple(iterable $keys): bool` Supprime un ensemble de données.
* `has(string $key): bool` Indique si la clé existe.

Ajouts :

* `increment(string $key, null|int|DateInterval $ttl = null, int $offset = 1): bool` Incrémente une valeur. Vaut `$offset` si elle n'existe pas.
* `decrement(string $key, null|int|DateInterval $ttl = null, int $offset = 1): bool` Décrémente une valeur. Vaut `-$offset` si elle n'existe pas avec `MicroCache` (voir [`TODO.md`](TODO.md) pour `MemCache`).

Préfixe, commun à `MemCache` et `MicroCache` :

* `setPrefix(string $prefix): static` Préfixe ajouté à toutes les clés.
* `getKey(string $key): string` Clé préfixée.

Les clés ne sont pas validées : aucune `InvalidArgumentException` PSR-16 n'est levée.

## MemCache

Implémente `CacheInterface` sur un serveur Memcached.

* `__construct(string $host = '127.0.0.1', int $port = 11211, array $options = [])` Se connecte au serveur. `$options` (constantes `Memcached::OPT_*`) complète les options par défaut (protocole binaire, compression, sérialiseur PHP…). Lève `CacheException('connection failed')` si le serveur ne répond pas.
* `getStore(): Memcached` L'instance `Memcached` sous-jacente.

Comportements :

* Un TTL `null` ou `0` signifie « n'expire jamais ». Au-delà de 30 jours, il est converti en timestamp UNIX.
* Une valeur `false` stockée est lue comme absente : `get()` renvoie `$default`.
* `clear()` vide **tout** le serveur, quel que soit le préfixe.
* `increment()` stocke des chaînes numériques (`'6'`) et `decrement()` s'arrête à 0.
* `setMultiple()`, `deleteMultiple()`, `increment()` et `decrement()` renvoient toujours `true`.

## MicroCache

Implémente `CacheInterface` dans un tableau PHP.

* Conserve les données uniquement le temps de l'exécution du script.
* Le TTL n'a aucun effet.
* Une valeur `null` stockée est lue comme absente par `get()`, mais `has()` renvoie `true`.

## RateLimit

Antispam qui stocke les tentatives dans un cache PSR-16.

Utilise le principe du seau de jetons (token bucket) : https://en.wikipedia.org/wiki/Token_bucket

* `__construct(Psr\SimpleCache\CacheInterface $cache, string $name, int $max, int $period)` Crée un `RateLimit` :
  * `$name` : clé de cache, utilisée telle quelle (à rendre unique, par exemple `'login_' . $ip`) ;
  * `$max` : stock initial ;
  * `$period` : durée en secondes pour reconstituer tout le stock, et TTL de l'entrée.
* `hit(int $use = 1): bool` Consomme `$use` jetons. Renvoie `false` si le stock est insuffisant (rien n'est alors consommé).
* `stock(): int` Stock enregistré lors du dernier `hit()`, sans la recharge écoulée depuis.
* `purge()` Réinitialise le stock.

```php
$limit = new \Pebble\Cache\RateLimit($cache, 'login_' . $ip, 5, 300);
if (!$limit->hit()) {
    http_response_code(429);
    exit;
}
```

## SessionHandler

Implémente `SessionHandlerInterface` pour stocker les sessions dans n'importe quel cache PSR-16.

* `__construct(Psr\SimpleCache\CacheInterface $cache, int $expiration = 3600, string $prefix = 'sess_')` `$expiration` est le TTL de chaque écriture.
* `read()` renvoie `''` pour une session inconnue. `open()`, `close()` et `gc()` ne font rien (`gc()` renvoie toujours 100).

```php
session_set_save_handler(new \Pebble\Cache\SessionHandler(new \Pebble\Cache\MemCache()), true);
session_start();
```

## Helper

* `Helper::dateInterval2Seconds(DateInterval $interval): int` Convertit un intervalle en secondes. **Attention** : les jours, mois et années d'un intervalle construit à la main (`new DateInterval('P1D')`) sont ignorés (voir [`TODO.md`](TODO.md)).

## Tests

```bash
composer install
vendor/bin/phpunit
```

Les tests de `MemCache` nécessitent `ext-memcached` et un serveur (`MEMCACHED_HOST`, `MEMCACHED_PORT`, par défaut `127.0.0.1:11211`). Ils sont sautés sinon, et n'appellent jamais `clear()`.

Les bugs connus sont figés par des tests annotés `// BUG:` qui vérifient le comportement actuel.
