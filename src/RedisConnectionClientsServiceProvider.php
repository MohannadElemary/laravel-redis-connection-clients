<?php

declare(strict_types=1);

namespace MohannadElemary\RedisConnectionClients;

use Illuminate\Redis\RedisManager as BaseRedisManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class RedisConnectionClientsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('redis', function (mixed $redis, $app) {
            // Only swap Laravel's stock manager. If another package already
            // replaced it with its own class, leave that one alone.
            if (! $redis instanceof BaseRedisManager || $redis::class !== BaseRedisManager::class) {
                return $redis;
            }

            $config = $app->make('config')->get('database.redis', []);

            return new RedisManager($app, Arr::pull($config, 'client', 'phpredis'), $config);
        });
    }
}
