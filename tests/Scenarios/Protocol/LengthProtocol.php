<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Scenarios\Protocol;

use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\ProtocolInterface;

/**
 * 基于长度的协议，用于测试
 * 包格式：包头4字节(N) + 包体
 */
class LengthProtocol implements ProtocolInterface
{
    /**
     * 输入检查
     * 确保接收到了一个完整的包
     */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        // 如果数据不足4字节，无法获取长度
        if (strlen($buffer) < 4) {
            return 0;
        }

        // 读取包长
        $unpacked = unpack('N', $buffer);
        if (false === $unpacked) {
            return 0;
        }
        $length = $unpacked[1];

        // 完整包长 = 4字节头部 + 包体长度
        $totalLength = 4 + $length;

        // 如果接收的数据不够一个包，继续等待
        if (strlen($buffer) < $totalLength) {
            return 0;
        }

        // 返回实际处理的包的总长度
        return $totalLength;
    }

    /**
     * 解码数据
     */
    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        // 跳过4字节的长度头，直接返回包体内容
        return substr($buffer, 4);
    }

    /**
     * 编码数据
     * 在数据前加上4字节的长度头
     */
    public static function encode(mixed $buffer, ConnectionInterface $connection): string
    {
        // 计算数据长度并在前面添加长度头
        $length = strlen($buffer);

        return pack('N', $length) . $buffer;
    }
}
