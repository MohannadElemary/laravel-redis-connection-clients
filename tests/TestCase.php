<?php

declare(strict_types=1);

namespace MohannadElemary\RedisConnectionClients\Tests;

use MohannadElemary\RedisConnectionClients\RedisConnectionClientsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RedisConnectionClientsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $server = [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
        ];

        $app['config']->set('database.redis', [
            'client' => 'phpredis',
            'options' => ['prefix' => 'rcc_test:'],
            'default' => [...$server, 'database' => 0],
            'queue' => [...$server, 'database' => 1, 'client' => 'predis'],
            'cache' => [...$server, 'database' => 2, 'client' => 'predis'],
        ]);
    }

    public static function redisIsReachableForTests(): bool
    {
        $socket = @fsockopen((string) env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379), $code, $message, 1);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
