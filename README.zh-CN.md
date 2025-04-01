# Workerman 链式协议

[![最新版本](https://img.shields.io/packagist/v/tourze/workerman-chain-protocol.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-chain-protocol)
[![开源协议](https://img.shields.io/github/license/tourze/php-monorepo.svg?style=flat-square)](LICENSE)

[English](README.md) | [中文](README.zh-CN.md)

Workerman的链式协议处理库。该包允许您创建一系列协议解析器，以在Workerman连接中解码和编码数据。

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

## 贡献

请参阅[CONTRIBUTING.md](CONTRIBUTING.md)了解详情。

## 开源协议

MIT许可证。详情请参阅[许可证文件](LICENSE)。
