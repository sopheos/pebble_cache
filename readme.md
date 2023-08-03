# Pebble/Cache

Système de de mise en cache.


## CacheInterface

- `start(): static` Démarre le service
- `close(): static` Stop le service
- `set(string $key, $value, int $expiration = 0): static` Ajoute une donnée.
- `setMulti(array $items, int $expiration = 0): static` Ajoute un ensemble de données.
- `delete(string $key): static` Supprime une donnée.
- `get(string $key): mixed` Récupère une donnée
- `getMulti(array $keys): array` Récupère un ensemble de données.
- `increment(string $key, int $expiration = 0, int $offset = 1): static` : Incrémente une valeur. (Vaut `$offset` si n'existe pas.)
- `decrement(string $key, int $expiration = 0, int $offset = 1): static` : Décrémente une valeur. (Vaut `- $offset` si n'existe pas.)

## MemCache

- Implémente `CacheInterface`.
- Se connecte à un serveur Memcached.

## MicroCache

- Implémente `CacheInterface`.
- Conserve les données en cache uniquement le temps de l'execution du script.
- L'expiration des méthodes n'a pas d'effet.

## SessionHandler

- Implémente `SessionHandlerInterface`.
- Permet de stocker les sessions dans un `CacheInterface`.

## RateLimit

Antispam qui stocke les tentatives dans un `CacheInterface`.

Utilise le principe de Token bucket : https://en.wikipedia.org/wiki/Token_bucket

- `__construct(CacheInterface $cache, string $name, int $max, int $period)` Crée un `RateLimit` :
  - `$name` : Nom
  - `$max` : Stock initial
  - `$period` : Période en secondes avant réinitialisation partiel du stock
- `hit(int $use = 1): bool` : Consomme `$use` éléments du stock et vérifie que le stock est toujours plein.
- `stock(): int` Récupère le stock courant.
- `purge()` Réinitialise le stock.
