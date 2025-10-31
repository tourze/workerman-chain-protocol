<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodedEvent;
use Workerman\Connection\ConnectionInterface;

/**
 * @internal
 */
#[CoversClass(ChainDataDecodedEvent::class)]
final class ChainDataDecodedEventTest extends AbstractEventTestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = self::createStub(ConnectionInterface::class);
    }

    public function testCanBeInstantiated(): void
    {
        $event = new ChainDataDecodedEvent('test buffer', $this->connection);
        $this->assertInstanceOf(ChainDataDecodedEvent::class, $event);
    }

    public function testGetBuffer(): void
    {
        $buffer = 'decoded data';
        $event = new ChainDataDecodedEvent($buffer, $this->connection);

        $this->assertEquals($buffer, $event->getBuffer());
    }

    public function testSetBuffer(): void
    {
        $event = new ChainDataDecodedEvent('initial', $this->connection);
        $newBuffer = 'modified buffer';

        $event->setBuffer($newBuffer);
        $this->assertEquals($newBuffer, $event->getBuffer());
    }

    public function testGetConnection(): void
    {
        $event = new ChainDataDecodedEvent('test', $this->connection);

        $this->assertSame($this->connection, $event->getConnection());
    }

    public function testIsEvent(): void
    {
        $event = new ChainDataDecodedEvent('test', $this->connection);

        $this->assertInstanceOf(Event::class, $event);
    }
}
