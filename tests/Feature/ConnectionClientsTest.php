<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use MohannadElemary\RedisConnectionClients\RedisManager;
use MohannadElemary\RedisConnectionClients\Tests\FakeConnector;
use MohannadElemary\RedisConnectionClients\Tests\TestCase;
use Predis\Client;

beforeEach(function () {
    FakeConnector::$calls = [];
});

it('replaces the redis manager for every way it is resolved', function () {
    expect(app('redis'))->toBeInstanceOf(RedisManager::class)
        ->and(app(Factory::class))->toBe(app('redis'))
        ->and(app(Illuminate\Redis\RedisManager::class))->toBe(app('redis'))
        ->and(Redis::getFacadeRoot())->toBe(app('redis'));
});

it('uses the client set on a connection and the global client elsewhere', function () {
    expect(Redis::connection('queue'))->toBeInstanceOf(PredisConnection::class)
        ->and(Redis::connection('cache'))->toBeInstanceOf(PredisConnection::class)
        ->and(Redis::connection('default'))->toBeInstanceOf(PhpRedisConnection::class)
        ->and(Redis::connection())->toBeInstanceOf(PhpRedisConnection::class);
})->skip(fn () => ! extension_loaded('redis') || ! TestCase::redisIsReachableForTests(), 'Needs ext-redis and a Redis server.');

it('talks to the server through both clients', function () {
    Redis::connection('default')->set('greeting', 'from phpredis');
    Redis::connection('queue')->set('greeting', 'from predis');

    expect(Redis::connection('default')->get('greeting'))->toBe('from phpredis')
        ->and(Redis::connection('queue')->get('greeting'))->toBe('from predis')
        ->and(Redis::connection('queue')->client())->toBeInstanceOf(Client::class)
        ->and(Redis::connection('default')->client())->toBeInstanceOf('Redis');

    Redis::connection('default')->del('greeting');
    Redis::connection('queue')->del('greeting');
})->skip(fn () => ! extension_loaded('redis') || ! TestCase::redisIsReachableForTests(), 'Needs ext-redis and a Redis server.');

it('lets a cache store run on a predis connection while the rest uses phpredis', function () {
    config()->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'cache']);

    Cache::store('redis')->put('answer', ['value' => 42], 60);

    expect(Cache::store('redis')->get('answer'))->toBe(['value' => 42])
        ->and(Cache::store('redis')->connection())->toBeInstanceOf(PredisConnection::class);

    Cache::store('redis')->forget('answer');
})->skip(fn () => ! TestCase::redisIsReachableForTests(), 'Needs a Redis server.');

it('uses a client registered with Redis::extend() for just that connection', function () {
    config()->set('database.redis.custom', ['host' => 'custom.test', 'port' => 6390, 'client' => 'fake']);
    app()->forgetInstance('redis');

    Redis::extend('fake', fn () => new FakeConnector);

    Redis::connection('custom');

    expect(FakeConnector::$calls)->toHaveCount(1)
        ->and(FakeConnector::$calls[0]['type'])->toBe('single')
        ->and(FakeConnector::$calls[0]['config'])->toMatchArray(['host' => 'custom.test', 'port' => 6390])
        ->and(FakeConnector::$calls[0]['config'])->not->toHaveKey('client')
        ->and(FakeConnector::$calls[0]['options'])->toMatchArray(['prefix' => 'rcc_test:']);
});

it('passes per connection parameters the same way laravel does', function () {
    config()->set('database.redis.custom', ['host' => 'custom.test', 'client' => 'fake']);
    config()->set('database.redis.options.parameters', ['password' => 'global', 'custom' => ['password' => 'scoped']]);
    app()->forgetInstance('redis');

    Redis::extend('fake', fn () => new FakeConnector);
    Redis::connection('custom');

    expect(FakeConnector::$calls[0]['options']['parameters'])->toBe(['password' => 'scoped']);
});

it('supports connection urls next to the client key', function () {
    config()->set('database.redis.custom', ['url' => 'tls://user:secret@cache.test:6380/3', 'client' => 'fake']);
    app()->forgetInstance('redis');

    Redis::extend('fake', fn () => new FakeConnector);
    Redis::connection('custom');

    expect(FakeConnector::$calls[0]['config'])->toMatchArray([
        'host' => 'cache.test',
        'port' => 6380,
        'username' => 'user',
        'password' => 'secret',
        'database' => 3,
        'scheme' => 'tls',
    ])->not->toHaveKey('client');
});

it('uses the client set on a cluster', function () {
    config()->set('database.redis.clusters', [
        'options' => ['cluster' => 'redis'],
        'sessions' => [
            'client' => 'fake',
            ['host' => 'node-1.test', 'port' => 7000],
            ['host' => 'node-2.test', 'port' => 7001],
        ],
    ]);
    app()->forgetInstance('redis');

    Redis::extend('fake', fn () => new FakeConnector);
    Redis::connection('sessions');

    expect(FakeConnector::$calls)->toHaveCount(1)
        ->and(FakeConnector::$calls[0]['type'])->toBe('cluster')
        ->and(FakeConnector::$calls[0]['config'])->toHaveCount(2)
        ->and(array_column(FakeConnector::$calls[0]['config'], 'host'))->toBe(['node-1.test', 'node-2.test'])
        ->and(FakeConnector::$calls[0]['options'])->toBe(['cluster' => 'redis']);
});

it('leaves connections without a client key to laravel', function () {
    config()->set('database.redis.client', 'fake');
    config()->set('database.redis.plain', ['host' => 'plain.test']);
    app()->forgetInstance('redis');

    Redis::extend('fake', fn () => new FakeConnector);
    Redis::connection('plain');

    expect(FakeConnector::$calls)->toHaveCount(1)
        ->and(FakeConnector::$calls[0]['config'])->toMatchArray(['host' => 'plain.test']);
});

it('fails clearly for an unknown client', function () {
    config()->set('database.redis.custom', ['host' => 'custom.test', 'client' => 'nope']);
    app()->forgetInstance('redis');

    Redis::connection('custom');
})->throws(InvalidArgumentException::class, 'Redis client [nope] used by connection [custom] is not supported.');

it('rejects a client option that is not a string', function () {
    config()->set('database.redis.custom', ['host' => 'custom.test', 'client' => ['predis']]);
    app()->forgetInstance('redis');

    Redis::connection('custom');
})->throws(InvalidArgumentException::class, 'The Redis "client" option must be a non-empty string.');

it('still throws for a connection that is not configured', function () {
    Redis::connection('missing');
})->throws(InvalidArgumentException::class, 'Redis connection [missing] not configured.');

it('does not replace a manager another package already swapped in', function () {
    $custom = new class(app(), 'phpredis', []) extends Illuminate\Redis\RedisManager {};

    app()->forgetInstance('redis');
    app()->singleton('redis', fn () => $custom);

    expect(app('redis'))->toBe($custom);
});
