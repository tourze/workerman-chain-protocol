<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\Workerman\ChainProtocol\Event\ChainDataEncodedEvent;
use Workerman\Connection\ConnectionInterface;

/**
 * @internal
 */
#[CoversClass(ChainDataEncodedEvent::class)]
final class ChainDataEncodedEventTest extends AbstractEventTestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = self::createStub(ConnectionInterface::class);
    }

    public function testCanBeInstantiated(): void
    {
        $event = new ChainDataEncodedEvent('test buffer', $this->connection);
        $this->assertInstanceOf(ChainDataEncodedEvent::class, $event);
    }

    public function testGetBuffer(): void
    {
        $buffer = 'encoded data';
        $event = new ChainDataEncodedEvent($buffer, $this->connection);

        $this->assertEquals($buffer, $event->getBuffer());
    }

    public function testSetBuffer(): void
    {
        $event = new ChainDataEncodedEvent('initial', $this->connection);
        $newBuffer = 'modified encoded data';

        $event->setBuffer($newBuffer);
        $this->assertEquals($newBuffer, $event->getBuffer());
    }

    public function testGetConnection(): void
    {
        $event = new ChainDataEncodedEvent('test', $this->connection);

        $this->assertSame($this->connection, $event->getConnection());
    }

    public function testIsEvent(): void
    {
        $event = new ChainDataEncodedEvent('test', $this->connection);

        $this->assertInstanceOf(Event::class, $event);
    }
}
