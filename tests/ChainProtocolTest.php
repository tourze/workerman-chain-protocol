<?php

namespace Tourze\Workerman\ChainProtocol\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\Workerman\ChainProtocol\ChainProtocol;
use Tourze\Workerman\ChainProtocol\Container;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodedEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodingEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataEncodedEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataInputEvent;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\Frame;
use Workerman\Protocols\ProtocolInterface;
use Workerman\Protocols\Text;

/**
 * @internal
 */
#[CoversClass(ChainProtocol::class)]
final class ChainProtocolTest extends TestCase
{
    /**
     * 测试 Container 的 getter 和 setter
     */
    public function testContainerGetters(): void
    {
        // 创建模拟对象
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置静态属性
        Container::$logger = $logger;
        Container::$eventDispatcher = $dispatcher;
        Container::$decodeProtocols = [Text::class, Frame::class];
        Container::$encodeProtocols = [Frame::class, Text::class];

        // 验证 getter 方法
        $this->assertSame($logger, Container::getLogger($connection));
        $this->assertSame($dispatcher, Container::getEventDispatcher($connection));
        $this->assertSame([Text::class, Frame::class], Container::getDecodeProtocols($connection));
        $this->assertSame([Frame::class, Text::class], Container::getEncodeProtocols($connection));
    }

