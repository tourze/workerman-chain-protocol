<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Scenarios\Protocol;

use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\ProtocolInterface;

/**
 * 缓存协议，用于测试
 * 简单地处理和缓存数据
 */
class BufferCacheProtocol implements ProtocolInterface
{
    /**
     * 输入检查
     * 直接处理所有数据
     */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        // 简单地接收所有数据
        return strlen($buffer);
    }

    /**
     * 解码数据
     * 简单地添加前缀
     */
    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        // 模拟某种业务处理，简单地添加前缀
        return "cached:{$buffer}";
    }

    /**
     * 编码数据
     * 简单地添加前缀
     */
    public static function encode(mixed $buffer, ConnectionInterface $connection): string
    {
        // 模拟某种业务处理，简单地添加前缀
        return "cached:{$buffer}";
    }
}
