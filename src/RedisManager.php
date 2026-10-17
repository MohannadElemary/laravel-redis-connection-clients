<?php

declare(strict_types=1);

namespace MohannadElemary\RedisConnectionClients;

use Illuminate\Contracts\Redis\Connector;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connectors\PhpRedisConnector;
use Illuminate\Redis\Connectors\PredisConnector;
use Illuminate\Redis\RedisManager as BaseRedisManager;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * A Redis manager that reads an optional "client" key from each connection
 * (or cluster) and uses that client for it. Connections without the key
 * are resolved by Laravel's own manager, using database.redis.client.
 */
class RedisManager extends BaseRedisManager
{
    /**
     * Resolve the given connection by name.
     *
     * @param  string|null  $name
     * @return Connection
     *
     * @throws InvalidArgumentException
     */
    public function resolve($name = null)
    {
        $name = $name ?: 'default';

        $client = $this->clientFor($this->config[$name] ?? null);

        if ($client === null) {
            return parent::resolve($name);
        }

        $options = $this->config['options'] ?? [];

        return $this->connectorFor($client, $name)->connect(
            $this->parseConnectionConfiguration(Arr::except($this->config[$name], 'client')),
            array_merge(Arr::except($options, 'parameters'), ['parameters' => Arr::get($options, 'parameters.'.$name, Arr::get($options, 'parameters', []))])
        );
    }

    /**
     * Resolve the given cluster connection by name.
     *
     * @param  string  $name
     * @return Connection
     */
    protected function resolveCluster($name)
    {
        $client = $this->clientFor($this->config['clusters'][$name]);

        if ($client === null) {
            return parent::resolveCluster($name);
        }

        return $this->connectorFor($client, $name)->connectToCluster(
            array_map(
                fn ($config) => $this->parseConnectionConfiguration($config),
                Arr::except($this->config['clusters'][$name], 'client'),
            ),
            $this->config['clusters']['options'] ?? [],
            $this->config['options'] ?? []
        );
    }

    /**
     * Get the client configured for a single connection or cluster, if any.
     */
    protected function clientFor(mixed $config): ?string
    {
        if (! is_array($config) || ! isset($config['client'])) {
            return null;
        }

        if (! is_string($config['client']) || $config['client'] === '') {
            throw new InvalidArgumentException('The Redis "client" option must be a non-empty string.');
        }

        return $config['client'];
    }

    /**
     * Get the connector for the given client.
     *
     * @throws InvalidArgumentException
     */
    protected function connectorFor(string $client, string $name): Connector
    {
        if (isset($this->customCreators[$client])) {
            return ($this->customCreators[$client])();
        }

        return match ($client) {
            'predis' => new PredisConnector,
            'phpredis' => new PhpRedisConnector,
            default => throw new InvalidArgumentException(
                "Redis client [{$client}] used by connection [{$name}] is not supported. Use phpredis, predis, or a client registered with Redis::extend()."
            ),
        };
    }
}
