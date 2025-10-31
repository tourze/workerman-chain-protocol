# Workerman 链式协议

[![最新版本](https://img.shields.io/packagist/v/tourze/workerman-chain-protocol.svg?style=flat-square)]
(https://packagist.org/packages/tourze/workerman-chain-protocol)
[![PHP 版本](https://img.shields.io/packagist/php-v/tourze/workerman-chain-protocol.svg?style=flat-square)]
(composer.json)
[![许可证](https://img.shields.io/github/license/tourze/php-monorepo.svg?style=flat-square)]
(LICENSE)
[![构建状态](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/php-monorepo/actions)
[![代码覆盖率](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)

[English](README.md) | [中文](README.zh-CN.md)

Workerman的链式协议处理库。该包允许您创建一系列协议解析器，
以在Workerman连接中解码和编码数据。

## 功能特性

- Workerman的链式协议处理
- 协议无关，适用于任何实现了Workerman的ProtocolInterface的协议
- 支持TCP和UDP连接
- 基于Symfony Event Dispatcher的事件驱动架构
- 全面的日志记录
- 使用PHP Timer进行性能监控

## 安装

```bash
composer require tourze/workerman-chain-protocol
```

## 快速开始

```php
<?php

use Tourze\Workerman\ChainProtocol\ChainProtocol;
use Tourze\Workerman\ChainProtocol\Container;
use Workerman\Worker;

// 设置自定义协议类（这些类必须实现ProtocolInterface）
Container::$decodeProtocols = [
    MyProtocol1::class, 
    MyProtocol2::class,
    // 根据需要添加更多协议
];

Container::$encodeProtocols = [
    MyProtocol2::class,
    MyProtocol1::class,
    // 编码顺序很重要（与解码相反）
];

// 如有需要，设置日志记录器
Container::$logger = new MyLogger();

// 如有需要，设置事件分发器
Container::$eventDispatcher = new MyEventDispatcher();

// 创建使用ChainProtocol的Workerman服务器
$worker = new Worker('tcp://0.0.0.0:8080');
$worker->protocol = ChainProtocol::class;

$worker->onMessage = function($connection, $data) {
    // $data已经通过链中的所有协议解码
    // 在这里处理您的业务逻辑

    // 发送响应，响应将通过链中的所有协议编码
    $connection->send('您的响应');
};

Worker::runAll();
```

## 依赖组件

本包需要：

- PHP 8.1 或更高版本
- workerman/workerman ^5.1
- symfony/event-dispatcher ^7.3
- symfony/event-dispatcher-contracts ^3
- tourze/workerman-connection-context 0.0.*
- psr/log ^1|^2|^3
- phpunit/php-timer ^7.0

## 高级用法

### 自定义协议实现

```php
<?php

use Workerman\Protocols\ProtocolInterface;
use Workerman\Connection\ConnectionInterface;

class MyCustomProtocol implements ProtocolInterface
{
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        // 返回完整数据包的长度，或者如果需要更多数据则返回0
        return strlen($buffer) >= 4 ? unpack('N', substr($buffer, 0, 4))[1] + 4 : 0;
    }

    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        // 移除长度头并返回实际数据
        return substr($buffer, 4);
    }

    public static function encode(mixed $data, ConnectionInterface $connection): string
    {
        // 为数据添加长度头
        return pack('N', strlen($data)) . $data;
    }
}
```

### 事件处理

```php
<?php

use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodedEvent;
use Tourze\Workerman\ChainProtocol\Container;

$eventDispatcher = new EventDispatcher();

$eventDispatcher->addListener(ChainDataDecodedEvent::class, function(ChainDataDecodedEvent $event) {
    // 记录或处理解码后的数据
    error_log('数据已解码: ' . $event->getBuffer());
});

Container::$eventDispatcher = $eventDispatcher;
```

### 自定义日志配置

```php
<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tourze\Workerman\ChainProtocol\Container;

$logger = new Logger('chain-protocol');
$logger->pushHandler(new StreamHandler('path/to/logfile.log', Logger::DEBUG));

Container::$logger = $logger;
```

## 贡献

请参阅[CONTRIBUTING.md](CONTRIBUTING.md)了解详情。

## 开源协议

MIT许可证。详情请参阅[许可证文件](LICENSE)。
