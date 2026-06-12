# ACP Client for PHP

A minimal PHP client for the [Agent Client Protocol (ACP)](https://agentclientprotocol.com/).

## Features

- JSON-RPC 2.0 request/response over stdio
- Transport interface ready for HTTP/WebSocket extensions
- Synchronous, blocking API
- Built-in timeout handling
- PHPUnit tests

## Installation

```bash
composer require yankewei/acp-client
```

## Usage

```php
use Yankewei\AcpClient\Client;
use Yankewei\AcpClient\Transport\StdioTransport;

$transport = new StdioTransport([
    'command' => 'node',
    'args' => ['agent.js'],
]);

$client = new Client($transport);

$client->initialize();
$result = $client->call('agent/run', ['task' => 'refactor login']);
$client->notify('agent/cancel');
```

## Development

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
