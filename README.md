# ACP Client for PHP

A minimal PHP client for the [Agent Client Protocol (ACP)](https://agentclientprotocol.com/).

## Features

- JSON-RPC 2.0 request/response over stdio
- Transport interface ready for HTTP/WebSocket extensions
- Synchronous, blocking API
- Supports any JSON-RPC result type from `Client::call()`
- Skips server-initiated JSON-RPC notifications while waiting for a response
- Built-in timeout handling
- Stdio process error messages include captured stderr when available
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
$session = $client->call('session/new', ['cwd' => getcwd(), 'mcpServers' => []]);
$client->notify('agent/cancel');
$transport->close();
```

`initialize()` sends ACP v1-compatible default client information and
capabilities. Pass an array to override any initialization params.

`Client::call()` returns the raw JSON-RPC `result` value, so it may be an array,
string, number, boolean, or `null` depending on the ACP method. `initialize()`
expects an object/array response and throws if the server returns another type.

## Kimi ACP smoke test

If you have Kimi Code installed locally, you can test the stdio transport with:

```bash
php examples/kimi-smoke.php
```

The script starts `kimi acp`, initializes the connection, creates a session for
the current project directory, prints both responses, and then closes the
transport.

## Errors

- `TransportException`: process start, read/write, timeout, or closed transport failures
- `JsonRpcException`: JSON-RPC error responses, with `getJsonRpcCode()` and `getData()`
- `AcpException`: invalid protocol messages, invalid initialize result shape, or parser errors

## Development

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
