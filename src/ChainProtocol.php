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

        $timer = new Timer;
        $timer->start();
        try {
            foreach (Container::getDecodeProtocols($connection) as $parser) {
                // decode数据
                // 每一层都做一次decode处理，这样子就能拿到最终解密后的数据
                //StrHelper::hexDump($connection, "{$parser}::decode - 前", $buffer);
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
                if ($connection instanceof TcpConnection && $connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                    Container::getLogger($connection)?->warning('chain tcp is closed-1', [
                        'connection' => $connection,
                    ]);
                    return '';
                }

                if ($buffer === '') {
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
     * @param string|ProtocolInterface $protocol 哪一层协议
     * @param ConnectionInterface $connection 连接
     * @param string $buffer 当前收到的数据
     * @return string 可以传递给下一层的数据
     */
    private static function handleProtocol(string|ProtocolInterface $protocol, ConnectionInterface $connection, string $buffer): string
    {
        // UDP协议不存在拆包的可能
        if ($connection instanceof UdpConnection) {
            $protocol::input($buffer, $connection); // 这里总是模拟一次input
            return $protocol::decode($buffer, $connection);
        }

        $recvBuffersContext = ContextContainer::getInstance()->getContext($connection, ProtocolRecvBuffersContext::class);
        if (!$recvBuffersContext instanceof ProtocolRecvBuffersContext) {
            $recvBuffersContext = new ProtocolRecvBuffersContext();
            ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);
        }
        if (!isset($recvBuffersContext->buffers[$protocol])) {
            $recvBuffersContext->buffers[$protocol] = '';
        }

        // 结果
        $result = '';

        // 上次没处理完的数据，我们合并过来一起处理
        $buffer = $recvBuffersContext->buffers[$protocol] . $buffer;
        $recvBuffersContext->buffers[$protocol] = '';
        ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);

        while (strlen($buffer) > 0) {
            $requestLen = $protocol::input($buffer, $connection);
            Container::getLogger($connection)?->debug("{$protocol} requestLen: {$requestLen}", [
                'connection' => $connection,
            ]);
            if ($requestLen === 0) {
                // 还需要等待数据
                $recvBuffersContext->buffers[$protocol] = $buffer;
                ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);
                Container::getLogger($connection)?->debug("{$protocol}还需要等待数据", [
                    'connection' => $connection,
                ]);
                return $result;
            }

            // 如果长度不够，那我们就等待新数据来了再说
            $currentLen = strlen($buffer);
            if ($currentLen < $requestLen) {
                $recvBuffersContext->buffers[$protocol] = $buffer;
                ContextContainer::getInstance()->setContext($connection, $recvBuffersContext);
                Container::getLogger($connection)?->info("{$protocol}期望数据长度{$requestLen}，实际只有{$currentLen}，waiting", [
                    'resultLen' => strlen($result),
                    'connection' => $connection,
                ]);
                return $result;
            }

            // 只要这部分数据喔，那我们截断
            $tmp = substr($buffer, 0, $requestLen);
            $result .= $protocol::decode($tmp, $connection);
            // 已经断开连接了，我们不处理
            if ($connection instanceof TcpConnection && in_array($connection->getStatus(), [TcpConnection::STATUS_CLOSING, TcpConnection::STATUS_CLOSED])) {
                Container::getLogger($connection)?->warning('chain tcp is closed-3', [
                    'protocol' => $protocol,
                    'connection' => $connection,
                ]);
                return '';
            }
            $buffer = substr($buffer, $requestLen);
        }

        return $result;
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

        $timer = new Timer;
        $timer->start();
        try {
            // encode数据时，我们只要一层层encode就好，最终能发送出去就OK
            foreach (Container::getEncodeProtocols($connection) as $parser) {
                Container::getLogger($connection)?->debug("$parser pre encode", [
                    'data' => $data,
                    'connection' => $connection,
                ]);

                $data = $parser::encode($data, $connection);

                Container::getLogger($connection)?->debug("$parser encoded", [
                    'data' => $data,
                    'connection' => $connection,
                ]);

                // TCP的话，可能在中途被断开了
                if ($connection instanceof TcpConnection && $connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
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

        //Logger::info('返回处理后的数据，len:' . strlen($data), connection: $connection);

        $event = new ChainDataEncodedEvent($data, $connection);
        Container::getEventDispatcher($connection)?->dispatch($event);
        return $event->getBuffer();
    }
}