    /**
     * 测试 input 方法触发事件
     */
    public function testInput(): void
    {
        // 创建模拟对象
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置期望
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($event) use ($connection) {
                return $event instanceof ChainDataInputEvent
                    && 'test-data' === $event->getBuffer()
                    && 9 === $event->getLength()
                    && $event->getConnection() === $connection;
            }))
            ->willReturnArgument(0)
        ;

        // 设置静态属性
        Container::$eventDispatcher = $dispatcher;

        // 调用并验证
        $result = ChainProtocol::input('test-data', $connection);
        $this->assertEquals(9, $result);
    }

    /**
     * 测试自定义 input 长度的情况
     */
    public function testInputWithCustomLength(): void
    {
        // 创建模拟对象和事件分发器
        $dispatcher = new EventDispatcher();
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置事件监听器，修改长度
        $dispatcher->addListener(ChainDataInputEvent::class, function (ChainDataInputEvent $event): void {
            $event->setLength(5); // 只处理前5个字符
        });

        // 设置静态属性
        Container::$eventDispatcher = $dispatcher;

        // 调用并验证
        $result = ChainProtocol::input('test-data', $connection);
        $this->assertEquals(5, $result);
    }

    /**
     * 测试解码方法的基本功能
     */
    public function testDecode(): void
    {
        // 创建模拟对象
        $logger = $this->createMock(LoggerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);

        // 创建一个简单的模拟协议解析器类
        $mockProtocol = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer);
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return "decoded:{$buffer}";
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return "encoded:{$buffer}";
            }
        };

        // 设置静态属性
        Container::$logger = $logger;
        Container::$decodeProtocols = [get_class($mockProtocol)];

        // 调用并验证
        $result = ChainProtocol::decode('test-data', $connection);
        $this->assertEquals('decoded:test-data', $result);
    }

    /**
     * 测试多层解码链
     */
    public function testDecodeMultipleProtocols(): void
    {
        // 创建测试协议类
        $mockProtocol1 = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer);
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return "protocol1:{$buffer}";
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }
        };

        $mockProtocol2 = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer);
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return "protocol2:{$buffer}";
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }
        };

        // 创建模拟连接对象
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置解码链
        Container::$decodeProtocols = [
            get_class($mockProtocol1),
            get_class($mockProtocol2),
        ];

        // 调用并验证 (先应用protocol1，再应用protocol2)
        $result = ChainProtocol::decode('test-data', $connection);
        $this->assertEquals('protocol2:protocol1:test-data', $result);
    }

    /**
     * 测试编码方法的基本功能
     */
    public function testEncode(): void
    {
        // 创建模拟对象
        $logger = $this->createMock(LoggerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);

        // 创建一个简单的模拟协议解析器类
        $mockProtocol = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer);
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return "encoded:{$buffer}";
            }
        };

        // 设置静态属性
        Container::$logger = $logger;
        Container::$encodeProtocols = [get_class($mockProtocol)];

        // 调用并验证
        $result = ChainProtocol::encode('test-data', $connection);
        $this->assertEquals('encoded:test-data', $result);
    }

    /**
     * 测试多层编码链
     */
    public function testEncodeMultipleProtocols(): void
    {
        // 创建测试协议类
        $mockProtocol1 = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer);
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return "protocol1:{$buffer}";
            }
        };

        $mockProtocol2 = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer);
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return "protocol2:{$buffer}";
            }
        };

        // 创建模拟连接对象
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置编码链
        Container::$encodeProtocols = [
            get_class($mockProtocol1),
            get_class($mockProtocol2),
        ];

        // 调用并验证 (先应用protocol1，再应用protocol2)
        $result = ChainProtocol::encode('test-data', $connection);
        $this->assertEquals('protocol2:protocol1:test-data', $result);
    }

    /**
     * 测试事件分发
     */
    public function testEventDispatching(): void
    {
        // 创建一个实际的事件分发器
        $dispatcher = new EventDispatcher();
        $connection = $this->createMock(ConnectionInterface::class);

        // 标志变量，用于检查事件是否被触发
        $decodingTriggered = false;
        $decodedTriggered = false;
        $encodedTriggered = false;

        // 添加事件监听器
        $dispatcher->addListener(ChainDataDecodingEvent::class, function (ChainDataDecodingEvent $event) use (&$decodingTriggered): void {
            $decodingTriggered = true;
        });

        $dispatcher->addListener(ChainDataDecodedEvent::class, function (ChainDataDecodedEvent $event) use (&$decodedTriggered): void {
            $decodedTriggered = true;
        });

        $dispatcher->addListener(ChainDataEncodedEvent::class, function (ChainDataEncodedEvent $event) use (&$encodedTriggered): void {
            $encodedTriggered = true;
        });

        // 设置静态属性
        Container::$eventDispatcher = $dispatcher;
        Container::$decodeProtocols = []; // 空的协议链
        Container::$encodeProtocols = []; // 空的协议链

        // 调用方法
        ChainProtocol::decode('test', $connection);
        ChainProtocol::encode('test', $connection);

        // 验证事件是否被触发
        $this->assertTrue($decodingTriggered, '解码开始事件未被触发');
        $this->assertTrue($decodedTriggered, '解码完成事件未被触发');
        $this->assertTrue($encodedTriggered, '编码完成事件未被触发');
    }

    /**
     * 测试TCP连接处理
     */
    public function testTcpConnectionHandling(): void
    {
        // 创建测试协议类
        $mockProtocol = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return 4; // 只处理前4个字符
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return "decoded:{$buffer}";
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }
        };

        // 创建模拟连接对象
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置解码链
        Container::$decodeProtocols = [get_class($mockProtocol)];

        // 准备一个比协议输入长度更长的数据
        $result = ChainProtocol::decode('testmorethanapacket', $connection);

        // 验证结果，应该处理了多个包
        $this->assertEquals('decoded:testdecoded:moredecoded:thandecoded:apac', $result);
    }

    /**
     * 测试UDP连接处理
     */
    public function testUdpConnectionHandling(): void
    {
        // 创建测试协议类
        $mockProtocol = new class implements ProtocolInterface {
            public static function input(string $buffer, ConnectionInterface $connection): int
            {
                return strlen($buffer); // UDP 处理整个包
            }

            public static function decode(string $buffer, ConnectionInterface $connection): string
            {
                return "udp-decoded:{$buffer}";
            }

            public static function encode($buffer, ConnectionInterface $connection): string
            {
                return $buffer;
            }
        };

        // 创建模拟连接对象
        $connection = $this->createMock(ConnectionInterface::class);

        // 设置解码链
        Container::$decodeProtocols = [get_class($mockProtocol)];

        // 测试UDP数据包处理
        $result = ChainProtocol::decode('udppacket', $connection);

        // 验证结果，应该是单个处理的UDP包
        $this->assertEquals('udp-decoded:udppacket', $result);
    }
}
