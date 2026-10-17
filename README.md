# Laravel Redis Connection Clients

[![tests](https://github.com/MohannadElemary/laravel-redis-connection-clients/actions/workflows/tests.yml/badge.svg)](https://github.com/MohannadElemary/laravel-redis-connection-clients/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mohannadelemary/laravel-redis-connection-clients.svg)](https://packagist.org/packages/mohannadelemary/laravel-redis-connection-clients)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Pick the Redis client per connection. Laravel has a single `database.redis.client` setting, so every Redis
connection in an app has to use phpredis, or every one has to use predis. With this package you can add a `client`
key to any connection (or cluster) and only that one switches:

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'), // still the default for everything else

    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'client' => 'predis', // this connection only
        'host' => env('REDIS_CACHE_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

Why you'd want this: sometimes one connection needs something only one client gives you (predis' replication or
Sentinel options, phpredis' serializer and compression, or simply a host where `ext-redis` isn't an option for that
workload), while the rest of the app is fine on the other client. Before this package you had to pick one for the
whole app. It was asked for in
[laravel/framework#57142](https://github.com/laravel/framework/discussions/57142).

## Installation

```bash
composer require mohannadelemary/laravel-redis-connection-clients
```

Requires PHP 8.3+ and Laravel 12 or 13. The service provider is auto-discovered and there's nothing to publish.
Install whichever clients you use: `ext-redis` for phpredis, `predis/predis` for predis.

## Usage

### Per connection

Add `'client' => 'predis'` or `'client' => 'phpredis'` to a connection, as shown above. Connections without it are
handed straight to Laravel's own `RedisManager`, so they behave exactly as before.

```php
Redis::connection();          // Illuminate\Redis\Connections\PhpRedisConnection
Redis::connection('cache');   // Illuminate\Redis\Connections\PredisConnection
```

Anything built on a named connection (cache stores, queues, sessions, broadcasting, locks) picks it up, because
they all go through `Redis::connection($name)`.

### Clusters

The key works on a cluster too:

```php
'clusters' => [
    'sessions' => [
        'client' => 'predis',
        ['host' => '10.0.0.1', 'port' => 6379],
        ['host' => '10.0.0.2', 'port' => 6379],
    ],
],
```

### Custom clients

A client registered with `Redis::extend()` can be used by name:

```php
// AppServiceProvider::boot()
Redis::extend('sentinel', fn () => new App\Redis\SentinelConnector);
```

```php
'queue' => [
    'client' => 'sentinel',
    // ...
],
```

The `client` key is removed before the config reaches the connector, so your connector sees the same array it
would see without this package.

## How it works

The provider wraps the container's `redis` binding and swaps Laravel's `RedisManager` for a subclass that reads the
`client` key. It only does that when the bound manager is Laravel's stock class. If another package already replaced
it, this package leaves it alone (so check this if a per-connection `client` appears to be ignored).

An unknown client name throws an `InvalidArgumentException` naming the connection, rather than quietly falling back.

## Limitations

- `options` and `clusters.options` are shared, like in Laravel. Client-specific options (for example predis'
  `replication`) belong in that connection's config.
- `RedisManager::setDriver()` still changes the default client only, not connections that have their own `client`.

## Testing

```bash
composer test      # Pest, needs a Redis server on REDIS_HOST:REDIS_PORT (127.0.0.1:6379 by default)
composer analyse   # PHPStan level 8
composer lint      # Pint
```

## License

MIT. See [LICENSE](LICENSE).
