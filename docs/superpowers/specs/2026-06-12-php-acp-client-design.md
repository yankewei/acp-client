# PHP ACP Client Design

## Context

Project: `yankewei/acp-client`  
Goal: Build a PHP client for the Agent Client Protocol (ACP), which standardizes communication between code editors/IDEs and coding agents. ACP uses JSON-RPC over stdio for local agents and HTTP/WebSocket for remote agents (remote support is still a work in progress).

Current project state: `composer.json` initialized, no source code yet.

## Scope

Minimum viable version:
- stdio transport only
- JSON-RPC request/response support
- Initialize connection and call ACP methods
- Synchronous, blocking API
- Ignore server-initiated notifications for now

## Chosen Approach

**Interface abstraction + synchronous implementation.**

We define a `TransportInterface` and implement `StdioTransport` as the default. The public API is a single `Client` class. This keeps the initial code simple while leaving room for HTTP/WebSocket or async transports later.

## Project Structure

```
acp-client/
├── composer.json
├── src/
│   ├── Client.php
│   ├── Transport/
│   │   ├── TransportInterface.php
│   │   └── StdioTransport.php
│   ├── JsonRpc/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Error.php
│   └── Exception/
│       ├── AcpException.php
│       ├── TransportException.php
│       └── JsonRpcException.php
├── tests/
│   └── ClientTest.php
└── README.md
```

## Components

### TransportInterface

```php
interface TransportInterface
{
    public function open(): void;
    public function send(string $message): void;
    public function receive(): ?string;
    public function close(): void;
    public function isOpen(): bool;
}
```

Responsibilities:
- Open/close the underlying connection
- Send raw strings
- Read raw strings

### StdioTransport

- Spawns an agent subprocess via `proc_open`
- Writes a single line to stdin on `send()`
- Reads a single line from stdout on `receive()`
- Tracks process state and raises `TransportException` on failure

### JsonRpc\Request

- Represents a JSON-RPC 2.0 request
- Auto-generates a unique `id`
- Provides `toJson(): string`

### JsonRpc\Response

- Represents a JSON-RPC 2.0 response
- Contains `id`, optional `result`, and optional `error`
- Provides `static fromJson(string $json): self`

### JsonRpc\Error

- Represents a JSON-RPC 2.0 error object
- Contains `code`, `message`, and optional `data`

### Client

```php
final class Client
{
    public function __construct(private TransportInterface $transport) {}

    public function initialize(): array;
    public function call(string $method, array $params = []): array;
    public function notify(string $method, array $params = []): void;
}
```

Responsibilities:
- The only public entry point
- Build JSON-RPC requests
- Send through transport and match responses by `id`
- Convert errors into exceptions

## Data Flow

1. User calls `$client->call('agent/run', ['task' => '...'])`
2. Client creates a `JsonRpc\Request` with auto-generated `id`
3. Client serializes the request and calls `TransportInterface::send()`
4. Client loops on `TransportInterface::receive()` until it gets a response with matching `id`
5. If response contains an error, throw `JsonRpcException`
6. Return `result` as an associative array

## Error Handling

Three layers of errors:

1. **Transport layer**: process start/exit/read/write failures → `TransportException`
2. **Protocol layer**: invalid JSON, missing `id`, mismatched `id` → `AcpException`
3. **Business layer**: JSON-RPC error response → `JsonRpcException` (carries `code` and `message`)

`Client::call()` uses a default timeout (30 seconds) to avoid blocking forever.

## Testing

- Use PHPUnit as the test runner
- Test `Client` with a fake `TransportInterface` implementation
- Test JSON-RPC serialization/deserialization
- Test exception paths (transport failure, error response, timeout)

`composer.json` additions:

```json
"autoload": {
    "psr-4": {
        "Yankewei\\AcpClient\\": "src/"
    }
},
"autoload-dev": {
    "psr-4": {
        "Yankewei\\AcpClient\\Tests\\": "tests/"
    }
},
"require-dev": {
    "phpunit/phpunit": "^10.0 || ^11.0"
}
```

## Usage Example

```php
$transport = new StdioTransport(['command' => 'node', 'args' => ['agent.js']]);
$client = new Client($transport);

$client->initialize();
$result = $client->call('agent/run', ['task' => 'refactor login']);
$client->notify('agent/cancel');
```

## Out of Scope

- HTTP/WebSocket transports
- Async/event-loop support
- Server-initiated notifications
- Full ACP type system
- Automatic reconnection/retry
