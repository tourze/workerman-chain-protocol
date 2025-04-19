<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Event;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodedEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodingEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataEncodedEvent;
use Tourze\Workerman\ChainProtocol\Event\ChainDataInputEvent;
use Workerman\Connection\ConnectionInterface;

class ChainDataEventTest extends TestCase
{
    /**
     * 测试 ChainDataInputEvent
     */
    public function testChainDataInputEvent(): void
    {
        // 创建模拟连接
        $connection = $this->createMock(ConnectionInterface::class);

        // 创建事件
        $event = new ChainDataInputEvent('test-buffer', 10, $connection);

        // 验证初始值
        $this->assertEquals('test-buffer', $event->getBuffer());
        $this->assertEquals(10, $event->getLength());
        $this->assertSame($connection, $event->getConnection());

        // 测试设置长度
        $event->setLength(20);
        $this->assertEquals(20, $event->getLength());
    }

    /**
     * 测试 ChainDataDecodingEvent
     */
    public function testChainDataDecodingEvent(): void
    {
        // 创建模拟连接
        $connection = $this->createMock(ConnectionInterface::class);

        // 创建事件
        $event = new ChainDataDecodingEvent('test-buffer', $connection);

        // 验证初始值
        $this->assertEquals('test-buffer', $event->getBuffer());
        $this->assertSame($connection, $event->getConnection());

        // 测试设置buffer
        $event->setBuffer('new-buffer');
        $this->assertEquals('new-buffer', $event->getBuffer());
    }

    /**
     * 测试 ChainDataDecodedEvent
     */
    public function testChainDataDecodedEvent(): void
    {
        // 创建模拟连接
        $connection = $this->createMock(ConnectionInterface::class);

        // 创建事件
        $event = new ChainDataDecodedEvent('test-buffer', $connection);

        // 验证初始值
        $this->assertEquals('test-buffer', $event->getBuffer());
        $this->assertSame($connection, $event->getConnection());

        // 测试设置buffer
        $event->setBuffer('decoded-buffer');
        $this->assertEquals('decoded-buffer', $event->getBuffer());
    }

    /**
     * 测试 ChainDataEncodedEvent
     */
    public function testChainDataEncodedEvent(): void
    {
        // 创建模拟连接
        $connection = $this->createMock(ConnectionInterface::class);

        // 创建事件
        $event = new ChainDataEncodedEvent('test-buffer', $connection);

        // 验证初始值
        $this->assertEquals('test-buffer', $event->getBuffer());
        $this->assertSame($connection, $event->getConnection());

        // 测试设置buffer
        $event->setBuffer('encoded-buffer');
        $this->assertEquals('encoded-buffer', $event->getBuffer());
    }
}
