# Workerman Chain Protocol

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-chain-protocol.svg?style=flat-square)]
(https://packagist.org/packages/tourze/workerman-chain-protocol)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/workerman-chain-protocol.svg?style=flat-square)]
(composer.json)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)
[![License](https://img.shields.io/github/license/tourze/php-monorepo.svg?style=flat-square)]
(LICENSE)

[English](README.md) | [中文](README.zh-CN.md)

Chain protocol processing for Workerman. This package allows you to create a 
chain of protocol parsers to decode and encode data in Workerman connections.

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

## Dependencies

This package requires:

- PHP 8.1 or higher
- workerman/workerman ^5.1
- symfony/event-dispatcher ^7.3
- symfony/event-dispatcher-contracts ^3
- tourze/workerman-connection-context 0.0.*
- psr/log ^1|^2|^3
- phpunit/php-timer ^7.0

## Advanced Usage

### Custom Protocol Implementation

```php
<?php

use Workerman\Protocols\ProtocolInterface;
use Workerman\Connection\ConnectionInterface;

class MyCustomProtocol implements ProtocolInterface
{
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        // Return the length of a complete packet, or 0 if more data is needed
        return strlen($buffer) >= 4 ? unpack('N', substr($buffer, 0, 4))[1] + 4 : 0;
    }

    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        // Remove the length header and return the actual data
        return substr($buffer, 4);
    }

    public static function encode(mixed $data, ConnectionInterface $connection): string
    {
        // Add a length header to the data
        return pack('N', strlen($data)) . $data;
    }
}
```

### Event Handling

```php
<?php

use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\Workerman\ChainProtocol\Event\ChainDataDecodedEvent;
use Tourze\Workerman\ChainProtocol\Container;

$eventDispatcher = new EventDispatcher();

$eventDispatcher->addListener(ChainDataDecodedEvent::class, function(ChainDataDecodedEvent $event) {
    // Log or process decoded data
    error_log('Data decoded: ' . $event->getBuffer());
});

Container::$eventDispatcher = $eventDispatcher;
```

### Custom Logger Configuration

```php
<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tourze\Workerman\ChainProtocol\Container;

$logger = new Logger('chain-protocol');
$logger->pushHandler(new StreamHandler('path/to/logfile.log', Logger::DEBUG));

Container::$logger = $logger;
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
