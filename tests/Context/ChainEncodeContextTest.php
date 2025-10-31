<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\Workerman\ChainProtocol\Context\ChainEncodeContext;

/**
 * @internal
 */
#[CoversClass(ChainEncodeContext::class)]
final class ChainEncodeContextTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $context = new ChainEncodeContext();
        $this->assertInstanceOf(ChainEncodeContext::class, $context);
    }

    public function testInitialInitBuffer(): void
    {
        $context = new ChainEncodeContext();
        $this->assertNull($context->initBuffer);
    }

    public function testInitialLastBuffer(): void
    {
        $context = new ChainEncodeContext();
        $this->assertNull($context->lastBuffer);
    }

    public function testCanSetInitBuffer(): void
    {
        $context = new ChainEncodeContext();
        $context->initBuffer = 'initial encoded data';
        $this->assertEquals('initial encoded data', $context->initBuffer);
    }

    public function testCanSetLastBuffer(): void
    {
        $context = new ChainEncodeContext();
        $context->lastBuffer = 'last encoded data';
        $this->assertEquals('last encoded data', $context->lastBuffer);
    }
}
