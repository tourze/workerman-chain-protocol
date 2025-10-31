<?php

namespace Tourze\Workerman\ChainProtocol;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Workerman\Connection\ConnectionInterface;

class Container
{
    public static ?LoggerInterface $logger = null;

    public static ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * @var class-string[]
     */
    public static array $decodeProtocols;

    /**
     * @var class-string[]
     */
    public static array $encodeProtocols;

    public static function getLogger(?ConnectionInterface $connection = null): ?LoggerInterface
    {
        return static::$logger;
    }

    public static function getEventDispatcher(?ConnectionInterface $connection = null): ?EventDispatcherInterface
    {
        return static::$eventDispatcher;
    }

    /**
     * @return class-string[]
     */
    public static function getDecodeProtocols(ConnectionInterface $connection): array
    {
        return static::$decodeProtocols;
    }

    /**
     * @return class-string[]
     */
    public static function getEncodeProtocols(ConnectionInterface $connection): array
    {
        return static::$encodeProtocols;
    }
}
