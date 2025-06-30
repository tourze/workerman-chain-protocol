<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodingEvent;
use Workerman\Connection\ConnectionInterface;

class ChainDataDecodingEventTest extends TestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createStub(ConnectionInterface::class);
    }

    public function testCanBeInstantiated(): void
    {
        $event = new ChainDataDecodingEvent('test buffer', $this->connection);
        $this->assertInstanceOf(ChainDataDecodingEvent::class, $event);
    }

    public function testGetBuffer(): void
    {
        $buffer = 'decoding data';
        $event = new ChainDataDecodingEvent($buffer, $this->connection);
        
        $this->assertEquals($buffer, $event->getBuffer());
    }

    public function testSetBuffer(): void
    {
        $event = new ChainDataDecodingEvent('initial', $this->connection);
        $newBuffer = 'updated buffer';
        
        $event->setBuffer($newBuffer);
        $this->assertEquals($newBuffer, $event->getBuffer());
    }

    public function testGetConnection(): void
    {
        $event = new ChainDataDecodingEvent('test', $this->connection);
        
        $this->assertSame($this->connection, $event->getConnection());
    }

    public function testIsEvent(): void
    {
        $event = new ChainDataDecodingEvent('test', $this->connection);
        
        $this->assertInstanceOf(\Symfony\Contracts\EventDispatcher\Event::class, $event);
    }
}