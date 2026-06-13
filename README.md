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
$session = $client->sessionNew(getcwd());
$turn = $client->sessionPrompt($session->getSessionId(), 'Refactor the login flow');
$client->sessionCancel($session->getSessionId());
$transport->close();
```

`initialize()` sends ACP v1-compatible default client information and
capabilities. Pass an array to override any initialization params.

Typed convenience methods are available for stable ACP v1 calls:

- `authenticate($methodId)` and `logout()`
- `sessionNew($cwd, $mcpServers = [], $additionalDirectories = [])`
- `sessionLoad($sessionId, $cwd, $mcpServers = [], $additionalDirectories = [])`
- `sessionResume($sessionId, $cwd, $mcpServers = [], $additionalDirectories = [])`
- `sessionClose($sessionId)`
- `sessionList($cwd = null, $cursor = null)`
- `sessionDelete($sessionId)`
- `sessionPrompt($sessionId, $prompt)`
- `sessionCancel($sessionId)`
- `setConfigOption($sessionId, $configId, $value)`
- `setMode($sessionId, $modeId)` for agents that still expose legacy modes

High-level methods return typed value objects (DTOs) such as `InitializeResult`,
`Session`, `SessionListResult`, and `PromptResult`. The lower-level
`Client::call()` and `Client::notify()` methods remain available for
agent-specific extensions and ACP methods not yet wrapped by this library, and
still return raw arrays/mixed values as the escape hatch.

`PromptResult::getStopReason()` returns the required ACP stop reason string.
Use helpers such as `isEndTurn()` and `isCancelled()` for common branches.

### Authentication

ACP agents advertise authentication methods during initialization. Use
`InitializeResult::getAuthMethods()` to choose a method, then call
`authenticate()` with its ID. Only call `logout()` when the agent advertised the
logout capability:

```php
$initialize = $client->initialize();

$authMethod = $initialize->getAuthMethods()[0] ?? null;
if ($authMethod !== null) {
    $client->authenticate($authMethod->getId());
}

if ($initialize->supportsLogout()) {
    $client->logout();
}
```

If an agent requires authentication before creating a session, it can return a
JSON-RPC error with code `-32000`. `JsonRpcException::isAuthenticationRequired()`
is available for that branch.

### Protocol strictness

By default the client enforces ACP Session Setup requirements that can be
validated locally:

```php
$client = new Client($transport);

$initialize = $client->initialize();

if ($initialize->supportsSessionResume()) {
    $client->sessionResume($sessionId, getcwd());
}
```

Strict mode enforces the ACP Session Setup rules that the client can validate
locally:

- `initialize()` must run before session lifecycle calls
- `session/list`, `session/load`, `session/resume`, `session/close`, and
  `additionalDirectories` require the matching advertised agent capability
- `cwd`, `additionalDirectories`, and stdio MCP `command` values must be
  absolute paths
- stdio, HTTP, and SSE MCP server configurations must match the protocol shape
- HTTP and SSE MCP servers require `mcpCapabilities.http` or
  `mcpCapabilities.sse`
- `session/prompt` content blocks must match the advertised
  `promptCapabilities`; image, audio, and embedded resource content are rejected
  unless the agent advertised support

If you need a permissive thin JSON-RPC wrapper for agent-specific extensions or
compatibility testing, disable strict mode explicitly. In that mode prompt
capability checks are also skipped and content blocks are passed through as raw
JSON-RPC params:

```php
$client = new Client($transport, strictProtocol: false);
```

## Kimi ACP smoke test

If you have Kimi Code installed locally, you can test the stdio transport with:

```bash
php examples/kimi-smoke.php
```

The script starts `kimi acp`, initializes the connection, creates a session for
the current project directory, exercises supported session methods, sends a
prompt, and then closes the transport.

## Notifications

ACP agents can push server-initiated JSON-RPC notifications while a request is
in flight (for example, `session/update` with prompt progress, tool calls, or
usage updates). Register listeners to receive them:

```php
use Yankewei\AcpClient\Event\Notification;

$client->onNotification(function (Notification $notification): void {
    if ($notification->is('session/update')) {
        $params = $notification->getParams();
        // handle progress, tool calls, usage, etc.
    }
});

// Or listen to a specific method only:
$client->on('session/update', function (Notification $notification): void {
    // ...
});
```

Listeners run synchronously on the same thread when a notification arrives, so
they keep the existing blocking API simple. Use `offNotification()` or `off()`
to remove a listener when you no longer need it.

## Handling agent requests

ACP agents can also send JSON-RPC requests *to* the client, for example to read
a file, run a terminal command, ask the user a question, or ask for permission.
Unlike notifications, these messages have an `id` and require the client to send
a JSON-RPC response. Register handlers to respond to them:

```php
$client->onRequest('fs/read_text_file', function (array $params): string {
    return file_get_contents($params['path']);
});

$client->onRequest('terminal/run', function (array $params): array {
    // run command, return { output, exitCode }
    return ['output' => '', 'exitCode' => 0];
});
```

The handler return value is sent back as the JSON-RPC `result`. If a handler
throws, the client replies with a JSON-RPC internal error (`-32603`). If no
handler is registered for a method, it replies with method not found
(`-32601`). Use `offRequest()` to remove a handler.

Some agents use implementation-specific method names for ask-user or permission
requests. Use `onAnyRequest()` as a fallback when you want to inspect or handle
unknown agent requests:

```php
$client->onAnyRequest(function (string $method, array $params): mixed {
    echo "\nAgent request: {$method}\n";
    echo json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    echo "Allow? [y/N] ";
    $answer = strtolower(trim((string) fgets(STDIN)));

    return [
        'outcome' => $answer === 'y' ? 'approved' : 'denied',
    ];
});
```

Method-specific handlers registered with `onRequest()` take precedence over the
fallback handler. Use `offAnyRequest()` to remove a fallback handler.

## Errors

- `TransportException`: process start, read/write, timeout, or closed transport failures
- `JsonRpcException`: JSON-RPC error responses, with `getJsonRpcCode()` and `getData()`
- `AcpException`: invalid protocol messages, invalid initialize result shape, or parser errors

## Development

```bash
composer update
vendor/bin/phpunit
vendor/bin/phpstan analyse -c phpstan.neon.dist
```

## License

MIT
