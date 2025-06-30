<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\ChainProtocol\Context\ChainDecodeContext;

class ChainDecodeContextTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $context = new ChainDecodeContext();
        $this->assertInstanceOf(ChainDecodeContext::class, $context);
    }

    public function testInitialInitBuffer(): void
    {
        $context = new ChainDecodeContext();
        $this->assertNull($context->initBuffer);
    }

    public function testInitialLastBuffer(): void
    {
        $context = new ChainDecodeContext();
        $this->assertNull($context->lastBuffer);
    }

    public function testCanSetInitBuffer(): void
    {
        $context = new ChainDecodeContext();
        $context->initBuffer = 'initial data';
        $this->assertEquals('initial data', $context->initBuffer);
    }

    public function testCanSetLastBuffer(): void
    {
        $context = new ChainDecodeContext();
        $context->lastBuffer = 'last data';
        $this->assertEquals('last data', $context->lastBuffer);
    }
}