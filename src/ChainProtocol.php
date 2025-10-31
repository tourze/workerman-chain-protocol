<?php

namespace Tourze\Workerman\ChainProtocol;

use SebastianBergmann\Timer\Timer;
use Tourze\Workerman\ChainProtocol\Context\ChainDecodeContext;
use Tourze\Workerman\ChainProtocol\Context\ChainEncodeContext;
use Tourze\Workerman\ChainProtocol\Context\ProtocolRecvBuffersContext;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodedEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodingEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataEncodedEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataInputEvent;
use Tourze\Workerman\ConnectionContext\ContextContainer;
use Workerman\Connection\ConnectionInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Connection\UdpConnection;
use Workerman\Protocols\ProtocolInterface;

/**
 * 链式协议处理
 * 这个是最核心的处理协议
 */
class ChainProtocol implements ProtocolInterface
{
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        $event = new ChainDataInputEvent($buffer, strlen($buffer), $connection);
        Container::getEventDispatcher($connection)?->dispatch($event);

        return $event->getLength();
    }

    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        Container::getLogger($connection)?->debug('接收到需要decode的数据', [
            'buffer' => $buffer,
            'connection' => $connection,
        ]);

        $length = strlen($buffer);

        // 记录初始收到的数据
        $decodeContext = new ChainDecodeContext();
        $decodeContext->initBuffer = $buffer;
        $decodeContext->lastBuffer = $buffer;
        ContextContainer::getInstance()->setContext($connection, $decodeContext);

        $event = new ChainDataDecodingEvent($buffer, $connection);
        Container::getEventDispatcher($connection)?->dispatch($event);

        $timer = new Timer();
        $timer->start();
        try {
            foreach (Container::getDecodeProtocols($connection) as $parser) {
                // decode数据
                // 每一层都做一次decode处理，这样子就能拿到最终解密后的数据
                // StrHelper::hexDump($connection, "{$parser}::decode - 前", $buffer);
                Container::getLogger($connection)?->debug("{$parser}开始decode", [
                    'connection' => $connection,
                ]);
                $buffer = self::handleProtocol($parser, $connection, $buffer);
                // 记录上次解密结果
                $decodeContext = ContextContainer::getInstance()->getContext($connection, ChainDecodeContext::class);
                assert($decodeContext instanceof ChainDecodeContext);
                $decodeContext->lastBuffer = $buffer;
                ContextContainer::getInstance()->setContext($connection, $decodeContext);
                Container::getLogger($connection)?->debug("{$parser}::decoded", [
                    'recv_buffer' => $buffer,
                    'connection' => $connection,
                ]);

                // TCP的话，可能在中途被断开了
                if ($connection instanceof TcpConnection && TcpConnection::STATUS_ESTABLISHED !== $connection->getStatus()) {
                    Container::getLogger($connection)?->warning('chain tcp is closed-1', [
                        'connection' => $connection,
                    ]);

                    return '';
                }

                if ('' === $buffer) {
                    // 还需要等待数据喔，这里不处理
                    return '';
                }
            }
        } finally {
            // 清理解码上下文
            ContextContainer::getInstance()->clearContext($connection, ChainDecodeContext::class);

            $duration = $timer->stop();
            Container::getLogger($connection)?->info('入站连接数据decode完成', [
                'duration' => $duration->asString(),
                'inbound' => $connection,
            ]);
        }

        // 解密完成，返回数据给上一层处理
        $event = new ChainDataDecodedEvent($buffer, $connection);
        Container::getEventDispatcher($connection)?->dispatch($event);

        return $event->getBuffer();
    }

    /**
     * 每一层都模拟一次 ProtocolInterface::input 和 ProtocolInterface::decode
     *
     * @param string|ProtocolInterface $protocol   哪一层协议
     * @param ConnectionInterface      $connection 连接
     * @param string                   $buffer     当前收到的数据
     *
     * @return string 可以传递给下一层的数据
     */
    private static function handleProtocol(string|ProtocolInterface $protocol, ConnectionInterface $connection, string $buffer): string
    {
        // UDP协议不存在拆包的可能
        if ($connection instanceof UdpConnection) {
            return self::handleUdpProtocol($protocol, $connection, $buffer);
        }

        return self::handleTcpProtocol($protocol, $connection, $buffer);
    }

    /**
     * 处理 UDP 协议
     */
    private static function handleUdpProtocol(string|ProtocolInterface $protocol, ConnectionInterface $connection, string $buffer): string
    {
        $protocol::input($buffer, $connection); // 这里总是模拟一次input

        return $protocol::decode($buffer, $connection);
    }

    /**
     * 处理 TCP 协议
     */
    private static function handleTcpProtocol(string|ProtocolInterface $protocol, ConnectionInterface $connection, string $buffer): string
    {
        $recvBuffersContext = self::getOrCreateBuffersContext($connection, $protocol);
        $protocolKey = self::getProtocolKey($protocol);

        // 结果
        $result = '';

        // 上次没处理完的数据，我们合并过来一起处理
        $buffer = $recvBuffersContext->buffers[$protocolKey] . $buffer;
        $recvBuffersContext->buffers[$protocolKey] = '';
        ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);

        while (strlen($buffer) > 0) {
            $processResult = self::processBuffer($protocol, $connection, $buffer, $result, $recvBuffersContext);

            if ($processResult['shouldReturn']) {
                return $processResult['result'];
            }

            $result = $processResult['result'];
            $buffer = $processResult['buffer'];
        }

        return $result;
    }

    /**
     * 获取或创建缓冲区上下文
     */
    private static function getOrCreateBuffersContext(ConnectionInterface $connection, string|ProtocolInterface $protocol): ProtocolRecvBuffersContext
    {
        $recvBuffersContext = ContextContainer::getInstance()->getContext($connection, ProtocolRecvBuffersContext::class);
        if (!$recvBuffersContext instanceof ProtocolRecvBuffersContext) {
            $recvBuffersContext = new ProtocolRecvBuffersContext();
            ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);
        }
        $protocolKey = self::getProtocolKey($protocol);
        if (!isset($recvBuffersContext->buffers[$protocolKey])) {
            $recvBuffersContext->buffers[$protocolKey] = '';
        }

        return $recvBuffersContext;
    }

    /**
     * 处理缓冲区数据
     *
     * @return array{shouldReturn: bool, result: string, buffer: string}
     */
    private static function processBuffer(
        string|ProtocolInterface $protocol,
        ConnectionInterface $connection,
        string $buffer,
        string $result,
        ProtocolRecvBuffersContext $recvBuffersContext,
    ): array {
        $requestLen = $protocol::input($buffer, $connection);
        $protocolName = self::getProtocolKey($protocol);
        Container::getLogger($connection)?->debug("{$protocolName} requestLen: {$requestLen}", [
            'connection' => $connection,
        ]);

        if (0 === $requestLen) {
            return self::handleWaitingForData($protocol, $connection, $buffer, $result, $recvBuffersContext);
        }

        $currentLen = strlen($buffer);
        if ($currentLen < $requestLen) {
            return self::handleInsufficientData($protocol, $connection, $buffer, $result, $recvBuffersContext, $requestLen, $currentLen);
        }

        return self::handleSufficientData($protocol, $connection, $buffer, $result, $requestLen);
    }

    /**
     * 处理等待数据的情况
     *
     * @return array{shouldReturn: bool, result: string, buffer: string}
     */
    private static function handleWaitingForData(
        string|ProtocolInterface $protocol,
        ConnectionInterface $connection,
        string $buffer,
        string $result,
        ProtocolRecvBuffersContext $recvBuffersContext,
    ): array {
        $protocolKey = self::getProtocolKey($protocol);
        $recvBuffersContext->buffers[$protocolKey] = $buffer;
        ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);
        Container::getLogger($connection)?->debug("{$protocolKey}还需要等待数据", [
            'connection' => $connection,
        ]);

        return ['shouldReturn' => true, 'result' => $result, 'buffer' => ''];
    }

    /**
     * 处理数据不足的情况
     *
     * @return array{shouldReturn: bool, result: string, buffer: string}
     */
    private static function handleInsufficientData(
        string|ProtocolInterface $protocol,
        ConnectionInterface $connection,
        string $buffer,
        string $result,
        ProtocolRecvBuffersContext $recvBuffersContext,
        int $requestLen,
        int $currentLen,
    ): array {
        $protocolKey = self::getProtocolKey($protocol);
        $recvBuffersContext->buffers[$protocolKey] = $buffer;
        ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);
        Container::getLogger($connection)?->info("{$protocolKey}期望数据长度{$requestLen}，实际只有{$currentLen}，waiting", [
            'resultLen' => strlen($result),
            'connection' => $connection,
        ]);

        return ['shouldReturn' => true, 'result' => $result, 'buffer' => ''];
    }

    /**
     * 处理数据充足的情况
     *
     * @return array{shouldReturn: bool, result: string, buffer: string}
     */
    private static function handleSufficientData(
        string|ProtocolInterface $protocol,
        ConnectionInterface $connection,
        string $buffer,
        string $result,
        int $requestLen,
    ): array {
        // 只要这部分数据喔，那我们截断
        $tmp = substr($buffer, 0, $requestLen);
        $result .= $protocol::decode($tmp, $connection);

        // 已经断开连接了，我们不处理
        if ($connection instanceof TcpConnection && in_array($connection->getStatus(), [TcpConnection::STATUS_CLOSING, TcpConnection::STATUS_CLOSED], true)) {
            Container::getLogger($connection)?->warning('chain tcp is closed-3', [
                'protocol' => $protocol,
                'connection' => $connection,
            ]);

            return ['shouldReturn' => true, 'result' => '', 'buffer' => ''];
        }

        $buffer = substr($buffer, $requestLen);

        return ['shouldReturn' => false, 'result' => $result, 'buffer' => $buffer];
    }

    public static function encode(mixed $data, ConnectionInterface $connection): string
    {
        Container::getLogger($connection)?->debug('准备encode数据', [
            'data' => $data,
            'connection' => $connection,
        ]);

        // 记录初始收到的数据
        $encodeContext = new ChainEncodeContext();
        $encodeContext->initBuffer = $data;
        $encodeContext->lastBuffer = $data;
        ContextContainer::getInstance()->setContext($connection, $encodeContext);

        $timer = new Timer();
        $timer->start();
        try {
            // encode数据时，我们只要一层层encode就好，最终能发送出去就OK
            foreach (Container::getEncodeProtocols($connection) as $parser) {
                Container::getLogger($connection)?->debug("{$parser} pre encode", [
                    'data' => $data,
                    'connection' => $connection,
                ]);

                $data = $parser::encode($data, $connection);

                Container::getLogger($connection)?->debug("{$parser} encoded", [
                    'data' => $data,
                    'connection' => $connection,
                ]);

                // TCP的话，可能在中途被断开了
                if ($connection instanceof TcpConnection && TcpConnection::STATUS_ESTABLISHED !== $connection->getStatus()) {
                    Container::getLogger($connection)?->info('chain tcp is closed-2', [
                        'connection' => $connection,
                    ]);

                    return '';
                }
            }
        } finally {
            // 清理编码上下文
            ContextContainer::getInstance()->clearContext($connection, ChainEncodeContext::class);

            $duration = $timer->stop();
            Container::getLogger($connection)?->info('出站连接数据encode完成', [
                'duration' => $duration->asString(),
                'length' => strlen($data),
                'inbound' => $connection,
            ]);
        }

        // Logger::info('返回处理后的数据，len:' . strlen($data), connection: $connection);

        $event = new ChainDataEncodedEvent($data, $connection);
        Container::getEventDispatcher($connection)?->dispatch($event);

        return $event->getBuffer();
    }

    /**
     * 将协议转换为字符串键，用于数组索引和日志输出
     */
    private static function getProtocolKey(string|ProtocolInterface $protocol): string
    {
        if (is_string($protocol)) {
            return $protocol;
        }

        return get_class($protocol);
    }
}
