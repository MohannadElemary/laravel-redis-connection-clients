<?php

declare(strict_types=1);

namespace MohannadElemary\RedisConnectionClients\Tests;

use Illuminate\Contracts\Redis\Connector;
use Illuminate\Redis\Connections\PredisConnection;
use Predis\Client;

class FakeConnector implements Connector
{
    /** @var list<array{type: string, config: array<mixed>, options: array<mixed>}> */
    public static array $calls = [];

    public function connect(array $config, array $options)
    {
        static::$calls[] = ['type' => 'single', 'config' => $config, 'options' => $options];

        return new PredisConnection(new Client);
    }

    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        static::$calls[] = ['type' => 'cluster', 'config' => $config, 'options' => $clusterOptions];

        return new PredisConnection(new Client);
    }
}
