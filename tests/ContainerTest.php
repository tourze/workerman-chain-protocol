<?php

namespace Tourze\Workerman\ChainProtocol\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\Workerman\ChainProtocol\Container;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\Frame;
use Workerman\Protocols\Text;

/**
 * @internal
 */
#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 清理测试前的静态属性
        Container::$logger = null;
        Container::$eventDispatcher = null;
        Container::$decodeProtocols = [];
        Container::$encodeProtocols = [];
    }

    /**
     * 测试日志读写
     */
    public function testLoggerAccessors(): void
    {
        $this->assertNull(Container::getLogger());

        $logger = self::createStub(LoggerInterface::class);
        Container::$logger = $logger;

        $connection = self::createStub(ConnectionInterface::class);
        $this->assertSame($logger, Container::getLogger($connection));
    }

    /**
     * 测试事件分发器读写
     */
    public function testEventDispatcherAccessors(): void
    {
        $this->assertNull(Container::getEventDispatcher());

        $dispatcher = new EventDispatcher();
        Container::$eventDispatcher = $dispatcher;

        $connection = self::createStub(ConnectionInterface::class);
        $this->assertSame($dispatcher, Container::getEventDispatcher($connection));
    }

    /**
     * 测试解码协议读写
     */
    public function testDecodeProtocolsAccessors(): void
    {
        Container::$decodeProtocols = [Text::class, Frame::class];

        $connection = self::createStub(ConnectionInterface::class);
        $this->assertEquals([Text::class, Frame::class], Container::getDecodeProtocols($connection));
    }

    /**
     * 测试编码协议读写
     */
    public function testEncodeProtocolsAccessors(): void
    {
        Container::$encodeProtocols = [Frame::class, Text::class];

        $connection = self::createStub(ConnectionInterface::class);
        $this->assertEquals([Frame::class, Text::class], Container::getEncodeProtocols($connection));
    }

    /**
     * 测试协议链顺序
     */
    public function testProtocolOrder(): void
    {
        // 测试解码和编码协议的顺序
        Container::$decodeProtocols = [
            Integration\Protocol\MockFirstProtocol::class,
            Integration\Protocol\MockSecondProtocol::class,
            Text::class,
        ];
        Container::$encodeProtocols = [
            Text::class,
            Integration\Protocol\MockSecondProtocol::class,
            Integration\Protocol\MockFirstProtocol::class,
        ];

        $connection = self::createStub(ConnectionInterface::class);

        // 验证解码顺序
        $decodeProtocols = Container::getDecodeProtocols($connection);
        $this->assertEquals(Integration\Protocol\MockFirstProtocol::class, $decodeProtocols[0]);
        $this->assertEquals(Integration\Protocol\MockSecondProtocol::class, $decodeProtocols[1]);
        $this->assertEquals(Text::class, $decodeProtocols[2]);

        // 验证编码顺序
        $encodeProtocols = Container::getEncodeProtocols($connection);
        $this->assertEquals(Text::class, $encodeProtocols[0]);
        $this->assertEquals(Integration\Protocol\MockSecondProtocol::class, $encodeProtocols[1]);
        $this->assertEquals(Integration\Protocol\MockFirstProtocol::class, $encodeProtocols[2]);
    }
}
