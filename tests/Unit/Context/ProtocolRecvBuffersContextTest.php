<?php

namespace Tourze\Workerman\ChainProtocol\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Tourze\Workerman\ChainProtocol\Context\ProtocolRecvBuffersContext;

class ProtocolRecvBuffersContextTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $context = new ProtocolRecvBuffersContext();
        $this->assertInstanceOf(ProtocolRecvBuffersContext::class, $context);
    }

    public function testInitialBuffers(): void
    {
        $context = new ProtocolRecvBuffersContext();
        $this->assertEquals([], $context->buffers);
    }

    public function testCanSetBuffers(): void
    {
        $context = new ProtocolRecvBuffersContext();
        $context->buffers = ['protocol1' => 'buffer1', 'protocol2' => 'buffer2'];
        
        $this->assertEquals(['protocol1' => 'buffer1', 'protocol2' => 'buffer2'], $context->buffers);
        $this->assertEquals('buffer1', $context->buffers['protocol1']);
        $this->assertEquals('buffer2', $context->buffers['protocol2']);
    }

    public function testCanModifyBuffers(): void
    {
        $context = new ProtocolRecvBuffersContext();
        $context->buffers['test'] = 'test data';
        
        $this->assertCount(1, $context->buffers);
        $this->assertEquals('test data', $context->buffers['test']);
    }
}