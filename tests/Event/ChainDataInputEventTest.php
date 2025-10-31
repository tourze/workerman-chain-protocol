<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\Workerman\ChainProtocol\Event\ChainDataInputEvent;
use Workerman\Connection\ConnectionInterface;

/**
 * @internal
 */
#[CoversClass(ChainDataInputEvent::class)]
final class ChainDataInputEventTest extends AbstractEventTestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = self::createStub(ConnectionInterface::class);
    }

    public function testCanBeInstantiated(): void
    {
        $event = new ChainDataInputEvent('test buffer', 100, $this->connection);
        $this->assertInstanceOf(ChainDataInputEvent::class, $event);
    }

    public function testGetBuffer(): void
    {
        $buffer = 'input data';
        $event = new ChainDataInputEvent($buffer, 50, $this->connection);

        $this->assertEquals($buffer, $event->getBuffer());
    }

    public function testGetLength(): void
    {
        $length = 75;
        $event = new ChainDataInputEvent('test', $length, $this->connection);

        $this->assertEquals($length, $event->getLength());
    }

    public function testSetLength(): void
    {
        $event = new ChainDataInputEvent('test', 100, $this->connection);
        $newLength = 200;

        $event->setLength($newLength);
        $this->assertEquals($newLength, $event->getLength());
    }

    public function testGetConnection(): void
    {
        $event = new ChainDataInputEvent('test', 50, $this->connection);

        $this->assertSame($this->connection, $event->getConnection());
    }

    public function testIsEvent(): void
    {
        $event = new ChainDataInputEvent('test', 50, $this->connection);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testBufferIsReadonly(): void
    {
        $buffer = 'readonly buffer';
        $event = new ChainDataInputEvent($buffer, 50, $this->connection);

        $this->assertEquals($buffer, $event->getBuffer());
    }
}
