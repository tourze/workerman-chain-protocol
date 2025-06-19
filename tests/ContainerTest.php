<?php

namespace Tourze\Workerman\ChainProtocol\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\Workerman\ChainProtocol\Container;
use Workerman\Connection\ConnectionInterface;
use Workerman\Connection\TcpConnection;

/**
 * Container 类的测试
 */
class ContainerTest extends TestCase
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

        $logger = $this->createStub(LoggerInterface::class);
        Container::$logger = $logger;

        $connection = $this->createStub(ConnectionInterface::class);
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

        $connection = $this->createStub(ConnectionInterface::class);
        $this->assertSame($dispatcher, Container::getEventDispatcher($connection));
    }

    /**
     * 测试解码协议读写
     */
    public function testDecodeProtocolsAccessors(): void
    {
        Container::$decodeProtocols = [\Workerman\Protocols\Text::class, \Workerman\Protocols\Frame::class];

        $connection = $this->createStub(TcpConnection::class);
        $this->assertEquals([\Workerman\Protocols\Text::class, \Workerman\Protocols\Frame::class], Container::getDecodeProtocols($connection));
    }

    /**
     * 测试编码协议读写
     */
    public function testEncodeProtocolsAccessors(): void
    {
        Container::$encodeProtocols = [\Workerman\Protocols\Frame::class, \Workerman\Protocols\Text::class];

        $connection = $this->createStub(TcpConnection::class);
        $this->assertEquals([\Workerman\Protocols\Frame::class, \Workerman\Protocols\Text::class], Container::getEncodeProtocols($connection));
    }

    /**
     * 测试协议链顺序
     */
    public function testProtocolOrder(): void
    {
        // 测试解码和编码协议的顺序
        Container::$decodeProtocols = [
            \Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockFirstProtocol::class,
            \Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockSecondProtocol::class,
            \Workerman\Protocols\Text::class
        ];
        Container::$encodeProtocols = [
            \Workerman\Protocols\Text::class,
            \Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockSecondProtocol::class,
            \Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockFirstProtocol::class
        ];

        $connection = $this->createStub(TcpConnection::class);

        // 验证解码顺序
        $decodeProtocols = Container::getDecodeProtocols($connection);
        $this->assertEquals(\Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockFirstProtocol::class, $decodeProtocols[0]);
        $this->assertEquals(\Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockSecondProtocol::class, $decodeProtocols[1]);
        $this->assertEquals(\Workerman\Protocols\Text::class, $decodeProtocols[2]);

        // 验证编码顺序
        $encodeProtocols = Container::getEncodeProtocols($connection);
        $this->assertEquals(\Workerman\Protocols\Text::class, $encodeProtocols[0]);
        $this->assertEquals(\Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockSecondProtocol::class, $encodeProtocols[1]);
        $this->assertEquals(\Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockFirstProtocol::class, $encodeProtocols[2]);
    }
}
