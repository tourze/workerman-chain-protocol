<?php

namespace Tourze\Workerman\ChainProtocol\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Workerman\Connection\ConnectionInterface;

/**
 * 用户上行数据
 */
class ChainDataInputEvent extends Event
{
    public function __construct(
        private readonly string $buffer,
        private int $length,
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function setLength(int $length): void
    {
        $this->length = $length;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
