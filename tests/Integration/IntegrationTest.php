<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\Workerman\ChainProtocol\ChainProtocol;
use Tourze\Workerman\ChainProtocol\Container;
use Tourze\Workerman\ChainProtocol\Event\ChainDataEncodedEvent;
use Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockFirstProtocol;
use Tourze\Workerman\ChainProtocol\Tests\Integration\Protocol\MockSecondProtocol;
use Workerman\Connection\TcpConnection;

/**
 * 集成测试
 */
class IntegrationTest extends TestCase
{
    protected EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        // 清理容器
        Container::$logger = null;
        Container::$eventDispatcher = null;
        Container::$decodeProtocols = [];
        Container::$encodeProtocols = [];

        // 设置事件分发器
        $this->eventDispatcher = new EventDispatcher();
        Container::$eventDispatcher = $this->eventDispatcher;
    }

    /**
     * 测试多协议链处理
     */
    public function testMultipleProtocolChain(): void
    {
        // 创建模拟连接和日志
        $logger = $this->createMock(LoggerInterface::class);
        $connection = $this->createStub(TcpConnection::class);
        $connection->method('getStatus')->willReturn(TcpConnection::STATUS_ESTABLISHED);

        // 设置容器
        Container::$logger = $logger;
        Container::$decodeProtocols = [
            MockFirstProtocol::class,
            MockSecondProtocol::class,
        ];
        Container::$encodeProtocols = [
            MockSecondProtocol::class,
            MockFirstProtocol::class,
        ];

        // 测试解码流程
        $decodedData = ChainProtocol::decode('test-data', $connection);
        $this->assertEquals('second-protocol:first-protocol:test-data', $decodedData);

        // 测试编码流程
        $encodedData = ChainProtocol::encode('response-data', $connection);
        $this->assertEquals('first-protocol:second-protocol:response-data', $encodedData);
    }

    /**
     * 测试事件监听
     */
    public function testEventListening(): void
    {
        // 创建模拟连接
        $connection = $this->createStub(TcpConnection::class);
        $connection->method('getStatus')->willReturn(TcpConnection::STATUS_ESTABLISHED);

        // 设置协议链
        Container::$encodeProtocols = [MockFirstProtocol::class];

        // 收集事件数据
        $capturedData = null;
        $this->eventDispatcher->addListener(ChainDataEncodedEvent::class, function (ChainDataEncodedEvent $event) use (&$capturedData) {
            $capturedData = $event->getBuffer();
            // 修改返回值
            $event->setBuffer('modified-' . $capturedData);
        });

        // 执行编码
        $result = ChainProtocol::encode('event-test', $connection);

        // 验证事件监听器被调用
        $this->assertEquals('first-protocol:event-test', $capturedData);
        // 验证事件监听器修改了返回值
        $this->assertEquals('modified-first-protocol:event-test', $result);
    }

    /**
     * 测试实际使用场景
     */
    public function testRealWorldScenario(): void
    {
        // 创建模拟TcpConnection
        $connection = $this->createStub(TcpConnection::class);
        $connection->method('getStatus')->willReturn(TcpConnection::STATUS_ESTABLISHED);

        // 设置协议链
        Container::$decodeProtocols = [
            MockFirstProtocol::class,
            MockSecondProtocol::class,
        ];
        Container::$encodeProtocols = [
            MockSecondProtocol::class,
            MockFirstProtocol::class,
        ];

        // 模拟接收到的数据包
        $input = ChainProtocol::input('raw-packet-data', $connection);
        $this->assertEquals(15, $input); // 长度应该等于原始数据长度

        // 模拟解码过程
        $decoded = ChainProtocol::decode('raw-packet-data', $connection);
        $this->assertEquals('second-protocol:first-protocol:raw-packet-data', $decoded);

        // 模拟业务处理，生成响应
        $response = "response-for-$decoded";

        // 模拟编码过程
        $encoded = ChainProtocol::encode($response, $connection);
        $this->assertEquals(
            'first-protocol:second-protocol:response-for-second-protocol:first-protocol:raw-packet-data',
            $encoded
        );
    }
}
