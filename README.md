# Workerman Chain Protocol

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-chain-protocol.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-chain-protocol)
[![License](https://img.shields.io/github/license/tourze/php-monorepo.svg?style=flat-square)](LICENSE)

[English](README.md) | [中文](README.zh-CN.md)

Chain protocol processing for Workerman. This package allows you to create a chain of protocol parsers to decode and encode data in Workerman connections.

## Features

- Chain-style protocol processing for Workerman
- Protocol-agnostic, works with any protocol that implements Workerman's ProtocolInterface
- Support for both TCP and UDP connections
- Event-driven architecture with Symfony Event Dispatcher
- Comprehensive logging
- Performance monitoring using PHP Timer

## Installation

```bash
composer require tourze/workerman-chain-protocol
```

## Quick Start

```php
<?php

use Tourze\Workerman\ChainProtocol\ChainProtocol;
use Tourze\Workerman\ChainProtocol\Container;
use Workerman\Worker;

// Setup your custom protocol classes that implement ProtocolInterface
Container::$decodeProtocols = [
    MyProtocol1::class, 
    MyProtocol2::class,
    // Add more protocols as needed
];

Container::$encodeProtocols = [
    MyProtocol2::class,
    MyProtocol1::class,
    // The order matters for encoding (reverse of decoding)
];

// Setup logger if needed
Container::$logger = new MyLogger();

// Setup event dispatcher if needed
Container::$eventDispatcher = new MyEventDispatcher();

// Create a Workerman server with ChainProtocol
$worker = new Worker('tcp://0.0.0.0:8080');
$worker->protocol = ChainProtocol::class;

$worker->onMessage = function($connection, $data) {
    // $data is already decoded through all protocols in the chain
    // Process your business logic here

    // Send a response, which will be encoded through all protocols in the chain
    $connection->send('Your response');
};

Worker::runAll();
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
