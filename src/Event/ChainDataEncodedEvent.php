<?php

namespace Tourze\Workerman\ChainProtocol\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Workerman\Connection\ConnectionInterface;

/**
 * 数据加密完成，准备下行发送给用户
 */
class ChainDataEncodedEvent extends Event
{
    public function __construct(
        private string $buffer,
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function setBuffer(string $buffer): void
    {
        $this->buffer = $buffer;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}
