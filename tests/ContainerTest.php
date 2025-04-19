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
        Container::$decodeProtocols = ['Protocol1', 'Protocol2'];

        $connection = $this->createStub(TcpConnection::class);
        $this->assertEquals(['Protocol1', 'Protocol2'], Container::getDecodeProtocols($connection));
    }

    /**
     * 测试编码协议读写
     */
    public function testEncodeProtocolsAccessors(): void
    {
        Container::$encodeProtocols = ['Protocol2', 'Protocol1'];

        $connection = $this->createStub(TcpConnection::class);
        $this->assertEquals(['Protocol2', 'Protocol1'], Container::getEncodeProtocols($connection));
    }

    /**
     * 测试协议链顺序
     */
    public function testProtocolOrder(): void
    {
        // 测试解码和编码协议的顺序
        Container::$decodeProtocols = ['First', 'Second', 'Third'];
        Container::$encodeProtocols = ['Third', 'Second', 'First'];

        $connection = $this->createStub(TcpConnection::class);

        // 验证解码顺序
        $decodeProtocols = Container::getDecodeProtocols($connection);
        $this->assertEquals('First', $decodeProtocols[0]);
        $this->assertEquals('Second', $decodeProtocols[1]);
        $this->assertEquals('Third', $decodeProtocols[2]);

        // 验证编码顺序
        $encodeProtocols = Container::getEncodeProtocols($connection);
        $this->assertEquals('Third', $encodeProtocols[0]);
        $this->assertEquals('Second', $encodeProtocols[1]);
        $this->assertEquals('First', $encodeProtocols[2]);
    }
}
