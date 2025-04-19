<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol;

use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\ProtocolInterface;

/**
 * 模拟第一个协议
 */
class MockFirstProtocol implements ProtocolInterface
{
    /**
     * Input 方法处理输入的数据包
     */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        // 简单地返回整个缓冲区长度
        return strlen($buffer);
    }

    /**
     * Decode 方法解码数据
     */
    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        // 模拟解码过程
        return "first-protocol:{$buffer}";
    }

    /**
     * Encode 方法编码数据
     *
     * @param mixed $buffer 数据
     */
    public static function encode(mixed $buffer, ConnectionInterface $connection): string
    {
        // 模拟编码过程
        return "first-protocol:{$buffer}";
    }
}
